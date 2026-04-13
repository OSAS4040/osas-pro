<?php

declare(strict_types=1);

namespace App\Reporting\Queries;

use App\Reporting\ReportingContext;
use App\Reporting\ReportingDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-scoped aggregates for the current tenant (caller enforces permissions).
 */
final class CustomerPulseSummaryQuery
{
    /**
     * @return array{summary: array<string, mixed>, breakdown: array<string, mixed>}
     */
    public function execute(ReportingContext $context, ReportingDateRange $range, bool $includeFinancial): array
    {
        $companyId = $context->companyId;
        $customerId = $context->customerId;
        if ($customerId === null) {
            throw new \InvalidArgumentException('Customer reporting requires customer_id on context.');
        }
        $branchIds = $context->branchIds;
        $maxBuckets = (int) config('reporting.platform_max_time_buckets', 32);

        $summary = $this->buildSummary($companyId, $customerId, $branchIds, $range, $includeFinancial);
        $breakdown = [
            'by_status' => $this->breakdownByStatus($companyId, $customerId, $branchIds, $range, $includeFinancial),
            'by_activity' => $this->breakdownByActivity($companyId, $customerId, $branchIds, $range, $includeFinancial),
            'by_time_period' => [
                'granularity' => 'week',
                'work_orders' => $this->weeklySeries('work_orders', 'created_at', $companyId, $customerId, $branchIds, $range, $maxBuckets),
                'invoices'    => $includeFinancial
                    ? $this->weeklySeries('invoices', 'issued_at', $companyId, $customerId, $branchIds, $range, $maxBuckets)
                    : [],
            ],
        ];

        return compact('summary', 'breakdown');
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>
     */
    private function buildSummary(
        int $companyId,
        int $customerId,
        ?array $branchIds,
        ReportingDateRange $range,
        bool $includeFinancial,
    ): array {
        $wo = DB::table('work_orders')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->startsAt, $range->endsAt]);
        $this->applyBranchFilter($wo, 'branch_id', $branchIds);
        $workOrdersInPeriod = (int) $wo->count();

        $invoicesInPeriod = 0;
        $paymentsInPeriod = 0;
        if ($includeFinancial) {
            $inv = DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')
                ->whereBetween('issued_at', [$range->startsAt, $range->endsAt]);
            $this->applyBranchFilter($inv, 'branch_id', $branchIds);
            $invoicesInPeriod = (int) $inv->count();

            $pay = DB::table('payments as p')
                ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
                ->where('p.company_id', $companyId)
                ->where('i.customer_id', $customerId)
                ->whereBetween('p.created_at', [$range->startsAt, $range->endsAt]);
            $this->applyBranchFilterOnAlias($pay, 'p.branch_id', $branchIds);
            $paymentsInPeriod = (int) $pay->count();
        }

        $ticketsOpen = 0;
        $ticketsOverdue = 0;
        if (Schema::hasTable('support_tickets')) {
            $tOpen = DB::table('support_tickets')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['resolved', 'closed']);
            $this->applyBranchFilter($tOpen, 'branch_id', $branchIds);
            $ticketsOpen = (int) $tOpen->count();

            $tOver = DB::table('support_tickets')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed']);
            $this->applyBranchFilter($tOver, 'branch_id', $branchIds);
            $ticketsOverdue = (int) $tOver->count();
        }

        $vehiclesCount = 0;
        if (Schema::hasTable('vehicles')) {
            $v = DB::table('vehicles')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at');
            $this->applyBranchFilter($v, 'branch_id', $branchIds);
            $vehiclesCount = (int) $v->count();
        }

        $lastActivity = $this->resolveLastActivity($companyId, $customerId, $branchIds, $includeFinancial);

        return [
            'work_orders_in_period' => $workOrdersInPeriod,
            'invoices_in_period'    => $invoicesInPeriod,
            'payments_in_period'    => $paymentsInPeriod,
            'tickets_open'         => $ticketsOpen,
            'tickets_overdue'      => $ticketsOverdue,
            'last_activity_at'     => $lastActivity,
            'vehicles_count'       => $vehiclesCount,
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function applyBranchFilter(\Illuminate\Database\Query\Builder $q, string $column, ?array $branchIds): void
    {
        if ($branchIds !== null) {
            $q->whereIn($column, $branchIds);
        }
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function applyBranchFilterOnAlias(\Illuminate\Database\Query\Builder $q, string $column, ?array $branchIds): void
    {
        if ($branchIds !== null) {
            $q->whereIn($column, $branchIds);
        }
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function resolveLastActivity(
        int $companyId,
        int $customerId,
        ?array $branchIds,
        bool $includeFinancial,
    ): ?string {
        $candidates = [];

        $woMax = $this->maxTimestamp('work_orders', $companyId, $customerId, $branchIds, ['created_at', 'updated_at']);
        if ($woMax !== null) {
            $candidates[] = $woMax;
        }

        if ($includeFinancial) {
            $invMax = $this->maxTimestamp('invoices', $companyId, $customerId, $branchIds, ['issued_at', 'updated_at']);
            if ($invMax !== null) {
                $candidates[] = $invMax;
            }

            $payMax = DB::table('payments as p')
                ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
                ->where('p.company_id', $companyId)
                ->where('i.customer_id', $customerId);
            $this->applyBranchFilterOnAlias($payMax, 'p.branch_id', $branchIds);
            $m = $payMax->max('p.created_at');
            if ($m !== null) {
                $candidates[] = (string) $m;
            }
        }

        if (Schema::hasTable('support_tickets')) {
            $t = DB::table('support_tickets')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at');
            $this->applyBranchFilter($t, 'branch_id', $branchIds);
            $tm = $t->max('updated_at');
            if ($tm !== null) {
                $candidates[] = (string) $tm;
            }
        }

        if (Schema::hasTable('vehicles')) {
            foreach (['updated_at', 'created_at'] as $col) {
                if (! Schema::hasColumn('vehicles', $col)) {
                    continue;
                }
                $vq = DB::table('vehicles')
                    ->where('company_id', $companyId)
                    ->where('customer_id', $customerId)
                    ->whereNull('deleted_at');
                $this->applyBranchFilter($vq, 'branch_id', $branchIds);
                $vm = $vq->max($col);
                if ($vm !== null) {
                    $candidates[] = (string) $vm;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        $latest = null;
        foreach ($candidates as $raw) {
            $dt = CarbonImmutable::parse($raw);
            if ($latest === null || $dt->greaterThan($latest)) {
                $latest = $dt;
            }
        }

        return $latest?->toIso8601String();
    }

    /**
     * @param  list<string>  $columns
     * @param  list<int>|null  $branchIds
     */
    private function maxTimestamp(
        string $table,
        int $companyId,
        int $customerId,
        ?array $branchIds,
        array $columns,
    ): ?string {
        if (! Schema::hasTable($table)) {
            return null;
        }
        $maxes = [];
        foreach ($columns as $col) {
            if (! Schema::hasColumn($table, $col)) {
                continue;
            }
            $q = DB::table($table)
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            $this->applyBranchFilter($q, 'branch_id', $branchIds);
            $m = $q->max($col);
            if ($m !== null) {
                $maxes[] = (string) $m;
            }
        }
        if ($maxes === []) {
            return null;
        }

        $latest = null;
        foreach ($maxes as $raw) {
            $dt = CarbonImmutable::parse($raw);
            if ($latest === null || $dt->greaterThan($latest)) {
                $latest = $dt;
            }
        }

        return $latest?->toIso8601String();
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>
     */
    private function breakdownByStatus(
        int $companyId,
        int $customerId,
        ?array $branchIds,
        ReportingDateRange $range,
        bool $includeFinancial,
    ): array {
        $wo = $this->statusCounts('work_orders', 'status', 'created_at', $companyId, $customerId, $branchIds, $range, true);

        $inv = $includeFinancial
            ? $this->statusCounts('invoices', 'status', 'issued_at', $companyId, $customerId, $branchIds, $range, true)
            : [];

        $tickets = [];
        if (Schema::hasTable('support_tickets')) {
            $tq = DB::table('support_tickets')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')
                ->selectRaw('status as k, COUNT(*) as c')
                ->groupBy('status')
                ->orderBy('status');
            $this->applyBranchFilter($tq, 'branch_id', $branchIds);
            $tickets = $tq->get()->map(fn ($r) => ['status' => (string) $r->k, 'count' => (int) $r->c])->all();
        }

        return [
            'work_orders'     => $wo,
            'invoices'        => $inv,
            'support_tickets' => $tickets,
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<array{status: string, count: int}>
     */
    private function statusCounts(
        string $table,
        string $statusCol,
        string $dateCol,
        int $companyId,
        int $customerId,
        ?array $branchIds,
        ReportingDateRange $range,
        bool $softDelete,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }
        $q = DB::table($table)
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereBetween($dateCol, [$range->startsAt, $range->endsAt])
            ->selectRaw($statusCol.' as k, COUNT(*) as c')
            ->groupBy($statusCol);
        if ($softDelete && Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return $q->orderBy('k')->get()->map(fn ($r) => [
            'status' => (string) $r->k,
            'count'  => (int) $r->c,
        ])->all();
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, int>
     */
    private function breakdownByActivity(
        int $companyId,
        int $customerId,
        ?array $branchIds,
        ReportingDateRange $range,
        bool $includeFinancial,
    ): array {
        $wo = DB::table('work_orders')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->startsAt, $range->endsAt]);
        $this->applyBranchFilter($wo, 'branch_id', $branchIds);

        $inv = 0;
        $pay = 0;
        if ($includeFinancial) {
            $iq = DB::table('invoices')
                ->where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')
                ->whereBetween('issued_at', [$range->startsAt, $range->endsAt]);
            $this->applyBranchFilter($iq, 'branch_id', $branchIds);
            $inv = (int) $iq->count();

            $pq = DB::table('payments as p')
                ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
                ->where('p.company_id', $companyId)
                ->where('i.customer_id', $customerId)
                ->whereBetween('p.created_at', [$range->startsAt, $range->endsAt]);
            $this->applyBranchFilterOnAlias($pq, 'p.branch_id', $branchIds);
            $pay = (int) $pq->count();
        }

        return [
            'work_orders_created_in_period' => (int) $wo->count(),
            'invoices_issued_in_period'     => $inv,
            'payments_recorded_in_period'   => $pay,
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<array{period_start: string, count: int}>
     */
    private function weeklySeries(
        string $table,
        string $dateColumn,
        int $companyId,
        int $customerId,
        ?array $branchIds,
        ReportingDateRange $range,
        int $maxBuckets,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $deleted = Schema::hasColumn($table, 'deleted_at');
        $sql = "SELECT date_trunc('week', \"{$dateColumn}\") AS bucket, COUNT(*) AS c FROM {$table} WHERE company_id = ? AND customer_id = ? AND \"{$dateColumn}\" BETWEEN ? AND ?";
        $bindings = [$companyId, $customerId, $range->startsAt, $range->endsAt];
        if ($deleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($branchIds !== null) {
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $sql .= " AND branch_id IN ({$placeholders})";
            array_push($bindings, ...$branchIds);
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
