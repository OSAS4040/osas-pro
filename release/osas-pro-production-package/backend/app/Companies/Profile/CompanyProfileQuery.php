<?php

declare(strict_types=1);

namespace App\Companies\Profile;

use App\Reporting\ReportingContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only aggregates for the company operational profile hub.
 */
final class CompanyProfileQuery
{
    private const WO_ACTIVE_STATUSES = [
        'draft', 'pending_manager_approval', 'approved', 'cancellation_requested', 'in_progress', 'on_hold',
    ];

    /**
     * @return array<string, mixed>
     */
    public function fetch(ReportingContext $context, int $companyId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, bool $includeFinancial): array
    {
        $branchIds = $context->branchIds;

        $usersCount = $this->countUsers($companyId, $branchIds);
        $customersCount = $this->countCustomers($companyId, $branchIds);
        $branchesCount = $this->countBranches($companyId, $branchIds);
        $workOrdersActive = $this->countWorkOrdersActive($companyId, $branchIds);
        $invoicesInPeriod = $includeFinancial
            ? $this->countInvoicesInPeriod($companyId, $branchIds, $periodStart, $periodEnd)
            : 0;
        $workOrdersInPeriod = $this->countWorkOrdersCreatedInPeriod($companyId, $branchIds, $periodStart, $periodEnd);

        $lastWo = $this->lastWorkOrder($companyId, $branchIds);
        $lastInv = $includeFinancial ? $this->lastInvoice($companyId, $branchIds) : null;
        $lastPay = $includeFinancial ? $this->lastPayment($companyId, $branchIds) : null;
        $lastTicket = Schema::hasTable('support_tickets') ? $this->lastTicket($companyId, $branchIds) : null;

        $lastActivityAt = $this->maxIsoAmong([
            $lastWo['occurred_at'] ?? null,
            $lastInv['occurred_at'] ?? null,
            $lastPay['occurred_at'] ?? null,
            $lastTicket['occurred_at'] ?? null,
        ]);

        $openTickets = 0;
        $ticketsOverdue = 0;
        if (Schema::hasTable('support_tickets')) {
            $openTickets = $this->countOpenTickets($companyId, $branchIds);
            $ticketsOverdue = $this->countOverdueTickets($companyId, $branchIds);
        }

        return [
            'users_count' => $usersCount,
            'customers_count' => $customersCount,
            'branches_count' => $branchesCount,
            'work_orders_active' => $workOrdersActive,
            'invoices_in_period' => $invoicesInPeriod,
            'work_orders_in_period' => $workOrdersInPeriod,
            'last_activity_at' => $lastActivityAt,
            'last_work_order' => $lastWo,
            'last_invoice' => $lastInv,
            'last_payment' => $lastPay,
            'last_ticket' => $lastTicket,
            'open_tickets' => $openTickets,
            'tickets_overdue' => $ticketsOverdue,
            'top_customers' => $this->topCustomers($companyId, $branchIds, $periodStart, $periodEnd),
            'top_users' => $this->topUsers($companyId, $branchIds, $periodStart, $periodEnd),
            'branches_summary' => $this->branchesSummary($companyId, $branchIds, $periodStart, $periodEnd),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function applyBranchFilter(Builder $q, string $column, ?array $branchIds): void
    {
        if ($branchIds !== null) {
            $q->whereIn($column, $branchIds);
        }
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countUsers(int $companyId, ?array $branchIds): int
    {
        $q = DB::table('users')->where('company_id', $companyId)->whereNull('deleted_at');
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countCustomers(int $companyId, ?array $branchIds): int
    {
        $q = DB::table('customers')->where('company_id', $companyId)->whereNull('deleted_at');
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countBranches(int $companyId, ?array $branchIds): int
    {
        $q = DB::table('branches')->where('company_id', $companyId)->whereNull('deleted_at');
        if ($branchIds !== null) {
            $q->whereIn('id', $branchIds);
        }

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countWorkOrdersActive(int $companyId, ?array $branchIds): int
    {
        $q = DB::table('work_orders')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('status', self::WO_ACTIVE_STATUSES);
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countInvoicesInPeriod(int $companyId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $q = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereBetween('issued_at', [$from, $to]);
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countWorkOrdersCreatedInPeriod(int $companyId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $q = DB::table('work_orders')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, $to]);
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastWorkOrder(int $companyId, ?array $branchIds): ?array
    {
        $q = DB::table('work_orders as wo')
            ->leftJoin('customers as c', 'c.id', '=', 'wo.customer_id')
            ->where('wo.company_id', $companyId)
            ->whereNull('wo.deleted_at')
            ->orderByDesc('wo.updated_at')
            ->orderByDesc('wo.id');
        $this->applyBranchFilter($q, 'wo.branch_id', $branchIds);
        $row = $q->first(['wo.id', 'wo.order_number', 'wo.status', 'wo.updated_at', 'c.name as customer_name']);

        return $this->mapLastRow($row, 'work_order', 'order_number', 'updated_at', null, null);
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastInvoice(int $companyId, ?array $branchIds): ?array
    {
        $q = DB::table('invoices as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.company_id', $companyId)
            ->whereNull('i.deleted_at')
            ->orderByDesc(DB::raw('COALESCE(i.issued_at, i.created_at)'))
            ->orderByDesc('i.id');
        $this->applyBranchFilter($q, 'i.branch_id', $branchIds);
        $row = $q->first(['i.id', 'i.invoice_number', 'i.status', 'i.issued_at', 'i.created_at', 'c.name as customer_name']);

        if ($row === null) {
            return null;
        }
        $at = $row->issued_at ?? $row->created_at;

        return [
            'id' => (int) $row->id,
            'reference' => (string) ($row->invoice_number ?? ''),
            'status' => (string) ($row->status ?? ''),
            'occurred_at' => $at ? CarbonImmutable::parse((string) $at)->toIso8601String() : null,
            'subtitle' => $row->customer_name ? (string) $row->customer_name : null,
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastPayment(int $companyId, ?array $branchIds): ?array
    {
        $q = DB::table('payments as p')
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where('p.company_id', $companyId)
            ->orderByDesc('p.created_at')
            ->orderByDesc('p.id');
        $this->applyBranchFilter($q, 'p.branch_id', $branchIds);
        $row = $q->first(['p.id', 'p.amount', 'p.currency', 'p.created_at', 'i.invoice_number']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'reference' => (string) ($row->invoice_number ?? ''),
            'status' => 'recorded',
            'occurred_at' => CarbonImmutable::parse((string) $row->created_at)->toIso8601String(),
            'subtitle' => trim((string) ($row->amount ?? '').' '.(string) ($row->currency ?? '')),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastTicket(int $companyId, ?array $branchIds): ?array
    {
        $q = DB::table('support_tickets as t')
            ->where('t.company_id', $companyId)
            ->whereNull('t.deleted_at')
            ->orderByDesc(DB::raw('COALESCE(t.updated_at, t.created_at)'))
            ->orderByDesc('t.id');
        $this->applyBranchFilter($q, 't.branch_id', $branchIds);
        $row = $q->first(['t.id', 't.ticket_number', 't.status', 't.subject', 't.updated_at', 't.created_at']);

        return $this->mapLastRow($row, 'ticket', 'ticket_number', 'updated_at', 'created_at', 'subject');
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<array<string, mixed>>
     */
    private function topCustomers(int $companyId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = DB::table('work_orders as wo')
            ->join('customers as c', 'c.id', '=', 'wo.customer_id')
            ->where('wo.company_id', $companyId)
            ->whereNull('wo.deleted_at')
            ->whereBetween('wo.created_at', [$from, $to])
            ->groupBy('c.id', 'c.name');
        $this->applyBranchFilter($q, 'wo.branch_id', $branchIds);
        $rows = $q->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(5)
            ->get([DB::raw('c.id as customer_id'), DB::raw('c.name as customer_name'), DB::raw('COUNT(*) as work_orders_count')]);

        return $rows->map(static function ($r): array {
            return [
                'customer_id' => (int) $r->customer_id,
                'customer_name' => (string) $r->customer_name,
                'work_orders_count' => (int) $r->work_orders_count,
            ];
        })->all();
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<array<string, mixed>>
     */
    private function topUsers(int $companyId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = DB::table('work_orders as wo')
            ->join('users as u', 'u.id', '=', 'wo.created_by_user_id')
            ->where('wo.company_id', $companyId)
            ->whereNull('wo.deleted_at')
            ->whereBetween('wo.created_at', [$from, $to])
            ->whereNull('u.deleted_at')
            ->groupBy('u.id', 'u.name');
        $this->applyBranchFilter($q, 'wo.branch_id', $branchIds);
        $rows = $q->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(5)
            ->get([DB::raw('u.id as user_id'), DB::raw('u.name as user_name'), DB::raw('COUNT(*) as work_orders_touched')]);

        return $rows->map(static function ($r): array {
            return [
                'user_id' => (int) $r->user_id,
                'user_name' => (string) $r->user_name,
                'work_orders_touched' => (int) $r->work_orders_touched,
            ];
        })->all();
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<array<string, mixed>>
     */
    private function branchesSummary(int $companyId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $q = DB::table('branches as b')
            ->leftJoin('work_orders as wo', function ($join) use ($companyId, $from, $to): void {
                $join->on('wo.branch_id', '=', 'b.id')
                    ->where('wo.company_id', '=', $companyId)
                    ->whereNull('wo.deleted_at')
                    ->whereBetween('wo.created_at', [$from, $to]);
            })
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->groupBy('b.id', 'b.name');
        if ($branchIds !== null) {
            $q->whereIn('b.id', $branchIds);
        }

        return $q->orderBy('b.name')
            ->get([
                'b.id',
                'b.name',
                DB::raw('COUNT(wo.id) as work_orders_in_period'),
            ])
            ->map(static function ($r): array {
                return [
                    'branch_id' => (int) $r->id,
                    'branch_name' => (string) $r->name,
                    'work_orders_in_period' => (int) $r->work_orders_in_period,
                ];
            })->all();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countOpenTickets(int $companyId, ?array $branchIds): int
    {
        $q = DB::table('support_tickets')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['resolved', 'closed']);
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countOverdueTickets(int $companyId, ?array $branchIds): int
    {
        $q = DB::table('support_tickets')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', ['resolved', 'closed']);
        $this->applyBranchFilter($q, 'branch_id', $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<string|null>  $isoList
     */
    private function maxIsoAmong(array $isoList): ?string
    {
        $best = null;
        foreach ($isoList as $iso) {
            if (! is_string($iso) || $iso === '') {
                continue;
            }
            if ($best === null || $iso > $best) {
                $best = $iso;
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapLastRow(
        ?object $row,
        string $kind,
        string $refColumn,
        string $primaryTsColumn,
        ?string $fallbackTsColumn = null,
        ?string $subtitleColumn = null,
    ): ?array {
        if ($row === null) {
            return null;
        }
        $ts = $row->{$primaryTsColumn} ?? null;
        if ($ts === null && $fallbackTsColumn !== null) {
            $ts = $row->{$fallbackTsColumn} ?? null;
        }

        return [
            'id' => (int) $row->id,
            'reference' => (string) ($row->{$refColumn} ?? ''),
            'status' => (string) ($row->status ?? ''),
            'occurred_at' => $ts ? CarbonImmutable::parse((string) $ts)->toIso8601String() : null,
            'subtitle' => $subtitleColumn !== null && isset($row->{$subtitleColumn})
                ? (string) $row->{$subtitleColumn}
                : (isset($row->customer_name) ? (string) $row->customer_name : null),
        ];
    }
}
