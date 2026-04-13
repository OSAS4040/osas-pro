<?php

use App\Http\Controllers\Api\V1\FinancialReconciliationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! is_dir(base_path('reports/financial-reliability'))) {
    mkdir(base_path('reports/financial-reliability'), 0777, true);
}

app()->instance('trace_id', (string) Str::uuid());

$controller = app(FinancialReconciliationController::class);
$user = User::first();

$healthRequest = Request::create('/api/v1/financial-reconciliation/health', 'GET');
$healthRequest->setUserResolver(fn () => $user);
$summaryRequest = Request::create('/api/v1/financial-reconciliation/summary', 'GET');
$summaryRequest->setUserResolver(fn () => $user);

$before = [
    'runs_by_execution_status' => [
        'running' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count(),
        'succeeded' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'succeeded')->count(),
        'failed' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'failed')->count(),
    ],
    'last_successful_run_id' => DB::table('financial_reconciliation_runs')->where('execution_status', 'succeeded')->orderByDesc('id')->value('id'),
    'last_failed_run_id' => DB::table('financial_reconciliation_runs')->where('execution_status', 'failed')->orderByDesc('id')->value('id'),
    'summary' => $controller->summary()->getData(true)['data'],
    'health' => $controller->health()->getData(true)['data'],
];

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-execution-observability.batch6.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$today = now()->toDateString();
\Illuminate\Support\Facades\Artisan::call('finance:reconcile-daily', ['--run-date' => $today, '--out-file' => 'reports/financial-reliability/reconciliation-batch6-success.json']);
\Illuminate\Support\Facades\Artisan::call('finance:reconcile-daily', ['--run-date' => now()->addDay()->toDateString(), '--out-file' => 'reports/financial-reliability/reconciliation-batch6-failed.json', '--simulate-failure' => true]);
$failedRunId = (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'failed')->orderByDesc('id')->value('id');
if ($failedRunId) {
    DB::table('financial_reconciliation_runs')->where('id', $failedRunId)->update([
        'updated_at' => now()->subDays(3),
        'completed_at' => now()->subDays(3),
    ]);
}

// Force stale warning then critical by shifting latest successful run completion.
$latestSuccessId = (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'succeeded')->orderByDesc('id')->value('id');
if ($latestSuccessId) {
    DB::table('financial_reconciliation_runs')->where('id', $latestSuccessId)->update([
        'completed_at' => now()->subHours(31),
        'updated_at' => now(),
    ]);
}
$healthWarning = $controller->health()->getData(true)['data'];

if ($latestSuccessId) {
    DB::table('financial_reconciliation_runs')->where('id', $latestSuccessId)->update([
        'completed_at' => now()->subHours(55),
        'updated_at' => now(),
    ]);
}
$healthCritical = $controller->health()->getData(true)['data'];

$after = [
    'runs_by_execution_status' => [
        'running' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count(),
        'succeeded' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'succeeded')->count(),
        'failed' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'failed')->count(),
    ],
    'last_successful_run_id' => DB::table('financial_reconciliation_runs')->where('execution_status', 'succeeded')->orderByDesc('id')->value('id'),
    'last_failed_run_id' => DB::table('financial_reconciliation_runs')->where('execution_status', 'failed')->orderByDesc('id')->value('id'),
    'summary' => $controller->summary()->getData(true)['data'],
    'health_after_stale_warning' => $healthWarning,
    'health_after_stale_critical' => $healthCritical,
];

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-execution-observability.batch6.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'B6_BEFORE_HEALTH='.$before['health']['latest_reconciliation_health'].PHP_EOL;
echo 'B6_AFTER_WARNING_HEALTH='.$healthWarning['latest_reconciliation_health'].PHP_EOL;
echo 'B6_AFTER_CRITICAL_HEALTH='.$healthCritical['latest_reconciliation_health'].PHP_EOL;
