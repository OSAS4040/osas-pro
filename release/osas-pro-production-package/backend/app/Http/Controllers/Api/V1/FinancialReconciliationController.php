<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReconciliationController extends Controller
{
    private const RUNBOOK_REF = 'docs/financial-reconciliation-operational-runbook.md';

    public function latest(): JsonResponse
    {
        $run = DB::table('financial_reconciliation_runs')
            ->orderByDesc('run_date')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => $run,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $runs = DB::table('financial_reconciliation_runs')
            ->when($request->filled('from_date'), fn($q) => $q->whereDate('run_date', '>=', $request->string('from_date')->toString()))
            ->when($request->filled('to_date'), fn($q) => $q->whereDate('run_date', '<=', $request->string('to_date')->toString()))
            ->orderByDesc('run_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $runs,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function findings(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $findings = DB::table('financial_reconciliation_findings')
            ->where('company_id', $companyId)
            ->when($request->filled('finding_type'), fn($q) => $q->where('finding_type', $request->string('finding_type')->toString()))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'data' => $findings,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $finding = DB::table('financial_reconciliation_findings')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();
        if (! $finding) {
            return response()->json(['message' => 'Finding not found.', 'trace_id' => app('trace_id')], 404);
        }

        $history = DB::table('financial_reconciliation_finding_histories')
            ->where('finding_id', $id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'finding' => $finding,
                'history' => $history,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function summary(): JsonResponse
    {
        $latestRun = DB::table('financial_reconciliation_runs')->orderByDesc('id')->first();
        $openFindings = (int) DB::table('financial_reconciliation_findings')->where('status', 'open')->count();
        $unresolvedFindings = (int) DB::table('financial_reconciliation_findings')
            ->whereIn('status', ['open', 'acknowledged'])
            ->count();

        $statusRows = DB::table('financial_reconciliation_findings')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        $statusCounts = [
            'open' => 0,
            'acknowledged' => 0,
            'resolved' => 0,
            'false_positive' => 0,
        ];
        foreach ($statusRows as $row) {
            $status = (string) $row->status;
            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = (int) $row->count;
            }
        }

        $byType = DB::table('financial_reconciliation_findings')
            ->select('finding_type', DB::raw('COUNT(*) as count'))
            ->groupBy('finding_type')
            ->get();

        $typeCounts = [
            'invoice_without_ledger' => 0,
            'unbalanced_journal_entry' => 0,
            'anomalous_reversal_settlement' => 0,
        ];
        foreach ($byType as $row) {
            $type = (string) $row->finding_type;
            if (array_key_exists($type, $typeCounts)) {
                $typeCounts[$type] = (int) $row->count;
            }
        }
        $criticalCoreUnresolved = (int) DB::table('financial_reconciliation_findings')
            ->whereIn('status', ['open', 'acknowledged'])
            ->whereIn('finding_type', ['invoice_without_ledger', 'unbalanced_journal_entry'])
            ->count() > 0;

        $aging = [
            '0_1_days' => (int) DB::table('financial_reconciliation_findings')
                ->whereIn('status', ['open', 'acknowledged'])
                ->whereRaw("NOW() - created_at < INTERVAL '2 days'")
                ->count(),
            '2_7_days' => (int) DB::table('financial_reconciliation_findings')
                ->whereIn('status', ['open', 'acknowledged'])
                ->whereRaw("NOW() - created_at >= INTERVAL '2 days'")
                ->whereRaw("NOW() - created_at < INTERVAL '8 days'")
                ->count(),
            '8_plus_days' => (int) DB::table('financial_reconciliation_findings')
                ->whereIn('status', ['open', 'acknowledged'])
                ->whereRaw("NOW() - created_at >= INTERVAL '8 days'")
                ->count(),
        ];

        $lastSuccessfulRun = DB::table('financial_reconciliation_runs')
            ->where('execution_status', 'succeeded')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
        $lastFailedRun = DB::table('financial_reconciliation_runs')
            ->where('execution_status', 'failed')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
        $runsByStatusRows = DB::table('financial_reconciliation_runs')
            ->select('execution_status', DB::raw('COUNT(*) as count'))
            ->groupBy('execution_status')
            ->get();
        $runsByExecutionStatus = ['running' => 0, 'succeeded' => 0, 'failed' => 0];
        foreach ($runsByStatusRows as $row) {
            $key = (string) $row->execution_status;
            if (array_key_exists($key, $runsByExecutionStatus)) {
                $runsByExecutionStatus[$key] = (int) $row->count;
            }
        }
        $runningRunsCount = (int) DB::table('financial_reconciliation_runs')->where('execution_status', 'running')->count();
        $stuckRunsCount = (int) DB::table('financial_reconciliation_runs')
            ->where('execution_status', 'running')
            ->whereRaw("NOW() - started_at > INTERVAL '20 minutes'")
            ->count();
        $latestBlockedAttempt = DB::table('financial_reconciliation_run_attempts')
            ->where('attempt_status', 'blocked')
            ->orderByDesc('id')
            ->first();
        $blockedAttemptsCount = (int) DB::table('financial_reconciliation_run_attempts')
            ->where('attempt_status', 'blocked')
            ->count();

        $stale = $this->computeStaleStatus($lastSuccessfulRun);
        $health = $this->classifyHealth($statusCounts, $typeCounts, $latestRun, $lastSuccessfulRun, $lastFailedRun, $stale['status'], $criticalCoreUnresolved, $stuckRunsCount);

        return response()->json([
            'data' => [
                'latest_run' => $latestRun,
                'open_findings' => $openFindings,
                'unresolved_findings' => $unresolvedFindings,
                'findings_by_type' => $byType,
                'findings_by_status' => $statusCounts,
                'findings_by_type_map' => $typeCounts,
                'unresolved_aging' => $aging,
                'latest_reconciliation_health' => $health,
                'last_successful_run' => $lastSuccessfulRun,
                'last_failed_run' => $lastFailedRun,
                'stale_status' => $stale['status'],
                'hours_since_last_success' => $stale['hours_since_last_success'],
                'runs_by_execution_status' => $runsByExecutionStatus,
                'has_running_run' => $runningRunsCount > 0,
                'running_runs_count' => $runningRunsCount,
                'has_stuck_run' => $stuckRunsCount > 0,
                'stuck_runs_count' => $stuckRunsCount,
                'blocked_concurrent_attempts_count' => $blockedAttemptsCount,
                'latest_blocked_attempt' => $latestBlockedAttempt,
                'concurrent_run_prevention_active' => true,
                'runbook_reference' => self::RUNBOOK_REF,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function health(): JsonResponse
    {
        $summary = $this->summary()->getData(true);

        return response()->json([
            'data' => [
                'latest_reconciliation_health' => $summary['data']['latest_reconciliation_health'] ?? 'warning',
                'latest_run' => $summary['data']['latest_run'] ?? null,
                'last_successful_run' => $summary['data']['last_successful_run'] ?? null,
                'last_failed_run' => $summary['data']['last_failed_run'] ?? null,
                'stale_status' => $summary['data']['stale_status'] ?? 'unknown',
                'hours_since_last_success' => $summary['data']['hours_since_last_success'] ?? null,
                'runs_by_execution_status' => $summary['data']['runs_by_execution_status'] ?? [],
                'has_running_run' => $summary['data']['has_running_run'] ?? false,
                'running_runs_count' => $summary['data']['running_runs_count'] ?? 0,
                'has_stuck_run' => $summary['data']['has_stuck_run'] ?? false,
                'stuck_runs_count' => $summary['data']['stuck_runs_count'] ?? 0,
                'blocked_concurrent_attempts_count' => $summary['data']['blocked_concurrent_attempts_count'] ?? 0,
                'latest_blocked_attempt' => $summary['data']['latest_blocked_attempt'] ?? null,
                'concurrent_run_prevention_active' => $summary['data']['concurrent_run_prevention_active'] ?? true,
                'findings_by_status' => $summary['data']['findings_by_status'] ?? [],
                'findings_by_type_map' => $summary['data']['findings_by_type_map'] ?? [],
                'unresolved_aging' => $summary['data']['unresolved_aging'] ?? [],
                'runbook_reference' => self::RUNBOOK_REF,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function updateFindingStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:open,acknowledged,resolved,false_positive',
            'note' => 'nullable|string|max:255',
        ]);

        $companyId = (int) $request->user()->company_id;
        $finding = DB::table('financial_reconciliation_findings')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();
        if (! $finding) {
            return response()->json(['message' => 'Finding not found.', 'trace_id' => app('trace_id')], 404);
        }

        $current = (string) $finding->status;
        $target = (string) $validated['status'];

        if (in_array($target, ['resolved', 'false_positive'], true) && empty($validated['note'])) {
            return response()->json([
                'message' => "review_note is required when transitioning to {$target}.",
                'trace_id' => app('trace_id'),
            ], 422);
        }

        if (! $this->isAllowedTransition($current, $target)) {
            return response()->json([
                'message' => "Transition from {$current} to {$target} is not allowed.",
                'trace_id' => app('trace_id'),
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
            ], 409);
        }

        DB::table('financial_reconciliation_findings')
            ->where('id', $id)
            ->update([
                'status' => $target,
                'status_updated_at' => now(),
                'status_updated_by_user_id' => $request->user()?->id,
                'status_update_note' => $validated['note'] ?? null,
                'updated_at' => now(),
            ]);

        DB::table('financial_reconciliation_finding_histories')->insert([
            'finding_id' => $id,
            'old_status' => $current,
            'new_status' => $target,
            'changed_by_user_id' => $request->user()?->id,
            'changed_at' => now(),
            'trace_id' => (string) app('trace_id'),
            'review_note' => $validated['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = DB::table('financial_reconciliation_findings')->where('id', $id)->first();

        return response()->json([
            'data' => $updated,
            'trace_id' => app('trace_id'),
        ]);
    }

    private function isAllowedTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $map = [
            'open' => ['acknowledged', 'resolved', 'false_positive'],
            'acknowledged' => ['resolved', 'false_positive'],
            'resolved' => [],
            'false_positive' => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    private function classifyHealth(array $statusCounts, array $typeCounts, ?object $latestRun, ?object $lastSuccessfulRun, ?object $lastFailedRun, string $staleStatus, bool $criticalCoreUnresolved, int $stuckRunsCount): string
    {
        $open = (int) ($statusCounts['open'] ?? 0);
        $ack = (int) ($statusCounts['acknowledged'] ?? 0);
        $unresolved = $open + $ack;

        if ($lastFailedRun && (! $lastSuccessfulRun || strtotime((string) $lastFailedRun->updated_at) >= strtotime((string) $lastSuccessfulRun->updated_at))) {
            return 'critical';
        }

        if ($staleStatus === 'critical') {
            return 'critical';
        }

        if ($stuckRunsCount > 0) {
            return 'critical';
        }

        if ($criticalCoreUnresolved || $unresolved >= 5) {
            return 'critical';
        }

        if ($staleStatus === 'warning' || $unresolved > 0 || ((int) ($latestRun->detected_cases ?? 0) > 0)) {
            return 'warning';
        }

        return 'healthy';
    }

    private function computeStaleStatus(?object $lastSuccessfulRun): array
    {
        if (! $lastSuccessfulRun || empty($lastSuccessfulRun->completed_at)) {
            return ['status' => 'critical', 'hours_since_last_success' => null];
        }

        $hours = Carbon::parse((string) $lastSuccessfulRun->completed_at)->diffInHours(now());
        if ($hours > 48) {
            return ['status' => 'critical', 'hours_since_last_success' => $hours];
        }

        if ($hours > 30) {
            return ['status' => 'warning', 'hours_since_last_success' => $hours];
        }

        return ['status' => 'fresh', 'hours_since_last_success' => $hours];
    }
}
