<?php

use Illuminate\Support\Facades\DB;

if (! is_dir(base_path('reports/financial-reliability'))) {
    mkdir(base_path('reports/financial-reliability'), 0777, true);
}

$data = [
    'generated_at' => now()->toIso8601String(),
    'runs_total' => (int) DB::table('financial_reconciliation_runs')->count(),
    'runs_by_execution_status' => [
        'running' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count(),
        'succeeded' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'succeeded')->count(),
        'failed' => (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'failed')->count(),
    ],
    'findings_by_status' => [
        'open' => (int) DB::table('financial_reconciliation_findings')->where('status', 'open')->count(),
        'acknowledged' => (int) DB::table('financial_reconciliation_findings')->where('status', 'acknowledged')->count(),
        'resolved' => (int) DB::table('financial_reconciliation_findings')->where('status', 'resolved')->count(),
        'false_positive' => (int) DB::table('financial_reconciliation_findings')->where('status', 'false_positive')->count(),
    ],
    'findings_with_history' => (int) DB::table('financial_reconciliation_finding_histories')
        ->distinct('finding_id')
        ->count('finding_id'),
    'status_changes_logged' => (int) DB::table('financial_reconciliation_finding_histories')->count(),
    'blocked_attempts' => (int) DB::table('financial_reconciliation_run_attempts')->where('attempt_status', 'blocked')->count(),
    'duplicate_active_runs' => (int) DB::selectOne("
        SELECT COUNT(*) c FROM (
            SELECT run_type, run_date, COUNT(*) cnt
            FROM financial_reconciliation_runs
            WHERE execution_status='running'
            GROUP BY run_type, run_date
            HAVING COUNT(*) > 1
        ) t
    ")->c,
    'stuck_runs' => (int) DB::table('financial_reconciliation_runs')
        ->where('execution_status', 'running')
        ->whereRaw("NOW() - started_at > INTERVAL '20 minutes'")
        ->count(),
    'last_successful_run_id' => DB::table('financial_reconciliation_runs')
        ->where('execution_status', 'succeeded')
        ->orderByDesc('completed_at')
        ->value('id'),
    'last_failed_run_id' => DB::table('financial_reconciliation_runs')
        ->where('execution_status', 'failed')
        ->orderByDesc('completed_at')
        ->value('id'),
];

$health = 'healthy';
if (($data['last_failed_run_id'] && ! $data['last_successful_run_id']) || $data['stuck_runs'] > 0) {
    $health = 'critical';
} elseif (($data['findings_by_status']['open'] + $data['findings_by_status']['acknowledged']) > 0) {
    $health = 'warning';
}
$data['derived_health'] = $health;

file_put_contents(
    base_path('reports/financial-reliability/wave3-signoff-gate-final.json'),
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "W3_GATE_HEALTH={$health}\n";
