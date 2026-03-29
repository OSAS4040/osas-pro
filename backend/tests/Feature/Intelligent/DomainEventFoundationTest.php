<?php

namespace Tests\Feature\Intelligent;

use App\Models\DomainEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainEventFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_trace_and_correlation_headers_are_echoed(): void
    {
        $t = 'test-trace-'.uniqid();
        $c = 'test-corr-'.uniqid();

        $response = $this->withHeaders([
            'X-Trace-Id'       => $t,
            'X-Correlation-Id' => $c,
        ])->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertHeader('X-Trace-Id', $t);
        $response->assertHeader('X-Correlation-Id', $c);
    }

    public function test_customer_create_does_not_persist_domain_events_when_flags_off(): void
    {
        config(['intelligent.events.enabled' => false]);
        config(['intelligent.events.persist.enabled' => false]);

        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->postJson('/api/v1/customers', [
            'type' => 'b2c',
            'name' => 'Intel Customer Off',
        ])->assertCreated();

        $this->assertSame(0, DomainEvent::count());
    }

    public function test_customer_create_records_domain_event_when_flags_on(): void
    {
        config(['intelligent.events.enabled' => true]);
        config(['intelligent.events.persist.enabled' => true]);
        config(['intelligent.observability.enabled' => false]);

        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->postJson('/api/v1/customers', [
            'type' => 'b2c',
            'name' => 'Intel Customer On',
        ])->assertCreated();

        $this->assertGreaterThanOrEqual(1, DomainEvent::where('event_name', 'CustomerCreated')->count());
    }

    public function test_internal_domain_events_returns_404_when_dashboard_disabled(): void
    {
        config(['intelligent.internal_dashboard.enabled' => false]);

        ['user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->getJson('/api/v1/internal/domain-events')->assertNotFound();
    }

    public function test_internal_domain_events_returns_403_for_non_admin(): void
    {
        config(['intelligent.internal_dashboard.enabled' => true]);

        ['company' => $company, 'branch' => $branch] = $this->createTenant();
        $cashier = $this->createUser($company, $branch, 'cashier');

        $this->actingAsUser($cashier)->getJson('/api/v1/internal/domain-events')->assertForbidden();
    }

    public function test_internal_domain_events_lists_events_for_owner(): void
    {
        config(['intelligent.internal_dashboard.enabled' => true]);
        config(['intelligent.events.enabled' => true]);
        config(['intelligent.events.persist.enabled' => true]);

        ['user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->postJson('/api/v1/customers', [
            'type' => 'b2c',
            'name' => 'List Me',
        ])->assertCreated();

        $res = $this->actingAsUser($user)->getJson('/api/v1/internal/domain-events?event_name=CustomerCreated');
        $res->assertOk();
        $res->assertJsonStructure(['data', 'meta', 'trace_id']);
        $this->assertNotEmpty($res->json('data'));
    }
}
