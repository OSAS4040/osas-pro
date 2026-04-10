<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialReconciliationCommandTest extends TestCase
{
    public function test_reports_healthy_state_when_no_anomalies_exist(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoice($tenant, 'INV-REC-OK-001');

        $this->createBalancedInvoiceEntry($tenant, $invoice, 'JE-REC-OK-001');

        $out = 'reports/testing/reconciliation-healthy.json';
        $this->artisan("finance:reconcile-daily --out-file={$out}")
            ->assertExitCode(0);

        $report = json_decode((string) file_get_contents(base_path($out)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $report['summary']['detected_cases']);
        $this->assertGreaterThan(0, $report['summary']['healthy_cases']);
        $this->assertDatabaseCount('financial_reconciliation_runs', 1);
        $this->assertDatabaseCount('financial_reconciliation_findings', 0);
    }

    public function test_creates_run_and_persists_findings_when_anomaly_exists(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoice($tenant, 'INV-REC-DB-001');
        $this->createBalancedInvoiceEntry($tenant, $invoice, 'JE-REC-DB-001');

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'invoice_id' => $invoice->id,
            'created_by_user_id' => $tenant['user']->id,
            'method' => 'cash',
            'amount' => 10,
            'currency' => 'SAR',
            'status' => 'refunded',
            'trace_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);

        $out = 'reports/testing/reconciliation-db-persist.json';
        $this->artisan("finance:reconcile-daily --out-file={$out}")
            ->assertExitCode(0);

        $this->assertDatabaseCount('financial_reconciliation_runs', 1);
        $run = DB::table('financial_reconciliation_runs')->first();
        $this->assertSame(1, (int) $run->anomalous_reversal_settlement_count);
        $this->assertDatabaseHas('financial_reconciliation_findings', [
            'run_id' => $run->id,
            'finding_type' => 'anomalous_reversal_settlement',
            'payment_id' => $payment->id,
        ]);
    }

    public function test_detects_invoice_without_ledger_entry(): void
    {
        $tenant = $this->createTenant();
        $this->createInvoice($tenant, 'INV-REC-MISS-001');

        $out = 'reports/testing/reconciliation-missing-ledger.json';
        $this->artisan("finance:reconcile-daily --out-file={$out}")
            ->assertExitCode(0);

        $report = json_decode((string) file_get_contents(base_path($out)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['checks']['invoice_without_ledger']['detected']);
    }

    public function test_detects_unbalanced_journal_entry(): void
    {
        $tenant = $this->createTenant();

        JournalEntry::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'entry_number' => 'JE-REC-UNBAL-001',
            'type' => 'adjustment',
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced test entry',
            'total_debit' => 100,
            'total_credit' => 90,
            'currency' => 'SAR',
            'trace_id' => (string) Str::uuid(),
            'created_by_user_id' => $tenant['user']->id,
        ]);

        $out = 'reports/testing/reconciliation-unbalanced.json';
        $this->artisan("finance:reconcile-daily --out-file={$out}")
            ->assertExitCode(0);

        $report = json_decode((string) file_get_contents(base_path($out)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['checks']['unbalanced_journal_entries']['detected']);
    }

    public function test_detects_reversal_settlement_anomaly(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoice($tenant, 'INV-REC-REV-001');

        Payment::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'invoice_id' => $invoice->id,
            'created_by_user_id' => $tenant['user']->id,
            'method' => 'cash',
            'amount' => 20,
            'currency' => 'SAR',
            'status' => 'refunded',
            'trace_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);

        $out = 'reports/testing/reconciliation-reversal-anomaly.json';
        $this->artisan("finance:reconcile-daily --out-file={$out}")
            ->assertExitCode(0);

        $report = json_decode((string) file_get_contents(base_path($out)), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $report['checks']['anomalous_reversal_settlement']['detected']);
    }

    public function test_is_idempotent_for_same_daily_run_date(): void
    {
        $tenant = $this->createTenant();
        $this->createInvoice($tenant, 'INV-REC-IDEMP-001');

        $out = 'reports/testing/reconciliation-idempotent.json';
        $runDate = now()->toDateString();

        $this->artisan("finance:reconcile-daily --out-file={$out} --run-date={$runDate}")
            ->assertExitCode(0);
        $this->artisan("finance:reconcile-daily --out-file={$out} --run-date={$runDate}")
            ->assertExitCode(0);

        $this->assertDatabaseCount('financial_reconciliation_runs', 1);
    }

    public function test_artifact_and_db_counters_are_consistent(): void
    {
        $tenant = $this->createTenant();
        $this->createInvoice($tenant, 'INV-REC-CONSIST-001');
        JournalEntry::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'entry_number' => 'JE-REC-CONSIST-001',
            'type' => 'adjustment',
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced for consistency check',
            'total_debit' => 40,
            'total_credit' => 30,
            'currency' => 'SAR',
            'trace_id' => (string) Str::uuid(),
            'created_by_user_id' => $tenant['user']->id,
        ]);

        $out = 'reports/testing/reconciliation-consistency.json';
        $this->artisan("finance:reconcile-daily --out-file={$out}")
            ->assertExitCode(0);

        $report = json_decode((string) file_get_contents(base_path($out)), true, 512, JSON_THROW_ON_ERROR);
        $run = DB::table('financial_reconciliation_runs')->first();
        $dbFindings = DB::table('financial_reconciliation_findings')->count();

        $this->assertSame((int) $report['summary']['detected_cases'], (int) $run->detected_cases);
        $this->assertSame((int) $report['summary']['detected_cases'], $dbFindings);
    }

    public function test_successful_run_sets_execution_status_to_succeeded(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->createInvoice($tenant, 'INV-REC-STATUS-OK-001');
        $this->createBalancedInvoiceEntry($tenant, $invoice, 'JE-REC-STATUS-OK-001');

        $out = 'reports/testing/reconciliation-run-status-ok.json';
        $this->artisan("finance:reconcile-daily --out-file={$out}")
            ->assertExitCode(0);

        $run = DB::table('financial_reconciliation_runs')->first();
        $this->assertSame('succeeded', (string) $run->execution_status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertNotNull($run->duration_ms);
    }

    public function test_failed_run_sets_execution_status_to_failed(): void
    {
        $out = 'reports/testing/reconciliation-run-status-failed.json';
        $this->artisan("finance:reconcile-daily --out-file={$out} --simulate-failure")
            ->assertExitCode(1);

        $run = DB::table('financial_reconciliation_runs')->first();
        $this->assertSame('failed', (string) $run->execution_status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
        $this->assertNotNull($run->duration_ms);
        $this->assertNotEmpty($run->failure_message);
        $this->assertNotEmpty($run->failure_class);
    }

    public function test_prevents_second_run_when_active_running_exists(): void
    {
        DB::table('financial_reconciliation_runs')->insert([
            'uuid' => (string) Str::uuid(),
            'run_type' => 'daily',
            'run_date' => now()->toDateString(),
            'execution_status' => 'running',
            'started_at' => now()->subMinutes(3),
            'completed_at' => null,
            'duration_ms' => null,
            'failure_message' => null,
            'failure_class' => null,
            'executed_at' => now()->subMinutes(3),
            'artifact_path' => 'reports/testing/running-active.json',
            'detected_cases' => 0,
            'healthy_cases' => 0,
            'invoice_without_ledger_count' => 0,
            'unbalanced_journal_entry_count' => 0,
            'anomalous_reversal_settlement_count' => 0,
            'trace_id' => (string) Str::uuid(),
            'meta' => json_encode(['test' => 'active-running']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('finance:reconcile-daily --run-date='.now()->addDay()->toDateString().' --out-file=reports/testing/concurrency-blocked.json')
            ->assertExitCode(1);

        $this->assertSame(1, DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count());
        $this->assertDatabaseHas('financial_reconciliation_run_attempts', ['attempt_status' => 'blocked']);
    }

    public function test_marks_stuck_running_then_allows_new_run(): void
    {
        DB::table('financial_reconciliation_runs')->insert([
            'uuid' => (string) Str::uuid(),
            'run_type' => 'daily',
            'run_date' => now()->subDay()->toDateString(),
            'execution_status' => 'running',
            'started_at' => now()->subMinutes(35),
            'completed_at' => null,
            'duration_ms' => null,
            'failure_message' => null,
            'failure_class' => null,
            'executed_at' => now()->subMinutes(35),
            'artifact_path' => 'reports/testing/running-stuck.json',
            'detected_cases' => 0,
            'healthy_cases' => 0,
            'invoice_without_ledger_count' => 0,
            'unbalanced_journal_entry_count' => 0,
            'anomalous_reversal_settlement_count' => 0,
            'trace_id' => (string) Str::uuid(),
            'meta' => json_encode(['test' => 'stuck-running']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('finance:reconcile-daily --out-file=reports/testing/stuck-recovered.json')
            ->assertExitCode(0);

        $this->assertSame(0, DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count());
        $this->assertGreaterThanOrEqual(1, DB::table('financial_reconciliation_runs')->where('execution_status', 'failed')->count());
        $this->assertDatabaseHas('financial_reconciliation_runs', [
            'execution_status' => 'failed',
            'failure_class' => 'App\\Services\\Finance\\Exceptions\\ReconciliationStuckRunException',
        ]);
    }

    private function createInvoice(array $tenant, string $invoiceNumber): Invoice
    {
        return Invoice::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number' => $invoiceNumber,
            'type' => 'sale',
            'status' => 'pending',
            'customer_type' => 'b2c',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'due_amount' => 115,
            'currency' => 'SAR',
        ]);
    }

    private function createBalancedInvoiceEntry(array $tenant, Invoice $invoice, string $entryNumber): JournalEntry
    {
        return JournalEntry::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'entry_number' => $entryNumber,
            'type' => 'sale',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Balanced invoice entry',
            'total_debit' => 115,
            'total_credit' => 115,
            'currency' => 'SAR',
            'trace_id' => (string) Str::uuid(),
            'created_by_user_id' => $tenant['user']->id,
        ]);
    }
}
