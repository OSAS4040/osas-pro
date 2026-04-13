<?php

declare(strict_types=1);

namespace App\Reporting\Queries;

use App\Reporting\ReportingContext;
use App\Reporting\ReportingDateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unified operational feed across work orders, invoices, payments, and support tickets (read-only).
 */
final class GlobalOperationsFeedQuery
{
    private const FEED_TYPES = ['work_order', 'invoice', 'payment', 'ticket'];

    /**
     * @param  array{
     *   types?: list<string>,
     *   statuses?: list<string>,
     *   attention_level?: string|null,
     *   page: int,
     *   per_page: int,
     *   include_financial: bool
     * }  $feedFilters
     * @return array{items: list<array<string, mixed>>, total: int, summary: array<string, int>}
     */
    public function execute(
        ReportingContext $context,
        ReportingDateRange $range,
        array $feedFilters,
        bool $financialVisible,
    ): array {
        $companyId = $context->companyId;
        $branchIds = $context->branchIds;
        $customerId = $context->customerId;
        $subjectUserId = $context->subjectUserId;

        $types = $this->normalizeTypes($feedFilters['types'] ?? null, $financialVisible && ($feedFilters['include_financial'] ?? true));
        $statuses = $feedFilters['statuses'] ?? [];
        $statuses = is_array($statuses) ? array_values(array_filter(array_map('strval', $statuses))) : [];
        $attention = isset($feedFilters['attention_level']) && is_string($feedFilters['attention_level'])
            ? $feedFilters['attention_level']
            : null;
        $page = max(1, (int) ($feedFilters['page'] ?? 1));
        $perPage = (int) ($feedFilters['per_page'] ?? config('reporting.global_feed_default_per_page', 25));
        $maxPer = (int) config('reporting.global_feed_max_per_page', 100);
        $perPage = max(1, min($maxPer, $perPage));
        $offset = ($page - 1) * $perPage;

        $parts = [];
        if (in_array('work_order', $types, true)) {
            $parts[] = $this->workOrderPart($companyId, $range, $branchIds, $customerId, $subjectUserId, $statuses);
        }
        if (in_array('invoice', $types, true)) {
            $parts[] = $this->invoicePart($companyId, $range, $branchIds, $customerId, $subjectUserId, $statuses, $financialVisible && ($feedFilters['include_financial'] ?? true));
        }
        if ($financialVisible && ($feedFilters['include_financial'] ?? true) && in_array('payment', $types, true)) {
            $parts[] = $this->paymentPart($companyId, $range, $branchIds, $customerId, $subjectUserId, $statuses);
        }
        if (in_array('ticket', $types, true) && Schema::hasTable('support_tickets')) {
            $parts[] = $this->ticketPart($companyId, $range, $branchIds, $customerId, $subjectUserId, $statuses);
        }

        if ($parts === []) {
            return [
                'items'   => [],
                'total'   => 0,
                'summary' => $this->emptySummary(),
            ];
        }

        $union = $parts[0];
        for ($i = 1; $i < count($parts); $i++) {
            $union = $union->unionAll($parts[$i]);
        }

        $base = DB::query()->fromSub($union, 'f');
        if ($attention !== null && $attention !== '') {
            $base->where('f.attention_level', '=', $attention);
        }

        $total = (int) (clone $base)->count();
        $summary = $this->aggregateSummary(clone $base, $total);

        $rows = $base
            ->leftJoin('companies as co', function ($j): void {
                $j->on('co.id', '=', 'f.company_id')->whereNull('co.deleted_at');
            })
            ->leftJoin('branches as br', function ($j): void {
                $j->on('br.id', '=', 'f.branch_id')->whereNull('br.deleted_at');
            })
            ->leftJoin('customers as cu', function ($j): void {
                $j->on('cu.id', '=', 'f.customer_id')->whereNull('cu.deleted_at');
            })
            ->leftJoin('users as act', function ($j): void {
                $j->on('act.id', '=', 'f.actor_user_id')->whereNull('act.deleted_at');
            })
            ->orderByDesc('f.occurred_at')
            ->orderByDesc('f.entity_id')
            ->limit($perPage)
            ->offset($offset)
            ->select([
                'f.*',
                'co.name as company_name',
                'br.name as branch_name',
                'cu.name as customer_name',
                'act.name as actor_name',
            ])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'items'   => $rows,
            'total'   => $total,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function aggregateSummary(Builder $base, int $total): array
    {
        $byType = (clone $base)
            ->selectRaw('f.feed_type as ft, COUNT(*) as c')
            ->groupBy('f.feed_type')
            ->pluck('c', 'ft')
            ->all();

        $attention = (int) (clone $base)
            ->whereIn('f.attention_level', ['important', 'critical'])
            ->count();

        return [
            'total_items_in_window' => $total,
            'work_orders_count'     => (int) ($byType['work_order'] ?? 0),
            'invoices_count'        => (int) ($byType['invoice'] ?? 0),
            'payments_count'        => (int) ($byType['payment'] ?? 0),
            'tickets_count'         => (int) ($byType['ticket'] ?? 0),
            'attention_count'       => $attention,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'total_items_in_window' => 0,
            'work_orders_count'     => 0,
            'invoices_count'        => 0,
            'payments_count'        => 0,
            'tickets_count'         => 0,
            'attention_count'       => 0,
        ];
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    private function normalizeTypes(?array $requested, bool $includePaymentType): array
    {
        $all = self::FEED_TYPES;
        if (! $includePaymentType) {
            $all = array_values(array_filter($all, fn ($t) => $t !== 'payment'));
        }
        if ($requested === null || $requested === []) {
            return $all;
        }
        $clean = [];
        foreach ($requested as $t) {
            $t = strtolower((string) $t);
            if (in_array($t, self::FEED_TYPES, true) && ($t !== 'payment' || $includePaymentType)) {
                $clean[] = $t;
            }
        }

        return $clean !== [] ? array_values(array_unique($clean)) : $all;
    }

    /**
     * @param  list<int>|null  $branchIds
     * @param  list<string>  $statuses
     */
    private function workOrderPart(
        int $companyId,
        ReportingDateRange $range,
        ?array $branchIds,
        ?int $customerId,
        ?int $subjectUserId,
        array $statuses,
    ): Builder {
        $q = DB::table('work_orders as wo')
            ->where('wo.company_id', $companyId)
            ->whereNull('wo.deleted_at')
            ->whereBetween('wo.created_at', [$range->startsAt, $range->endsAt])
            ->selectRaw("'work_order' as feed_type")
            ->selectRaw('wo.id as entity_id')
            ->selectRaw('wo.created_at as occurred_at')
            ->selectRaw('wo.company_id')
            ->selectRaw('wo.branch_id')
            ->selectRaw('wo.customer_id')
            ->selectRaw('wo.status as status')
            ->selectRaw('wo.order_number as reference')
            ->selectRaw('CAST(NULL AS DECIMAL(14,4)) as amount')
            ->selectRaw('CAST(NULL AS VARCHAR(8)) as currency')
            ->selectRaw('wo.created_by_user_id as actor_user_id')
            ->selectRaw("CASE WHEN wo.status::text IN ('cancellation_requested','on_hold') THEN 'watch' ELSE 'normal' END as attention_level")
            ->selectRaw('CAST(NULL AS BIGINT) as link_id');
        $this->applyBranch($q, 'wo.branch_id', $branchIds);
        if ($customerId !== null) {
            $q->where('wo.customer_id', $customerId);
        }
        if ($subjectUserId !== null) {
            $q->where(function ($w) use ($subjectUserId): void {
                $w->where('wo.created_by_user_id', $subjectUserId)
                    ->orWhere('wo.assigned_technician_id', $subjectUserId);
            });
        }
        if ($statuses !== []) {
            $q->whereIn('wo.status', $statuses);
        }

        return $q;
    }

    /**
     * @param  list<int>|null  $branchIds
     * @param  list<string>  $statuses
     */
    private function invoicePart(
        int $companyId,
        ReportingDateRange $range,
        ?array $branchIds,
        ?int $customerId,
        ?int $subjectUserId,
        array $statuses,
        bool $showAmounts,
    ): Builder {
        $amountExpr = $showAmounts ? 'i.total' : 'CAST(NULL AS DECIMAL(14,4))';
        $currExpr = $showAmounts ? 'i.currency' : 'CAST(NULL AS VARCHAR(8))';

        $q = DB::table('invoices as i')
            ->where('i.company_id', $companyId)
            ->whereNull('i.deleted_at')
            ->whereRaw('COALESCE(i.issued_at, i.created_at) BETWEEN ? AND ?', [$range->startsAt, $range->endsAt])
            ->selectRaw("'invoice' as feed_type")
            ->selectRaw('i.id as entity_id')
            ->selectRaw('COALESCE(i.issued_at, i.created_at) as occurred_at')
            ->selectRaw('i.company_id')
            ->selectRaw('i.branch_id')
            ->selectRaw('i.customer_id')
            ->selectRaw('i.status as status')
            ->selectRaw('i.invoice_number as reference')
            ->selectRaw("{$amountExpr} as amount")
            ->selectRaw("{$currExpr} as currency")
            ->selectRaw('i.created_by_user_id as actor_user_id')
            ->selectRaw("CASE
                WHEN i.due_amount > 0 AND i.status NOT IN ('paid','cancelled','void')
                    AND (i.due_at IS NOT NULL AND i.due_at < CURRENT_TIMESTAMP) THEN 'important'
                WHEN i.due_amount > 0 AND i.status NOT IN ('paid','cancelled','void') THEN 'watch'
                ELSE 'normal' END as attention_level")
            ->selectRaw('CAST(NULL AS BIGINT) as link_id');
        $this->applyBranch($q, 'i.branch_id', $branchIds);
        if ($customerId !== null) {
            $q->where('i.customer_id', $customerId);
        }
        if ($subjectUserId !== null) {
            $q->where('i.created_by_user_id', $subjectUserId);
        }
        if ($statuses !== []) {
            $q->whereIn('i.status', $statuses);
        }

        return $q;
    }

    /**
     * @param  list<int>|null  $branchIds
     * @param  list<string>  $statuses
     */
    private function paymentPart(
        int $companyId,
        ReportingDateRange $range,
        ?array $branchIds,
        ?int $customerId,
        ?int $subjectUserId,
        array $statuses,
    ): Builder {
        $q = DB::table('payments as p')
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where('p.company_id', $companyId)
            ->whereBetween('p.created_at', [$range->startsAt, $range->endsAt])
            ->whereNull('i.deleted_at')
            ->selectRaw("'payment' as feed_type")
            ->selectRaw('p.id as entity_id')
            ->selectRaw('p.created_at as occurred_at')
            ->selectRaw('p.company_id')
            ->selectRaw('p.branch_id')
            ->selectRaw('i.customer_id as customer_id')
            ->selectRaw('p.status as status')
            ->selectRaw("COALESCE(p.reference, CAST(p.method AS VARCHAR(120)), 'payment') as reference")
            ->selectRaw('p.amount as amount')
            ->selectRaw('p.currency as currency')
            ->selectRaw('p.created_by_user_id as actor_user_id')
            ->selectRaw("'normal' as attention_level")
            ->selectRaw('p.invoice_id as link_id');
        $this->applyBranch($q, 'p.branch_id', $branchIds);
        if ($customerId !== null) {
            $q->where('i.customer_id', $customerId);
        }
        if ($subjectUserId !== null) {
            $q->where('p.created_by_user_id', $subjectUserId);
        }
        if ($statuses !== []) {
            $q->whereIn('p.status', $statuses);
        }

        return $q;
    }

    /**
     * @param  list<int>|null  $branchIds
     * @param  list<string>  $statuses
     */
    private function ticketPart(
        int $companyId,
        ReportingDateRange $range,
        ?array $branchIds,
        ?int $customerId,
        ?int $subjectUserId,
        array $statuses,
    ): Builder {
        $q = DB::table('support_tickets as t')
            ->where('t.company_id', $companyId)
            ->whereNull('t.deleted_at')
            ->whereRaw('COALESCE(t.updated_at, t.created_at) BETWEEN ? AND ?', [$range->startsAt, $range->endsAt])
            ->selectRaw("'ticket' as feed_type")
            ->selectRaw('t.id as entity_id')
            ->selectRaw('COALESCE(t.updated_at, t.created_at) as occurred_at')
            ->selectRaw('t.company_id')
            ->selectRaw('t.branch_id')
            ->selectRaw('t.customer_id')
            ->selectRaw('t.status as status')
            ->selectRaw('t.ticket_number as reference')
            ->selectRaw('CAST(NULL AS DECIMAL(14,4)) as amount')
            ->selectRaw('CAST(NULL AS VARCHAR(8)) as currency')
            ->selectRaw('t.created_by as actor_user_id')
            ->selectRaw("CASE
                WHEN t.sla_due_at IS NOT NULL AND t.sla_due_at < CURRENT_TIMESTAMP
                    AND t.status NOT IN ('resolved','closed') THEN 'important'
                WHEN t.status = 'escalated' THEN 'watch'
                ELSE 'normal' END as attention_level")
            ->selectRaw('CAST(NULL AS BIGINT) as link_id');
        $this->applyBranch($q, 't.branch_id', $branchIds);
        if ($customerId !== null) {
            $q->where('t.customer_id', $customerId);
        }
        if ($subjectUserId !== null) {
            $q->where(function ($w) use ($subjectUserId): void {
                $w->where('t.created_by', $subjectUserId)
                    ->orWhere('t.assigned_to', $subjectUserId);
            });
        }
        if ($statuses !== []) {
            $q->whereIn('t.status', $statuses);
        }

        return $q;
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function applyBranch(Builder $q, string $column, ?array $branchIds): void
    {
        if ($branchIds !== null) {
            $q->whereIn($column, $branchIds);
        }
    }
}
