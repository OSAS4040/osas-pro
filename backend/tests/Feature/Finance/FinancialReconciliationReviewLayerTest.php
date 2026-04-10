<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialReconciliationReviewLayerTest extends TestCase
{
    /** مستأجر أوامر finance:reconcile-daily في setUp — الملاحظات مرتبطة بشركته فقط */
    private ?array $reconciliationSeedTenant = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReviewData();
    }

    /** المستخدم/الشركة التي خُزّنت لها بيانات المطابقة في setUp */
    private function reconciliationTenant(): array
    {
        if ($this->reconciliationSeedTenant === null) {
            self::fail('reconciliation seed tenant not initialized');
        }

        return $this->reconciliationSeedTenant;
    }

    public function test_can_read_latest_run(): void
    {
        $tenant = $this->createTenant();
        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/latest');

        $response->assertOk()
            ->assertJsonPath('data.run_type', 'daily')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_can_filter_findings_by_type_and_company(): void
    {
        $tenant = $this->reconciliationTenant();
        $companyId = (int) $tenant['company']->id;

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/findings?finding_type=anomalous_reversal_settlement');

        $response->assertOk();
        $rows = $response->json('data.data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('anomalous_reversal_settlement', $row['finding_type']);
            $this->assertSame($companyId, (int) $row['company_id']);
        }
    }

    public function test_can_update_finding_status_open_to_acknowledged(): void
    {
        $tenant = $this->reconciliationTenant();
        $findingId = (int) DB::table('financial_reconciliation_findings')
            ->where('company_id', $tenant['company']->id)
            ->where('status', 'open')
            ->value('id');

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", [
                'status' => 'acknowledged',
                'note' => 'Reviewed by operations',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');
    }

    public function test_rejects_invalid_status_transition(): void
    {
        $tenant = $this->reconciliationTenant();
        $findingId = (int) DB::table('financial_reconciliation_findings')
            ->where('company_id', $tenant['company']->id)
            ->where('status', 'open')
            ->value('id');

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", [
                'status' => 'resolved',
                'note' => 'resolved in invalid-transition setup',
            ])
            ->assertOk();

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", ['status' => 'acknowledged'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'TRANSITION_NOT_ALLOWED');
    }

    public function test_rejects_resolved_without_note(): void
    {
        $tenant = $this->reconciliationTenant();
        $findingId = (int) DB::table('financial_reconciliation_findings')
            ->where('company_id', $tenant['company']->id)
            ->where('status', 'open')
            ->value('id');

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", ['status' => 'resolved'])
            ->assertStatus(422);
    }

    public function test_rejects_false_positive_without_note(): void
    {
        $tenant = $this->reconciliationTenant();
        $findingId = (int) DB::table('financial_reconciliation_findings')
            ->where('company_id', $tenant['company']->id)
            ->where('status', 'open')
            ->value('id');

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", ['status' => 'false_positive'])
            ->assertStatus(422);
    }

    public function test_acknowledged_without_note_is_allowed_and_history_is_saved(): void
    {
        $tenant = $this->reconciliationTenant();
        $findingId = (int) DB::table('financial_reconciliation_findings')
            ->where('company_id', $tenant['company']->id)
            ->where('status', 'open')
            ->value('id');

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", ['status' => 'acknowledged'])
            ->assertOk();

        $this->assertDatabaseHas('financial_reconciliation_finding_histories', [
            'finding_id' => $findingId,
            'old_status' => 'open',
            'new_status' => 'acknowledged',
        ]);
    }

    public function test_can_read_finding_with_history(): void
    {
        $tenant = $this->reconciliationTenant();
        $findingId = (int) DB::table('financial_reconciliation_findings')
            ->where('company_id', $tenant['company']->id)
            ->where('status', 'open')
            ->value('id');

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", [
                'status' => 'acknowledged',
                'note' => 'history seed',
            ])
            ->assertOk();

        $this->actingAsUser($tenant['user'])
            ->getJson("/api/v1/financial-reconciliation/findings/{$findingId}")
            ->assertOk()
            ->assertJsonPath('data.finding.id', $findingId)
            ->assertJsonCount(1, 'data.history');
    }

    public function test_update_action_requires_permission(): void
    {
        $tenant = $this->reconciliationTenant();
        $cashier = $this->createUser($tenant['company'], $tenant['branch'], 'cashier');
        $findingId = (int) DB::table('financial_reconciliation_findings')
            ->where('company_id', $tenant['company']->id)
            ->value('id');

        $this->actingAsUser($cashier)
            ->patchJson("/api/v1/financial-reconciliation/findings/{$findingId}/status", ['status' => 'acknowledged'])
            ->assertStatus(403);
    }

    public function test_summary_endpoint_reflects_db_and_contains_health_and_runbook_reference(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/summary')
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame(
            DB::table('financial_reconciliation_findings')->where('status', 'open')->count(),
            (int) $data['open_findings']
        );
        $this->assertSame(
            DB::table('financial_reconciliation_findings')->whereIn('status', ['open', 'acknowledged'])->count(),
            (int) $data['unresolved_findings']
        );
        $this->assertContains($data['latest_reconciliation_health'], ['healthy', 'warning', 'critical']);
        $this->assertSame('docs/financial-reconciliation-operational-runbook.md', $data['runbook_reference']);
    }

    public function test_health_classification_is_critical_when_open_invoice_without_ledger_exists(): void
    {
        $tenant = $this->createTenant();
        $runId = (int) DB::table('financial_reconciliation_runs')->orderByDesc('id')->value('id');
        DB::table('financial_reconciliation_findings')->insert([
            'run_id' => $runId,
            'finding_type' => 'invoice_without_ledger',
            'status' => 'open',
            'company_id' => $tenant['company']->id,
            'details' => json_encode(['source' => 'test-critical']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/health')
            ->assertOk()
            ->assertJsonPath('data.latest_reconciliation_health', 'critical');
    }

    public function test_health_endpoint_reports_unresolved_aging_and_status_counts(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/health')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'latest_reconciliation_health',
                'latest_run',
                'last_successful_run',
                'last_failed_run',
                'stale_status',
                'hours_since_last_success',
                'runs_by_execution_status' => ['running', 'succeeded', 'failed'],
                'has_running_run',
                'running_runs_count',
                'has_stuck_run',
                'stuck_runs_count',
                'blocked_concurrent_attempts_count',
                'latest_blocked_attempt',
                'concurrent_run_prevention_active',
                'findings_by_status' => ['open', 'acknowledged', 'resolved', 'false_positive'],
                'findings_by_type_map' => ['invoice_without_ledger', 'unbalanced_journal_entry', 'anomalous_reversal_settlement'],
                'unresolved_aging' => ['0_1_days', '2_7_days', '8_plus_days'],
                'runbook_reference',
            ],
            'trace_id',
        ]);
    }

    public function test_health_is_critical_when_last_run_failed(): void
    {
        $tenant = $this->createTenant();
        DB::table('financial_reconciliation_runs')->insert([
            'uuid' => (string) Str::uuid(),
            'run_type' => 'daily',
            'run_date' => now()->toDateString(),
            'execution_status' => 'failed',
            'started_at' => now()->subMinutes(3),
            'completed_at' => now()->subMinutes(1),
            'duration_ms' => 120000,
            'failure_message' => 'Simulated failure',
            'failure_class' => 'RuntimeException',
            'executed_at' => now()->subMinutes(1),
            'artifact_path' => 'reports/testing/failure.json',
            'detected_cases' => 0,
            'healthy_cases' => 0,
            'invoice_without_ledger_count' => 0,
            'unbalanced_journal_entry_count' => 0,
            'anomalous_reversal_settlement_count' => 0,
            'trace_id' => (string) Str::uuid(),
            'meta' => json_encode(['test' => 'failed-run']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/health')
            ->assertOk()
            ->assertJsonPath('data.latest_reconciliation_health', 'critical');
    }

    public function test_health_is_warning_when_last_success_is_stale_warning_window(): void
    {
        $tenant = $this->createTenant();
        DB::table('financial_reconciliation_runs')
            ->where('execution_status', 'failed')
            ->delete();
        DB::table('financial_reconciliation_runs')
            ->where('execution_status', 'succeeded')
            ->update([
                'completed_at' => now()->subHours(31),
                'started_at' => now()->subHours(31)->subMinutes(3),
                'updated_at' => now(),
            ]);
        DB::table('financial_reconciliation_findings')->update([
            'status' => 'resolved',
            'updated_at' => now(),
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/health')
            ->assertOk()
            ->assertJsonPath('data.stale_status', 'warning')
            ->assertJsonPath('data.latest_reconciliation_health', 'warning');
    }

    public function test_health_is_critical_when_last_success_is_stale_critical_window(): void
    {
        $tenant = $this->createTenant();
        DB::table('financial_reconciliation_runs')
            ->where('execution_status', 'failed')
            ->delete();
        DB::table('financial_reconciliation_runs')
            ->where('execution_status', 'succeeded')
            ->update([
                'completed_at' => now()->subHours(55),
                'started_at' => now()->subHours(55)->subMinutes(2),
                'updated_at' => now(),
            ]);
        DB::table('financial_reconciliation_findings')->update([
            'status' => 'resolved',
            'updated_at' => now(),
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/health')
            ->assertOk()
            ->assertJsonPath('data.stale_status', 'critical')
            ->assertJsonPath('data.latest_reconciliation_health', 'critical');
    }

    public function test_health_is_critical_when_stuck_running_exists(): void
    {
        $tenant = $this->createTenant();
        DB::table('financial_reconciliation_runs')->insert([
            'uuid' => (string) Str::uuid(),
            'run_type' => 'daily',
            'run_date' => now()->toDateString(),
            'execution_status' => 'running',
            'started_at' => now()->subMinutes(45),
            'completed_at' => null,
            'duration_ms' => null,
            'failure_message' => null,
            'failure_class' => null,
            'executed_at' => now()->subMinutes(45),
            'artifact_path' => 'reports/testing/stuck-health.json',
            'detected_cases' => 0,
            'healthy_cases' => 0,
            'invoice_without_ledger_count' => 0,
            'unbalanced_journal_entry_count' => 0,
            'anomalous_reversal_settlement_count' => 0,
            'trace_id' => (string) Str::uuid(),
            'meta' => json_encode(['test' => 'stuck-health']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/financial-reconciliation/health')
            ->assertOk()
            ->assertJsonPath('data.has_stuck_run', true)
            ->assertJsonPath('data.latest_reconciliation_health', 'critical');
    }

    private function seedReviewData(): void
    {
        $tenant = $this->createTenant();
        $this->reconciliationSeedTenant = $tenant;

        $invoice = Invoice::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number' => 'INV-REV-LAYER-001',
            'type' => 'sale',
            'status' => 'pending',
            'customer_type' => 'b2c',
            'subtotal' => 10,
            'tax_amount' => 1.5,
            'total' => 11.5,
            'paid_amount' => 0,
            'due_amount' => 11.5,
            'currency' => 'SAR',
        ]);

        Payment::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'invoice_id' => $invoice->id,
            'created_by_user_id' => $tenant['user']->id,
            'method' => 'cash',
            'amount' => 11.5,
            'currency' => 'SAR',
            'status' => 'refunded',
            'trace_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);

        // Fixed past date: must not equal "today" or inserts using now()->toDateString() hit unique(run_type, run_date).
        $this->artisan('finance:reconcile-daily --run-date=2019-07-15 --out-file=reports/financial-reliability/reconciliation-review-layer-seed.json')
            ->assertExitCode(0);
    }
}
