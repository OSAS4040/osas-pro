<?php

declare(strict_types=1);

namespace App\Customers\Profile;

use App\Reporting\ReportingContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only aggregates for a single customer (tenant + optional branch scope).
 */
final class CustomerProfileQuery
{
    private const ENGAGEMENT_WINDOW_DAYS = 90;

    /**
     * @return array<string, mixed>
     */
    public function fetch(
        ReportingContext $context,
        int $companyId,
        int $customerId,
        CarbonImmutable $engagementStart,
        CarbonImmutable $now,
        bool $includeFinancial,
    ): array {
        $branchIds = $context->branchIds;

        $workOrdersCount = $this->countWorkOrders($companyId, $customerId, $branchIds);
        $invoicesCount = $includeFinancial ? $this->countInvoices($companyId, $customerId, $branchIds) : null;
        $paymentsCount = $includeFinancial ? $this->countPayments($companyId, $customerId, $branchIds) : null;

        $ticketsOpen = 0;
        if (Schema::hasTable('support_tickets')) {
            $ticketsOpen = $this->countOpenTickets($companyId, $customerId, $branchIds);
        }

        $lastWo = $this->lastWorkOrder($companyId, $customerId, $branchIds);
        $lastInv = $includeFinancial ? $this->lastInvoice($companyId, $customerId, $branchIds) : null;
        $lastPay = $includeFinancial ? $this->lastPayment($companyId, $customerId, $branchIds) : null;
        $lastTicket = Schema::hasTable('support_tickets') ? $this->lastTicket($companyId, $customerId, $branchIds) : null;

        $lastActivityAt = $this->maxIsoAmong([
            $lastWo['occurred_at'] ?? null,
            $lastInv['occurred_at'] ?? null,
            $lastPay['occurred_at'] ?? null,
            $lastTicket['occurred_at'] ?? null,
        ]);

        $woInWindow = $this->countWorkOrdersInWindow($companyId, $customerId, $branchIds, $engagementStart, $now);
        $invInWindow = $includeFinancial
            ? $this->countInvoicesInWindow($companyId, $customerId, $branchIds, $engagementStart, $now)
            : 0;

        $overdueInvoices = $includeFinancial ? $this->countOverdueInvoices($companyId, $customerId, $branchIds) : 0;
        $staleUnpaidInvoices = $includeFinancial ? $this->countStaleUnpaidInvoices($companyId, $customerId, $branchIds, $now) : 0;

        $vehiclesCount = $this->countVehicles($companyId, $customerId, $branchIds);
        $branches = $this->branchesForCustomer($companyId, $customerId);
        $assignedUsers = $this->assignedUsers($companyId, $customerId, $branchIds);

        return [
            'work_orders_count' => $workOrdersCount,
            'invoices_count' => $invoicesCount,
            'payments_count' => $paymentsCount,
            'tickets_open' => $ticketsOpen,
            'last_activity_at' => $lastActivityAt,
            'last_work_order' => $lastWo,
            'last_invoice' => $lastInv,
            'last_payment' => $lastPay,
            'last_ticket' => $lastTicket,
            'work_orders_in_window' => $woInWindow,
            'invoices_in_window' => $invInWindow,
            'overdue_invoices' => $overdueInvoices,
            'stale_unpaid_invoices' => $staleUnpaidInvoices,
            'vehicles_count' => $vehiclesCount,
            'branches' => $branches,
            'assigned_users' => $assignedUsers,
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function applyBranchOnWo(Builder $q, ?array $branchIds, string $alias = 'wo'): void
    {
        if ($branchIds !== null) {
            $q->whereIn($alias.'.branch_id', $branchIds);
        }
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function applyBranchOnInv(Builder $q, ?array $branchIds, string $alias = 'i'): void
    {
        if ($branchIds !== null) {
            $q->whereIn($alias.'.branch_id', $branchIds);
        }
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countWorkOrders(int $companyId, int $customerId, ?array $branchIds): int
    {
        $q = DB::table('work_orders as wo')
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customerId)
            ->whereNull('wo.deleted_at');
        $this->applyBranchOnWo($q, $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countInvoices(int $companyId, int $customerId, ?array $branchIds): int
    {
        $q = DB::table('invoices as i')
            ->where('i.company_id', $companyId)
            ->where('i.customer_id', $customerId)
            ->whereNull('i.deleted_at');
        $this->applyBranchOnInv($q, $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countPayments(int $companyId, int $customerId, ?array $branchIds): int
    {
        $q = DB::table('payments as p')
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where('p.company_id', $companyId)
            ->where('i.customer_id', $customerId);
        $this->applyBranchOnInv($q, $branchIds, 'i');
        if ($branchIds !== null) {
            $q->whereIn('p.branch_id', $branchIds);
        }

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countOpenTickets(int $companyId, int $customerId, ?array $branchIds): int
    {
        $q = DB::table('support_tickets as t')
            ->where('t.company_id', $companyId)
            ->where('t.customer_id', $customerId)
            ->whereNull('t.deleted_at')
            ->whereNotIn('t.status', ['resolved', 'closed']);
        if ($branchIds !== null) {
            $q->whereIn('t.branch_id', $branchIds);
        }

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countWorkOrdersInWindow(int $companyId, int $customerId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $q = DB::table('work_orders as wo')
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customerId)
            ->whereNull('wo.deleted_at')
            ->whereBetween('wo.created_at', [$from, $to]);
        $this->applyBranchOnWo($q, $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countInvoicesInWindow(int $companyId, int $customerId, ?array $branchIds, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $q = DB::table('invoices as i')
            ->where('i.company_id', $companyId)
            ->where('i.customer_id', $customerId)
            ->whereNull('i.deleted_at')
            ->whereBetween(DB::raw('COALESCE(i.issued_at, i.created_at)'), [$from, $to]);
        $this->applyBranchOnInv($q, $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countOverdueInvoices(int $companyId, int $customerId, ?array $branchIds): int
    {
        $q = DB::table('invoices as i')
            ->where('i.company_id', $companyId)
            ->where('i.customer_id', $customerId)
            ->whereNull('i.deleted_at')
            ->where('i.due_amount', '>', 0)
            ->whereNotNull('i.due_at')
            ->where('i.due_at', '<', now());
        $this->applyBranchOnInv($q, $branchIds);

        return (int) $q->count();
    }

    /**
     * Unpaid balance, issued more than 30 days ago, not past due_at only (delayed pay pattern).
     *
     * @param  list<int>|null  $branchIds
     */
    private function countStaleUnpaidInvoices(int $companyId, int $customerId, ?array $branchIds, CarbonImmutable $now): int
    {
        $cut = $now->subDays(30);
        $q = DB::table('invoices as i')
            ->where('i.company_id', $companyId)
            ->where('i.customer_id', $customerId)
            ->whereNull('i.deleted_at')
            ->where('i.due_amount', '>', 0)
            ->where('i.issued_at', '<', $cut)
            ->where(function ($w): void {
                $w->whereNull('i.due_at')->orWhere('i.due_at', '>=', now());
            });
        $this->applyBranchOnInv($q, $branchIds);

        return (int) $q->count();
    }

    /**
     * @param  list<int>|null  $branchIds
     */
    private function countVehicles(int $companyId, int $customerId, ?array $branchIds): int
    {
        $q = DB::table('vehicles as v')
            ->where('v.company_id', $companyId)
            ->where('v.customer_id', $customerId)
            ->whereNull('v.deleted_at');
        if ($branchIds !== null) {
            $q->whereIn('v.branch_id', $branchIds);
        }

        return (int) $q->count();
    }

    /**
     * @return list<array{branch_id: int, branch_name: string}>
     */
    private function branchesForCustomer(int $companyId, int $customerId): array
    {
        $row = DB::table('customers as c')
            ->join('branches as b', 'b.id', '=', 'c.branch_id')
            ->where('c.company_id', $companyId)
            ->where('c.id', $customerId)
            ->whereNull('c.deleted_at')
            ->whereNull('b.deleted_at')
            ->first(['b.id as branch_id', 'b.name as branch_name']);

        if ($row === null) {
            return [];
        }

        return [[
            'branch_id' => (int) $row->branch_id,
            'branch_name' => (string) $row->branch_name,
        ]];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<array{user_id: int, user_name: string, role_hint: string}>
     */
    private function assignedUsers(int $companyId, int $customerId, ?array $branchIds): array
    {
        $created = DB::table('work_orders as wo')
            ->join('users as u', 'u.id', '=', 'wo.created_by_user_id')
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customerId)
            ->whereNull('wo.deleted_at')
            ->whereNull('u.deleted_at');
        $this->applyBranchOnWo($created, $branchIds);
        $createdIds = $created->distinct()->pluck('u.id');

        $assigned = DB::table('work_orders as wo')
            ->join('users as u', 'u.id', '=', 'wo.assigned_technician_id')
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customerId)
            ->whereNull('wo.deleted_at')
            ->whereNotNull('wo.assigned_technician_id')
            ->whereNull('u.deleted_at');
        $this->applyBranchOnWo($assigned, $branchIds);
        $assignedIds = $assigned->distinct()->pluck('u.id');

        $ids = $createdIds->merge($assignedIds)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $users = DB::table('users')
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $users->map(static function ($u) use ($createdIds, $assignedIds): array {
            $hint = 'contributor';
            if ($assignedIds->contains($u->id)) {
                $hint = 'assigned';
            }
            if ($createdIds->contains($u->id) && $assignedIds->contains($u->id)) {
                $hint = 'assigned_and_creator';
            }

            return [
                'user_id' => (int) $u->id,
                'user_name' => (string) $u->name,
                'role_hint' => $hint,
            ];
        })->all();
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastWorkOrder(int $companyId, int $customerId, ?array $branchIds): ?array
    {
        $q = DB::table('work_orders as wo')
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customerId)
            ->whereNull('wo.deleted_at')
            ->orderByDesc('wo.updated_at')
            ->orderByDesc('wo.id');
        $this->applyBranchOnWo($q, $branchIds);
        $row = $q->first(['wo.id', 'wo.order_number', 'wo.status', 'wo.updated_at']);

        return $this->mapLast($row, 'order_number', 'updated_at');
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastInvoice(int $companyId, int $customerId, ?array $branchIds): ?array
    {
        $q = DB::table('invoices as i')
            ->where('i.company_id', $companyId)
            ->where('i.customer_id', $customerId)
            ->whereNull('i.deleted_at')
            ->orderByDesc(DB::raw('COALESCE(i.issued_at, i.created_at)'))
            ->orderByDesc('i.id');
        $this->applyBranchOnInv($q, $branchIds);
        $row = $q->first(['i.id', 'i.invoice_number', 'i.status', 'i.issued_at', 'i.created_at']);
        if ($row === null) {
            return null;
        }
        $at = $row->issued_at ?? $row->created_at;

        return [
            'id' => (int) $row->id,
            'reference' => (string) ($row->invoice_number ?? ''),
            'status' => (string) ($row->status ?? ''),
            'occurred_at' => $at ? CarbonImmutable::parse((string) $at)->toIso8601String() : null,
            'subtitle' => null,
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastPayment(int $companyId, int $customerId, ?array $branchIds): ?array
    {
        $q = DB::table('payments as p')
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where('p.company_id', $companyId)
            ->where('i.customer_id', $customerId)
            ->orderByDesc('p.created_at')
            ->orderByDesc('p.id');
        $this->applyBranchOnInv($q, $branchIds);
        if ($branchIds !== null) {
            $q->whereIn('p.branch_id', $branchIds);
        }
        $row = $q->first(['p.id', 'p.amount', 'p.currency', 'p.created_at', 'i.invoice_number']);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'reference' => (string) ($row->invoice_number ?? ''),
            'status' => 'recorded',
            'occurred_at' => CarbonImmutable::parse((string) $row->created_at)->toIso8601String(),
            'subtitle' => trim((string) $row->amount.' '.(string) $row->currency),
        ];
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return array<string, mixed>|null
     */
    private function lastTicket(int $companyId, int $customerId, ?array $branchIds): ?array
    {
        $q = DB::table('support_tickets as t')
            ->where('t.company_id', $companyId)
            ->where('t.customer_id', $customerId)
            ->whereNull('t.deleted_at')
            ->orderByDesc(DB::raw('COALESCE(t.updated_at, t.created_at)'))
            ->orderByDesc('t.id');
        if ($branchIds !== null) {
            $q->whereIn('t.branch_id', $branchIds);
        }
        $row = $q->first(['t.id', 't.ticket_number', 't.status', 't.subject', 't.updated_at', 't.created_at']);
        if ($row === null) {
            return null;
        }
        $ts = $row->updated_at ?? $row->created_at;

        return [
            'id' => (int) $row->id,
            'reference' => (string) ($row->ticket_number ?? ''),
            'status' => (string) ($row->status ?? ''),
            'occurred_at' => $ts ? CarbonImmutable::parse((string) $ts)->toIso8601String() : null,
            'subtitle' => (string) ($row->subject ?? ''),
        ];
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
    private function mapLast(?object $row, string $refField, string $tsField): ?array
    {
        if ($row === null) {
            return null;
        }
        $ts = $row->{$tsField} ?? null;

        return [
            'id' => (int) $row->id,
            'reference' => (string) ($row->{$refField} ?? ''),
            'status' => (string) ($row->status ?? ''),
            'occurred_at' => $ts ? CarbonImmutable::parse((string) $ts)->toIso8601String() : null,
            'subtitle' => null,
        ];
    }
}
