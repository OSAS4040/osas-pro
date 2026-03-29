<?php

namespace Tests\Feature\Intelligent;

use App\Models\DomainEvent;
use App\Models\Invoice;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2ReadonlyApiTest extends TestCase
{
    use RefreshDatabase;

    private function enablePhase2All(): void
    {
        config(['intelligent.internal_dashboard.enabled' => true]);
        config(['intelligent.phase2.enabled' => true]);
        config(['intelligent.phase2.features.overview' => true]);
        config(['intelligent.phase2.features.insights' => true]);
        config(['intelligent.phase2.features.recommendations' => true]);
        config(['intelligent.phase2.features.alerts' => true]);
        config(['intelligent.command_center_api.enabled' => true]);
        config(['intelligent.phase2.features.command_center' => true]);
    }

    public function test_phase2_routes_404_when_master_disabled(): void
    {
        config(['intelligent.internal_dashboard.enabled' => true]);
        config(['intelligent.phase2.enabled' => false]);

        ['user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->getJson('/api/v1/internal/intelligence/overview')->assertNotFound();
    }

    public function test_phase2_routes_404_when_internal_dashboard_disabled(): void
    {
        config(['intelligent.internal_dashboard.enabled' => false]);
        config(['intelligent.phase2.enabled' => true]);
        config(['intelligent.phase2.features.overview' => true]);

        ['user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->getJson('/api/v1/internal/intelligence/overview')->assertNotFound();
    }

    public function test_phase2_overview_404_when_feature_disabled(): void
    {
        $this->enablePhase2All();
        config(['intelligent.phase2.features.overview' => false]);

        ['user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->getJson('/api/v1/internal/intelligence/overview')->assertNotFound();
    }

    public function test_phase2_returns_403_for_non_admin(): void
    {
        $this->enablePhase2All();

        ['company' => $company, 'branch' => $branch] = $this->createTenant();
        $cashier = $this->createUser($company, $branch, 'cashier');

        $this->actingAsUser($cashier)->getJson('/api/v1/internal/intelligence/overview')->assertForbidden();
    }

    public function test_phase2_overview_insights_recommendations_alerts_ok_for_owner(): void
    {
        $this->enablePhase2All();
        ['company' => $company, 'user' => $user] = $this->createTenant();

        DomainEvent::create([
            'uuid'            => (string) Str::uuid(),
            'company_id'      => $company->id,
            'branch_id'       => null,
            'aggregate_type'  => 'Customer',
            'aggregate_id'    => '1',
            'event_name'      => 'CustomerCreated',
            'event_version'   => 1,
            'payload_json'    => [],
            'metadata_json'   => [],
            'trace_id'        => 't1',
            'correlation_id'  => 'c1',
            'occurred_at'     => now()->subDay(),
            'processing_status' => 'recorded',
        ]);

        DomainEvent::create([
            'uuid'              => (string) Str::uuid(),
            'company_id'        => $company->id,
            'branch_id'         => null,
            'aggregate_type'    => 'invoice',
            'aggregate_id'      => '42',
            'event_name'        => 'InvoiceCreated',
            'event_version'     => 1,
            'payload_json'      => [],
            'metadata_json'     => [],
            'trace_id'          => 't-inv',
            'correlation_id'    => 'c-inv',
            'occurred_at'       => now()->subHours(3),
            'processing_status' => 'recorded',
        ]);

        $base = '/api/v1/internal/intelligence';

        $o = $this->actingAsUser($user)->getJson("{$base}/overview");
        $o->assertOk();
        $o->assertJsonPath('meta.read_only', true);
        $o->assertJsonPath('meta.phase', 2);
        $o->assertJsonPath('data.read_only', true);
        $o->assertJsonPath('data.summary.total_domain_events', 2);

        $i = $this->actingAsUser($user)->getJson("{$base}/insights");
        $i->assertOk();
        $i->assertJsonStructure(['data' => ['window', 'totals', 'by_event_name', 'daily_counts'], 'meta', 'trace_id']);
        $i->assertJsonPath('data.totals.events', 2);

        $r = $this->actingAsUser($user)->getJson("{$base}/recommendations");
        $r->assertOk();
        $this->assertIsArray($r->json('data'));
        $this->assertNotEmpty($r->json('data'));

        $a = $this->actingAsUser($user)->getJson("{$base}/alerts");
        $a->assertOk();
        $this->assertIsArray($a->json('data'));

        $cc = $this->actingAsUser($user)->getJson("{$base}/command-center");
        $cc->assertOk();
        $cc->assertJsonPath('meta.read_only', true);
        $cc->assertJsonPath('meta.phase', 6);
        $cc->assertJsonPath('data.read_only', true);
        $cc->assertJsonStructure([
            'data' => [
                'zones' => ['now', 'next', 'watch'],
                'summary' => ['total_now', 'total_next', 'total_watch', 'low_signal'],
            ],
        ]);

        $ccData = $cc->json('data');
        $hrefs = [];
        foreach (['now', 'next', 'watch'] as $zone) {
            foreach ($ccData['zones'][$zone] ?? [] as $item) {
                $this->assertArrayHasKey('related_entity_references', $item);
                $this->assertArrayHasKey('why_details', $item);
                $this->assertArrayHasKey('signals_used', $item);
                $this->assertArrayHasKey('thresholds', $item);
                $this->assertArrayHasKey('confidence', $item);
                $this->assertArrayHasKey('governance_ref', $item);
                $this->assertIsArray($item['why_details']);
                $this->assertIsArray($item['signals_used']);
                $this->assertIsArray($item['thresholds']);
                foreach ($item['related_entity_references'] as $ref) {
                    $this->assertArrayHasKey('type', $ref);
                    $this->assertArrayHasKey('id', $ref);
                    $this->assertArrayHasKey('label', $ref);
                    $this->assertArrayHasKey('href', $ref);
                    $hrefs[] = $ref['href'];
                }
            }
        }
        $this->assertContains('/invoices/42', $hrefs);
        $this->assertContains('/customers', $hrefs);
    }

    public function test_command_center_404_when_feature_disabled(): void
    {
        $this->enablePhase2All();
        config(['intelligent.command_center_api.enabled' => false]);
        config(['intelligent.phase2.features.command_center' => false]);

        ['user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->getJson('/api/v1/internal/intelligence/command-center')->assertNotFound();
    }

    public function test_phase2_get_endpoints_do_not_mutate_operational_or_event_tables(): void
    {
        $this->enablePhase2All();
        ['company' => $company, 'user' => $user] = $this->createTenant();

        DomainEvent::create([
            'uuid'            => (string) Str::uuid(),
            'company_id'      => $company->id,
            'branch_id'       => null,
            'aggregate_type'  => 'Invoice',
            'aggregate_id'    => '99',
            'event_name'      => 'InvoiceCreated',
            'event_version'   => 1,
            'payload_json'    => ['x' => 1],
            'metadata_json'   => [],
            'trace_id'        => 't-readonly',
            'correlation_id'  => 'c-readonly',
            'occurred_at'     => now()->subHours(2),
            'processing_status' => 'recorded',
        ]);

        $domainEventsBefore = DomainEvent::count();
        $invoicesBefore = Invoice::count();
        $walletTxBefore = WalletTransaction::count();

        $base = '/api/v1/internal/intelligence';
        $this->actingAsUser($user)->getJson("{$base}/overview")->assertOk();
        $this->actingAsUser($user)->getJson("{$base}/insights")->assertOk();
        $this->actingAsUser($user)->getJson("{$base}/recommendations")->assertOk();
        $this->actingAsUser($user)->getJson("{$base}/alerts")->assertOk();
        $this->actingAsUser($user)->getJson("{$base}/command-center")->assertOk();

        $this->assertSame($domainEventsBefore, DomainEvent::count());
        $this->assertSame($invoicesBefore, Invoice::count());
        $this->assertSame($walletTxBefore, WalletTransaction::count());
    }
}
