<?php

namespace Tests\Feature\Intelligent;

use App\Models\DomainEvent;
use App\Models\IntelligenceCommandCenterGovernanceAudit;
use App\Models\Invoice;
use App\Models\WalletTransaction;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7GovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function enableAll(): void
    {
        config(['intelligent.internal_dashboard.enabled' => true]);
        config(['intelligent.phase2.enabled' => true]);
        config(['intelligent.phase2.features.overview' => true]);
        config(['intelligent.phase2.features.insights' => true]);
        config(['intelligent.phase2.features.recommendations' => true]);
        config(['intelligent.phase2.features.alerts' => true]);
        config(['intelligent.command_center_api.enabled' => true]);
        config(['intelligent.phase2.features.command_center' => true]);
        config(['intelligent.command_center_governance.enabled' => true]);
    }

    private function seedDomainEventsForCommandCenter($company): void
    {
        DomainEvent::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'       => $company->id,
            'branch_id'        => null,
            'aggregate_type'   => 'invoice',
            'aggregate_id'     => '42',
            'event_name'       => 'InvoiceCreated',
            'event_version'    => 1,
            'payload_json'     => [],
            'metadata_json'    => [],
            'trace_id'         => 't-inv',
            'correlation_id'   => 'c-inv',
            'occurred_at'      => now()->subHour(),
            'processing_status'=> 'recorded',
        ]);

        DomainEvent::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'       => $company->id,
            'branch_id'        => null,
            'aggregate_type'   => 'Customer',
            'aggregate_id'     => '1',
            'event_name'       => 'CustomerCreated',
            'event_version'    => 1,
            'payload_json'     => [],
            'metadata_json'    => [],
            'trace_id'         => 't1',
            'correlation_id'   => 'c1',
            'occurred_at'      => now()->subDay(),
            'processing_status'=> 'recorded',
        ]);
    }

    private function firstGovernanceRef($user): string
    {
        $base = '/api/v1/internal/intelligence';
        $cc = $this->actingAsUser($user)->getJson("{$base}/command-center");
        $cc->assertOk();
        $data = $cc->json('data');
        foreach (['now', 'next', 'watch'] as $z) {
            foreach ($data['zones'][$z] ?? [] as $item) {
                if (! empty($item['governance_ref'])) {
                    return (string) $item['governance_ref'];
                }
            }
        }

        $this->fail('No command-center item with governance_ref');
    }

    public function test_post_governance_creates_one_audit_row_and_updates_latest_summary(): void
    {
        $this->enableAll();
        ['company' => $company, 'user' => $user] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);

        $ref = $this->firstGovernanceRef($user);
        $this->assertSame(0, IntelligenceCommandCenterGovernanceAudit::count());

        $post = $this->actingAsUser($user)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'acknowledged',
            'note'           => 'راجعت البند',
        ]);
        $post->assertCreated();
        $post->assertJsonPath('meta.audit_only', true);
        $this->assertSame(1, IntelligenceCommandCenterGovernanceAudit::count());

        $cc2 = $this->actingAsUser($user)->getJson('/api/v1/internal/intelligence/command-center');
        $cc2->assertOk();
        $data = $cc2->json('data');
        $found = false;
        foreach (['now', 'next', 'watch'] as $z) {
            foreach ($data['zones'][$z] ?? [] as $item) {
                if (($item['governance_ref'] ?? null) === $ref) {
                    $this->assertSame('acknowledged', $item['latest_governance_action']);
                    $this->assertNotNull($item['latest_governance_at']);
                    $this->assertSame($user->name, $item['latest_governance_by']);
                    $found = true;
                }
            }
        }
        $this->assertTrue($found);
    }

    public function test_post_governance_does_not_mutate_domain_events_or_business_tables(): void
    {
        $this->enableAll();
        ['company' => $company, 'user' => $user] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);

        $de = DomainEvent::count();
        $inv = Invoice::count();
        $wt = WalletTransaction::count();
        $wo = WorkOrder::count();

        $ref = $this->firstGovernanceRef($user);

        $this->actingAsUser($user)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'needs_follow_up',
        ])->assertCreated();

        $this->assertSame($de, DomainEvent::count());
        $this->assertSame($inv, Invoice::count());
        $this->assertSame($wt, WalletTransaction::count());
        $this->assertSame($wo, WorkOrder::count());
    }

    public function test_invalid_governance_ref_rejected(): void
    {
        $this->enableAll();
        ['user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => 'v1.invalid.token.here',
            'action'         => 'acknowledged',
        ])->assertStatus(422);
    }

    public function test_cashier_rejected_for_governance(): void
    {
        $this->enableAll();
        ['company' => $company, 'branch' => $branch, 'user' => $owner] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);
        $ref = $this->firstGovernanceRef($owner);

        $cashier = $this->createUser($company, $branch, 'cashier');

        $this->actingAsUser($cashier)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'acknowledged',
        ])->assertForbidden();
    }

    public function test_unsupported_action_rejected(): void
    {
        $this->enableAll();
        ['company' => $company, 'user' => $user] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);
        $ref = $this->firstGovernanceRef($user);

        $this->actingAsUser($user)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'approve_payment',
        ])->assertStatus(422);
    }

    public function test_note_length_validation(): void
    {
        $this->enableAll();
        ['company' => $company, 'user' => $user] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);
        $ref = $this->firstGovernanceRef($user);

        $this->actingAsUser($user)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'acknowledged',
            'note'           => str_repeat('a', 501),
        ])->assertStatus(422);
    }

    public function test_get_history_returns_audit_rows(): void
    {
        $this->enableAll();
        ['company' => $company, 'user' => $user] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);
        $ref = $this->firstGovernanceRef($user);

        $this->actingAsUser($user)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'ignored_for_now',
            'note'           => 'مؤقت',
        ])->assertCreated();

        $h = $this->actingAsUser($user)->getJson(
            '/api/v1/internal/intelligence/command-center/governance/history?'.http_build_query(['governance_ref' => $ref])
        );
        $h->assertOk();
        $rows = $h->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame('ignored_for_now', $rows[0]['action']);
        $this->assertSame('مؤقت', $rows[0]['note']);
    }

    public function test_command_center_get_remains_read_only(): void
    {
        $this->enableAll();
        ['company' => $company, 'user' => $user] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);

        $before = DomainEvent::count();
        $this->actingAsUser($user)->getJson('/api/v1/internal/intelligence/command-center')->assertOk();
        $this->assertSame($before, DomainEvent::count());
    }

    public function test_governance_endpoints_404_when_feature_disabled(): void
    {
        $this->enableAll();
        ['company' => $company, 'user' => $user] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);
        $ref = $this->firstGovernanceRef($user);

        config(['intelligent.command_center_governance.enabled' => false]);

        $this->actingAsUser($user)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'acknowledged',
        ])->assertNotFound();
    }

    public function test_manager_can_record_governance(): void
    {
        $this->enableAll();
        ['company' => $company, 'branch' => $branch] = $this->createTenant();
        $this->seedDomainEventsForCommandCenter($company);
        $manager = $this->createUser($company, $branch, 'manager');

        $ref = $this->firstGovernanceRef($manager);

        $this->actingAsUser($manager)->postJson('/api/v1/internal/intelligence/command-center/governance', [
            'governance_ref' => $ref,
            'action'         => 'acknowledged',
        ])->assertCreated();
    }
}
