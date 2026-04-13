<?php

declare(strict_types=1);

namespace App\Reporting\Queries;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Reporting\ReportingDateRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-tenant read aggregates for platform operators only (caller must enforce access).
 * Uses base tables + explicit filters — never relies on request tenant global scopes.
 */
final class PlatformPulseSummaryQuery
{
    /**
     * @return array<string, mixed>
     */
    public function execute(ReportingDateRange $range): array
    {
        $start = $range->startsAt;
        $end = $range->endsAt;
        $maxBuckets = (int) config('reporting.platform_max_time_buckets', 32);

        $summary = $this->buildSummary($start, $end);
        $breakdown = [
            'by_status' => [
                'companies'       => $this->groupCountOnTable('companies', 'status', 'deleted_at'),
                'subscriptions' => $this->subscriptionStatusBreakdown(),
                'support_tickets' => $this->supportTicketStatusBreakdown(),
            ],
            'by_activity' => [
                'companies_registered_in_period' => $this->countCreatedBetween('companies', $start, $end, 'deleted_at'),
                'work_orders_created_in_period'  => $this->countCreatedBetween('work_orders', $start, $end, 'deleted_at'),
            ],
            'by_time_period' => [
                'granularity'   => 'week',
                'work_orders'   => $this->weeklyBuckets('work_orders', $start, $end, $maxBuckets),
                'companies'     => $this->weeklyBuckets('companies', $start, $end, $maxBuckets),
            ],
        ];

        return [
            'summary'   => $summary,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function buildSummary(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array
    {
        $companiesTotal = (int) Company::query()->whereNull('deleted_at')->count();

        $companiesOperational = (int) Company::query()
            ->whereNull('deleted_at')
            ->where('status', CompanyStatus::Active)
            ->where('is_active', true)
            ->count();

        $companiesSuspended = (int) Company::query()
            ->whereNull('deleted_at')
            ->where('status', CompanyStatus::Suspended)
            ->count();

        $companiesOther = max(0, $companiesTotal - $companiesOperational - $companiesSuspended);

        $usersTotal = (int) DB::table('users')
            ->whereNull('deleted_at')
            ->count();

        $customersTotal = (int) $this->safeCountTable('customers', 'deleted_at');
        $branchesTotal = (int) $this->safeCountTable('branches', 'deleted_at');
        $subscriptionsTotal = (int) $this->safeCountTable('subscriptions', null);

        $ticketsOpen = 0;
        $ticketsOverdue = 0;
        if (Schema::hasTable('support_tickets')) {
            $ticketsOpen = (int) DB::table('support_tickets')
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count();

            $ticketsOverdue = (int) DB::table('support_tickets')
                ->whereNull('deleted_at')
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count();
        }

        $workOrdersInPeriod = (int) DB::table('work_orders')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return [
            'companies_total'               => $companiesTotal,
            'companies_operational'        => $companiesOperational,
            'companies_suspended'          => $companiesSuspended,
            'companies_other'               => $companiesOther,
            'users_total'                   => $usersTotal,
            'customers_total'              => $customersTotal,
            'branches_total'               => $branchesTotal,
            'subscriptions_total'         => $subscriptionsTotal,
            'tickets_open'                 => $ticketsOpen,
            'tickets_overdue'              => $ticketsOverdue,
            'work_orders_in_period'        => $workOrdersInPeriod,
        ];
    }

    private function safeCountTable(string $table, ?string $deletedAtColumn): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $q = DB::table($table);
        if ($deletedAtColumn !== null && Schema::hasColumn($table, $deletedAtColumn)) {
            $q->whereNull($deletedAtColumn);
        }

        return (int) $q->count();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function groupCountOnTable(string $table, string $column, ?string $deletedAtColumn): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }
        $q = DB::table($table)->selectRaw($column.' as k, COUNT(*) as c')->groupBy($column);
        if ($deletedAtColumn !== null && Schema::hasColumn($table, $deletedAtColumn)) {
            $q->whereNull($deletedAtColumn);
        }

        return $q->orderBy('k')->get()->map(fn ($r) => [
            'status' => (string) $r->k,
            'count'  => (int) $r->c,
        ])->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function subscriptionStatusBreakdown(): array
    {
        if (! Schema::hasTable('subscriptions')) {
            return [];
        }

        return DB::table('subscriptions')
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->c])
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function supportTicketStatusBreakdown(): array
    {
        if (! Schema::hasTable('support_tickets')) {
            return [];
        }

        return DB::table('support_tickets')
            ->whereNull('deleted_at')
            ->selectRaw('status as k, COUNT(*) as c')
            ->groupBy('status')
            ->orderBy('k')
            ->get()
            ->map(fn ($r) => ['status' => (string) $r->k, 'count' => (int) $r->c])
            ->all();
    }

    private function countCreatedBetween(
        string $table,
        \Carbon\CarbonInterface $start,
        \Carbon\CarbonInterface $end,
        ?string $deletedAtColumn,
    ): int {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $q = DB::table($table)->whereBetween('created_at', [$start, $end]);
        if ($deletedAtColumn !== null && Schema::hasColumn($table, $deletedAtColumn)) {
            $q->whereNull($deletedAtColumn);
        }

        return (int) $q->count();
    }

    /**
     * @return list<array{period_start: string, count: int}>
     */
    private function weeklyBuckets(
        string $table,
        \Carbon\CarbonInterface $start,
        \Carbon\CarbonInterface $end,
        int $maxBuckets,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $deletedCol = Schema::hasColumn($table, 'deleted_at') ? 'deleted_at' : null;

        $sql = "SELECT date_trunc('week', created_at) AS bucket, COUNT(*) AS c FROM {$table} WHERE created_at BETWEEN ? AND ?";
        $bindings = [$start, $end];
        if ($deletedCol !== null) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' GROUP BY 1 ORDER BY 1 ASC';

        $rows = DB::select($sql, $bindings);

        $out = [];
        foreach ($rows as $i => $row) {
            if ($i >= $maxBuckets) {
                break;
            }
            $bucket = $row->bucket ?? null;
            $out[] = [
                'period_start' => $bucket ? (string) $bucket : '',
                'count'        => (int) ($row->c ?? 0),
            ];
        }

        return $out;
    }
}
