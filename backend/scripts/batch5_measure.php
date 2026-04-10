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

$summaryRequest = Request::create('/api/v1/financial-reconciliation/summary', 'GET');
$summaryRequest->setUserResolver(fn () => $user);
$healthRequest = Request::create('/api/v1/financial-reconciliation/health', 'GET');
$healthRequest->setUserResolver(fn () => $user);

$beforeSummary = $controller->summary()->getData(true)['data'];
$beforeHealth = $controller->health()->getData(true)['data'];
$before = [
    'summary' => $beforeSummary,
    'health' => $beforeHealth,
    'db_counts' => [
        'runs_visible' => (int) DB::table('financial_reconciliation_runs')->count(),
        'open' => (int) DB::table('financial_reconciliation_findings')->where('status', 'open')->count(),
        'acknowledged' => (int) DB::table('financial_reconciliation_findings')->where('status', 'acknowledged')->count(),
        'resolved' => (int) DB::table('financial_reconciliation_findings')->where('status', 'resolved')->count(),
        'false_positive' => (int) DB::table('financial_reconciliation_findings')->where('status', 'false_positive')->count(),
    ],
];

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-operational-summary.batch5.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$findingId = (int) DB::table('financial_reconciliation_findings')->where('status', 'open')->value('id');
if ($findingId) {
    $updateRequest = Request::create("/api/v1/financial-reconciliation/findings/{$findingId}/status", 'PATCH', [
        'status' => 'resolved',
        'note' => 'batch5 measurement closure',
    ]);
    $updateRequest->setUserResolver(fn () => $user);
    $controller->updateFindingStatus($updateRequest, $findingId);
}

$afterSummary = $controller->summary()->getData(true)['data'];
$afterHealth = $controller->health()->getData(true)['data'];
$after = [
    'summary' => $afterSummary,
    'health' => $afterHealth,
    'db_counts' => [
        'runs_visible' => (int) DB::table('financial_reconciliation_runs')->count(),
        'open' => (int) DB::table('financial_reconciliation_findings')->where('status', 'open')->count(),
        'acknowledged' => (int) DB::table('financial_reconciliation_findings')->where('status', 'acknowledged')->count(),
        'resolved' => (int) DB::table('financial_reconciliation_findings')->where('status', 'resolved')->count(),
        'false_positive' => (int) DB::table('financial_reconciliation_findings')->where('status', 'false_positive')->count(),
    ],
    'consistency_checks' => [
        'summary_open_matches_db' => ((int) ($afterSummary['open_findings'] ?? -1)) === (int) DB::table('financial_reconciliation_findings')->where('status', 'open')->count(),
        'summary_unresolved_matches_db' => ((int) ($afterSummary['unresolved_findings'] ?? -1)) === (int) DB::table('financial_reconciliation_findings')->whereIn('status', ['open', 'acknowledged'])->count(),
    ],
];

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-operational-summary.batch5.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'B5_HEALTH_BEFORE='.$beforeHealth['latest_reconciliation_health'].PHP_EOL;
echo 'B5_HEALTH_AFTER='.$afterHealth['latest_reconciliation_health'].PHP_EOL;
