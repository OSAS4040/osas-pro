<?php

declare(strict_types=1);

namespace App\Reporting\Queries;

use App\Reporting\ReportingContext;
use App\Reporting\ReportingDateRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company-scoped read aggregates (caller must enforce permissions + tenant).
 */
final class CompanyPulseSummaryQuery
{
    /**
     * @return array{summary: array<string, int|float|bool>, breakdown: array<string, mixed>, meta: array<string, mixed>}
     */
    public function execute(ReportingContext $context, ReportingDateRange $range, bool $includeFinancial): array
    {
        $companyId = $context->companyId;
        $branchIds = $context->branchIds;
        $maxBuckets = (int) config('reporting.platform_max_time_buckets', 32);

        $summary = $this->buildSummary($context, $range, $companyId, $branchIds, $includeFinancial);

        $breakdown = [
            'by_branch'     => $this->breakdownByBranch($companyId, $branchIds, $range, $includeFinancial, $context->customerId),
            'by_status'     => $this->breakdownByStatus($companyId, $branchIds, $range, $includeFinancial, $context->customerId),
            'by_activity'   => $this->breakdownByActivity($companyId, $branchIds, $range, $context->customerId),
            'by_time_period'=> [
                'granularity' => 'week',
                'work_orders' => $this->weeklySeries('work_orders', 'created_at', $companyId, $branchIds, $range, $maxBuckets, $context->customerId),
                'invoices'    => $includeFinancial
                    ? $this->weeklySeries('invoices', 'issued_at', $companyId, $branchIds, $range, $maxBuckets, $context->customerId)
                    : [],
            ],
        ];

        $meta = [
            'financial_metrics_included' => $includeFinancial,
        ];

        return compact('summary', 'breakdown', 'meta');
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, int|float|bool>
     */
    private function buildSummary(
        ReportingContext $context,
        ReportingDateRange $range,
        int $companyId,
        ?array $branchIds,
        bool $includeFinancial,
    ): array {
        $customerId = $context->customerId;

        $usersQ = DB::table('users')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');
        $this->applyBranchFilter($usersQ, 'branch_id', $branchIds);

        $customersQ = DB::table('customers')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');
        $this->applyBranchFilter($customersQ, 'branch_id', $branchIds);

        $branchesQ = DB::table('branches')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');
        if ($branchIds !== null) {
            $branchesQ->whereIn('id', $branchIds);
        }

        $woPeriod = DB::table('work_orders')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->startsAt, $range->endsAt]);
        $this->applyBranchFilter($woPeriod, 'branch_id', $branchIds);
        if ($customerId !== null) {
            $woPeriod->where('customer_id', $customerId);
        }

        $invoicesPeriod = 0;
        $paymentsPeriod = 0;
        if ($includeFinancial) {
            $invQ = DB::table('invoices')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereBetween('issued_at', [$range->startsAt, $range->endsAt]);
            $this->applyBranchFilter($invQ, 'branch_id', $branchIds);
            if ($customerId !== null) {
                $invQ->where('customer_id', $customerId);
            }
            $invoicesPeriod = (int) $invQ->count();

            $payQ = DB::table('payments')
                ->where('company_id', $companyId)
                ->whereBetween('created_at', [$range->startsAt, $range->endsAt]);
            $this->applyBranchFilter($payQ, 'branch_id', $branchIds);
            if ($customerId !== null) {
                $payQ->whereExists(function ($sub) use ($customerId, $companyId): void {
                    $sub->selectRaw('1')
                        ->from('invoices as i')
                        ->whereColumn('i.id', 'payments.invoice_id')
                        ->where('i.company_id', $companyId)
                        ->where('i.customer_id', $customerId);
                });
            }
            $paymentsPeriod = (int) $payQ->count();
        }

        $ticketsOpen = 0;
        $ticketsOverdue = 0;
        if (Schema::hasTable('support_tickets')) {
            $tOpen = DB::table('support_tickets')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['resolved', 'closed']);
            $this->applyBranchFilter($tOpen, 'branch_id', $branchIds);
            $ticketsOpen = (int) $tOpen->count();

            $tOver = DB::table('support_tickets')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereNotNull('sla_due_at')
                ->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed']);
            $this->applyBranchFilter($tOver, 'branch_id', $branchIds);
            $ticketsOverdue = (int) $tOver->count();
        }

        return [
            'users_total'            => (int) $usersQ->count(),
            'customers_total'        => (int) $customersQ->count(),
            'branches_total'         => (int) $branchesQ->count(),
            'work_orders_in_period'  => (int) $woPeriod->count(),
            'invoices_in_period'     => $includeFinancial ? $invoicesPeriod : 0,
            'payments_in_period'     => $includeFinancial ? $paymentsPeriod : 0,
            'tickets_open'           => $ticketsOpen,
            'tickets_overdue'        => $ticketsOverdue,
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
     * @return list<array<string, int>>
     */
    private function breakdownByBranch(
        int $companyId,
        ?array $branchIds,
        ReportingDateRange $range,
        bool $includeFinancial,
        ?int $customerId,
    ): array {
        $allowed = $this->allowedBranchIdsList($companyId, $branchIds);
        if ($allowed === []) {
            return [];
        }

        $out = [];
        foreach ($allowed as $bid) {
            $wo = (int) DB::table('work_orders')
                ->where('company_id', $companyId)
                ->where('branch_id', $bid)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$range->startsAt, $range->endsAt])
                ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
                ->count();

            $inv = 0;
            if ($includeFinancial) {
                $inv = (int) DB::table('invoices')
                    ->where('company_id', $companyId)
                    ->where('branch_id', $bid)
                    ->whereNull('deleted_at')
                    ->whereBetween('issued_at', [$range->startsAt, $range->endsAt])
                    ->when($customerId !== null, fn ($q) => $q->where('customer_id', $customerId))
                    ->count();
            }

            $out[] = [
                'branch_id'               => (int) $bid,
                'work_orders_in_period'   => $wo,
                'invoices_in_period'      => $inv,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<int>
     */
    private function allowedBranchIdsList(int $companyId, ?array $branchIds): array
    {
        if ($branchIds !== null) {
            return array_values(array_unique($branchIds));
        }

        return DB::table('branches')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>
     */
    private function breakdownByStatus(
        int $companyId,
        ?array $branchIds,
        ReportingDateRange $range,
        bool $includeFinancial,
        ?int $customerId,
    ): array {
        $wo = $this->statusCounts(
            'work_orders',
            'status',
            'created_at',
            $companyId,
            $branchIds,
            $range,
            true,
            $customerId,
        );

        $inv = $includeFinancial
            ? $this->statusCounts(
                'invoices',
                'status',
                'issued_at',
                $companyId,
                $branchIds,
                $range,
                true,
                $customerId,
            )
            : [];

        $tickets = [];
        if (Schema::hasTable('support_tickets')) {
            $tq = DB::table('support_tickets')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->selectRaw('status as k, COUNT(*) as c')
                ->groupBy('status')
                ->orderBy('status');
            $this->applyBranchFilter($tq, 'branch_id', $branchIds);
            $tickets = $tq->get()->map(fn ($r) => ['status' => (string) $r->k, 'count' => (int) $r->c])->all();
        }

        return [
            'work_orders'   => $wo,
            'invoices'      => $inv,
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
        ?array $branchIds,
        ReportingDateRange $range,
        bool $softDelete,
        ?int $customerId,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }
        $q = DB::table($table)
            ->where('company_id', $companyId)
            ->whereBetween($dateCol, [$range->startsAt, $range->endsAt])
            ->selectRaw($statusCol.' as k, COUNT(*) as c')
            ->groupBy($statusCol);
        if ($softDelete && Schema::hasColumn($table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        $this->applyBranchFilter($q, 'branch_id', $branchIds);
        if ($customerId !== null && Schema::hasColumn($table, 'customer_id')) {
            $q->where('customer_id', $customerId);
        }

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
        ?array $branchIds,
        ReportingDateRange $range,
        ?int $customerId,
    ): array {
        $usersNew = DB::table('users')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->startsAt, $range->endsAt]);
        $this->applyBranchFilter($usersNew, 'branch_id', $branchIds);

        $custNew = DB::table('customers')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->startsAt, $range->endsAt]);
        $this->applyBranchFilter($custNew, 'branch_id', $branchIds);

        $woNew = DB::table('work_orders')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->startsAt, $range->endsAt]);
        $this->applyBranchFilter($woNew, 'branch_id', $branchIds);
        if ($customerId !== null) {
            $woNew->where('customer_id', $customerId);
        }

        return [
            'users_created_in_period'     => (int) $usersNew->count(),
            'customers_created_in_period' => (int) $custNew->count(),
            'work_orders_created_in_period' => (int) $woNew->count(),
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
        ?array $branchIds,
        ReportingDateRange $range,
        int $maxBuckets,
        ?int $customerId,
    ): array {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $deleted = Schema::hasColumn($table, 'deleted_at');
        $sql = "SELECT date_trunc('week', \"{$dateColumn}\") AS bucket, COUNT(*) AS c FROM {$table} WHERE company_id = ? AND \"{$dateColumn}\" BETWEEN ? AND ?";
        $bindings = [$companyId, $range->startsAt, $range->endsAt];
        if ($deleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($branchIds !== null) {
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $sql .= " AND branch_id IN ({$placeholders})";
            array_push($bindings, ...$branchIds);
        }
        if ($customerId !== null && Schema::hasColumn($table, 'customer_id')) {
            $sql .= ' AND customer_id = ?';
            $bindings[] = $customerId;
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
