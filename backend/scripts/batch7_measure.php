<?php

use App\Http\Controllers\Api\V1\FinancialReconciliationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! is_dir(base_path('reports/financial-reliability'))) {
    mkdir(base_path('reports/financial-reliability'), 0777, true);
}

app()->instance('trace_id', (string) Str::uuid());
$controller = app(FinancialReconciliationController::class);
$user = User::first();
$healthReq = Request::create('/api/v1/financial-reconciliation/health', 'GET');
$healthReq->setUserResolver(fn () => $user);

$before = [
    'active_running_runs' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count(),
    'blocked_concurrent_attempts' => (int) DB::table('financial_reconciliation_run_attempts')->where('attempt_status', 'blocked')->count(),
    'stuck_runs' => (int) DB::table('financial_reconciliation_runs')
        ->where('execution_status', 'running')
        ->whereRaw("NOW() - started_at > INTERVAL '20 minutes'")
        ->count(),
    'duplicate_active_runs' => (int) DB::selectOne("
        SELECT COUNT(*) c FROM (
            SELECT run_type, run_date, COUNT(*) cnt
            FROM financial_reconciliation_runs
            WHERE execution_status='running'
            GROUP BY run_type, run_date
            HAVING COUNT(*) > 1
        ) t
    ")->c,
    'health' => $controller->health()->getData(true)['data']['latest_reconciliation_health'] ?? null,
];

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-concurrency-control.batch7.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

DB::table('financial_reconciliation_runs')->insert([
    'uuid' => (string) Str::uuid(),
    'run_type' => 'daily',
    'run_date' => now()->toDateString(),
    'execution_status' => 'running',
    'started_at' => now()->subMinutes(2),
    'completed_at' => null,
    'duration_ms' => null,
    'failure_message' => null,
    'failure_class' => null,
    'executed_at' => now()->subMinutes(2),
    'artifact_path' => 'reports/testing/batch7-active-running.json',
    'detected_cases' => 0,
    'healthy_cases' => 0,
    'invoice_without_ledger_count' => 0,
    'unbalanced_journal_entry_count' => 0,
    'anomalous_reversal_settlement_count' => 0,
    'trace_id' => (string) Str::uuid(),
    'meta' => json_encode(['batch7' => 'active-running']),
    'created_at' => now(),
    'updated_at' => now(),
]);
Artisan::call('finance:reconcile-daily', [
    '--run-date' => now()->addDay()->toDateString(),
    '--out-file' => 'reports/testing/batch7-blocked.json',
]);

DB::table('financial_reconciliation_runs')
    ->where('execution_status', 'running')
    ->update([
        'started_at' => now()->subMinutes(41),
        'updated_at' => now(),
    ]);
Artisan::call('finance:reconcile-daily', [
    '--run-date' => now()->addDays(2)->toDateString(),
    '--out-file' => 'reports/testing/batch7-stuck-recovered.json',
]);

$after = [
    'active_running_runs' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count(),
    'blocked_concurrent_attempts' => (int) DB::table('financial_reconciliation_run_attempts')->where('attempt_status', 'blocked')->count(),
    'stuck_runs' => (int) DB::table('financial_reconciliation_runs')
        ->where('execution_status', 'running')
        ->whereRaw("NOW() - started_at > INTERVAL '20 minutes'")
        ->count(),
    'duplicate_active_runs' => (int) DB::selectOne("
        SELECT COUNT(*) c FROM (
            SELECT run_type, run_date, COUNT(*) cnt
            FROM financial_reconciliation_runs
            WHERE execution_status='running'
            GROUP BY run_type, run_date
            HAVING COUNT(*) > 1
        ) t
    ")->c,
    'health' => $controller->health()->getData(true)['data']['latest_reconciliation_health'] ?? null,
    'latest_blocked_attempt' => DB::table('financial_reconciliation_run_attempts')
        ->where('attempt_status', 'blocked')
        ->orderByDesc('id')
        ->first(),
];

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-concurrency-control.batch7.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'B7_BLOCKED_ATTEMPTS='.$after['blocked_concurrent_attempts'].PHP_EOL;
echo 'B7_DUP_ACTIVE='.$after['duplicate_active_runs'].PHP_EOL;
