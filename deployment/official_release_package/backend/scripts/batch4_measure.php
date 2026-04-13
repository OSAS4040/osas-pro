<?php

use App\Http\Controllers\Api\V1\FinancialReconciliationController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$before = [
    'findings_with_review_history' => (int) DB::table('financial_reconciliation_finding_histories')
        ->distinct('finding_id')
        ->count('finding_id'),
    'status_changes_logged' => (int) DB::table('financial_reconciliation_finding_histories')->count(),
    'rejected_due_note_requirement' => 0,
    'state_history_mismatches' => (int) DB::selectOne("
        SELECT COUNT(*) c
        FROM financial_reconciliation_findings f
        JOIN (
            SELECT finding_id, MAX(id) AS max_id
            FROM financial_reconciliation_finding_histories
            GROUP BY finding_id
        ) hmax ON hmax.finding_id = f.id
        JOIN financial_reconciliation_finding_histories h ON h.id = hmax.max_id
        WHERE f.status <> h.new_status
    ")->c,
];

if (! is_dir(base_path('reports/financial-reliability'))) {
    mkdir(base_path('reports/financial-reliability'), 0777, true);
}

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-review-audit.batch4.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$findingId = (int) DB::table('financial_reconciliation_findings')->where('status', 'open')->value('id');

if (! $findingId) {
    $runId = (int) DB::table('financial_reconciliation_runs')->value('id');
    if (! $runId) {
        $runId = (int) DB::table('financial_reconciliation_runs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'run_type' => 'daily',
            'run_date' => now()->toDateString(),
            'executed_at' => now(),
            'artifact_path' => 'reports/financial-reliability/reconciliation-report.json',
            'detected_cases' => 1,
            'healthy_cases' => 0,
            'invoice_without_ledger_count' => 0,
            'unbalanced_journal_entry_count' => 0,
            'anomalous_reversal_settlement_count' => 1,
            'trace_id' => (string) Str::uuid(),
            'meta' => json_encode(['seed' => 'batch4-measurement']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $findingId = (int) DB::table('financial_reconciliation_findings')->insertGetId([
        'run_id' => $runId,
        'finding_type' => 'anomalous_reversal_settlement',
        'status' => 'open',
        'company_id' => 1,
        'details' => json_encode(['source' => 'batch4-measurement']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

$controller = app(FinancialReconciliationController::class);
$user = User::first();
$rejected = 0;
app()->instance('trace_id', (string) Str::uuid());

$r1 = Request::create("/api/v1/financial-reconciliation/findings/{$findingId}/status", 'PATCH', ['status' => 'resolved']);
$r1->setUserResolver(function () use ($user) {
    return $user;
});
if ($controller->updateFindingStatus($r1, $findingId)->getStatusCode() === 422) {
    $rejected++;
}

$r2 = Request::create("/api/v1/financial-reconciliation/findings/{$findingId}/status", 'PATCH', ['status' => 'false_positive']);
$r2->setUserResolver(function () use ($user) {
    return $user;
});
if ($controller->updateFindingStatus($r2, $findingId)->getStatusCode() === 422) {
    $rejected++;
}

$r3 = Request::create("/api/v1/financial-reconciliation/findings/{$findingId}/status", 'PATCH', ['status' => 'acknowledged']);
$r3->setUserResolver(function () use ($user) {
    return $user;
});
$ackStatus = $controller->updateFindingStatus($r3, $findingId)->getStatusCode();

$after = [
    'findings_with_review_history' => (int) DB::table('financial_reconciliation_finding_histories')
        ->distinct('finding_id')
        ->count('finding_id'),
    'status_changes_logged' => (int) DB::table('financial_reconciliation_finding_histories')->count(),
    'rejected_due_note_requirement' => $rejected,
    'acknowledged_without_note_http_status' => $ackStatus,
    'state_history_mismatches' => (int) DB::selectOne("
        SELECT COUNT(*) c
        FROM financial_reconciliation_findings f
        JOIN (
            SELECT finding_id, MAX(id) AS max_id
            FROM financial_reconciliation_finding_histories
            GROUP BY finding_id
        ) hmax ON hmax.finding_id = f.id
        JOIN financial_reconciliation_finding_histories h ON h.id = hmax.max_id
        WHERE f.status <> h.new_status
    ")->c,
];

file_put_contents(
    base_path('reports/financial-reliability/reconciliation-review-audit.batch4.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "B4_FINDING_ID={$findingId}\n";
echo "B4_REJECTED={$rejected}\n";
echo "B4_ACK_STATUS={$ackStatus}\n";
