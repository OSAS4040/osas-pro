<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPortal\CustomerPortalReportRangeRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OrgUnit;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class CustomerPortalReportsController extends Controller
{
    public function filterOptions(CustomerPortalReportRangeRequest $request): JsonResponse
    {
        [, $customer, $companyId] = $this->requirePortalCustomer($request);
        if ($customer === null) {
            return response()->json([
                'data' => ['services' => [], 'products' => []],
                'trace_id' => app('trace_id'),
            ]);
        }

        $from = Carbon::parse($request->validated('from'))->startOfDay();
        $to = Carbon::parse($request->validated('to'))->endOfDay();
        $orgUnitScope = $this->resolveOrgUnitScope($request, $companyId);

        $statuses = [WorkOrderStatus::Completed->value, WorkOrderStatus::Delivered->value];

        $serviceIds = DB::table('work_order_items as woi')
            ->join('work_orders as wo', 'wo.id', '=', 'woi.work_order_id')
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customer->id)
            ->whereNull('wo.deleted_at')
            ->whereIn('wo.status', $statuses)
            ->whereNotNull('woi.service_id')
            ->whereRaw('COALESCE(wo.completed_at, wo.delivered_at, wo.updated_at) BETWEEN ? AND ?', [$from, $to])
            ->when($orgUnitScope !== [], static function ($q) use ($orgUnitScope): void {
                $q->whereExists(static function ($uq) use ($orgUnitScope): void {
                    $uq->selectRaw('1')
                        ->from('users')
                        ->whereColumn('users.id', 'wo.created_by_user_id')
                        ->whereIn('users.org_unit_id', $orgUnitScope);
                });
            })
            ->distinct()
            ->limit(300)
            ->pluck('woi.service_id');

        $productIds = DB::table('work_order_items as woi')
            ->join('work_orders as wo', 'wo.id', '=', 'woi.work_order_id')
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customer->id)
            ->whereNull('wo.deleted_at')
            ->whereIn('wo.status', $statuses)
            ->whereNotNull('woi.product_id')
            ->whereRaw('COALESCE(wo.completed_at, wo.delivered_at, wo.updated_at) BETWEEN ? AND ?', [$from, $to])
            ->when($orgUnitScope !== [], static function ($q) use ($orgUnitScope): void {
                $q->whereExists(static function ($uq) use ($orgUnitScope): void {
                    $uq->selectRaw('1')
                        ->from('users')
                        ->whereColumn('users.id', 'wo.created_by_user_id')
                        ->whereIn('users.org_unit_id', $orgUnitScope);
                });
            })
            ->distinct()
            ->limit(300)
            ->pluck('woi.product_id');

        $services = Service::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $serviceIds)
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar', 'code'])
            ->map(static fn (Service $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'name_ar' => $s->name_ar,
                'code' => $s->code,
            ])
            ->values()
            ->all();

        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar', 'sku'])
            ->map(static fn (Product $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'name_ar' => $p->name_ar,
                'sku' => $p->sku,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => ['services' => $services, 'products' => $products],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * ملخص موسّع للعميل ضمن الفترة + لقطة أوامر العمل ومخطط الحالات (أوامر مفتوحة في الفترة).
     */
    public function summary(CustomerPortalReportRangeRequest $request): JsonResponse
    {
        [, $customer, $companyId] = $this->requirePortalCustomer($request);
        if ($customer === null) {
            return response()->json([
                'data' => $this->emptySummaryPayload(),
                'trace_id' => app('trace_id'),
            ]);
        }

        $validated = $request->validated();
        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $orgUnitScope = $this->resolveOrgUnitScope($request, $companyId);

        $invoiceBase = Invoice::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->whereBetween('issued_at', [$from, $to])
            ->whereNotIn('status', [InvoiceStatus::Cancelled, InvoiceStatus::Refunded, InvoiceStatus::Draft])
            ->when($orgUnitScope !== [], static function (Builder $q) use ($orgUnitScope): void {
                $q->whereHas('createdBy', static fn ($uq) => $uq->whereIn('org_unit_id', $orgUnitScope));
            });

        $invoiceRows = (clone $invoiceBase)->get(['total', 'paid_amount', 'due_amount', 'due_at', 'status']);

        $now = Carbon::now();
        $partialCount = $invoiceRows->filter(static fn ($r) => $r->status === InvoiceStatus::PartialPaid)->count();
        $paidCount = $invoiceRows->filter(static function ($r) use ($now): bool {
            if ($r->status === InvoiceStatus::Paid) {
                return true;
            }
            if ((float) $r->due_amount <= 0.00001 && (float) $r->total > 0) {
                return true;
            }

            return false;
        })->count();
        $overdueCount = $invoiceRows->filter(static function ($r) use ($now): bool {
            return (float) $r->due_amount > 0 && $r->due_at !== null && $r->due_at->lt($now);
        })->count();
        $unpaidCount = $invoiceRows->filter(static function ($r) use ($now): bool {
            if ((float) $r->due_amount <= 0) {
                return false;
            }
            if ($r->due_at !== null && $r->due_at->lt($now)) {
                return false;
            }

            return true;
        })->count();

        $terminalOpen = [
            WorkOrderStatus::Completed->value,
            WorkOrderStatus::Delivered->value,
            WorkOrderStatus::Cancelled->value,
        ];
        $activeOpen = WorkOrder::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->whereNotIn('status', $terminalOpen)
            ->when($orgUnitScope !== [], static function (Builder $q) use ($orgUnitScope): void {
                $q->whereHas('createdBy', static fn ($uq) => $uq->whereIn('org_unit_id', $orgUnitScope));
            })
            ->count();

        $completedStatuses = [WorkOrderStatus::Completed->value, WorkOrderStatus::Delivered->value];
        $woCompletedBase = WorkOrder::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->whereIn('status', $completedStatuses)
            ->whereRaw('COALESCE(completed_at, delivered_at, updated_at) BETWEEN ? AND ?', [$from, $to])
            ->when($orgUnitScope !== [], static function (Builder $q) use ($orgUnitScope): void {
                $q->whereHas('createdBy', static fn ($uq) => $uq->whereIn('org_unit_id', $orgUnitScope));
            });

        $completedInPeriod = (clone $woCompletedBase)->count();
        $completedAmountInPeriod = (string) (clone $woCompletedBase)->sum('actual_total');

        $openedByStatus = WorkOrder::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, $to])
            ->when($orgUnitScope !== [], static function (Builder $q) use ($orgUnitScope): void {
                $q->whereHas('createdBy', static fn ($uq) => $uq->whereIn('org_unit_id', $orgUnitScope));
            })
            ->selectRaw('status, COUNT(*) as c, COALESCE(SUM(actual_total), 0) as amt')
            ->groupBy('status')
            ->orderByDesc('c')
            ->get()
            ->map(static function ($r): array {
                return [
                    'status' => (string) $r->status,
                    'count' => (int) $r->c,
                    'total_actual' => (string) $r->amt,
                ];
            })
            ->values()
            ->all();

        $vehiclesRegistered = Vehicle::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'data' => [
                'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
                'invoices' => [
                    'count' => $invoiceRows->count(),
                    'total_invoiced' => (string) $invoiceRows->sum(static fn ($r) => (float) $r->total),
                    'total_paid' => (string) $invoiceRows->sum(static fn ($r) => (float) $r->paid_amount),
                    'total_due' => (string) $invoiceRows->sum(static fn ($r) => (float) $r->due_amount),
                    'overdue_count' => $overdueCount,
                    'paid_count' => $paidCount,
                    'unpaid_count' => $unpaidCount,
                    'partial_count' => $partialCount,
                ],
                'work_orders' => [
                    'active_open' => $activeOpen,
                    'completed_in_period' => $completedInPeriod,
                    'completed_amount_in_period' => $completedAmountInPeriod,
                    'opened_in_period_by_status' => $openedByStatus,
                ],
                'vehicles_registered' => $vehiclesRegistered,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * فواتير العميل ضمن الفترة مع فلترة من الخادم وتصفّح.
     */
    public function invoices(CustomerPortalReportRangeRequest $request): JsonResponse
    {
        [, $customer, $companyId] = $this->requirePortalCustomer($request);
        if ($customer === null) {
            return response()->json([
                'data' => ['rows' => []],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
                'trace_id' => app('trace_id'),
            ]);
        }

        $validated = $request->validated();
        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $orgUnitScope = $this->resolveOrgUnitScope($request, $companyId);
        $perPage = min(100, max(1, (int) ($validated['per_page'] ?? 25)));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $minAmount = isset($validated['min_amount']) ? (float) $validated['min_amount'] : null;
        $maxAmount = isset($validated['max_amount']) ? (float) $validated['max_amount'] : null;
        $vehicleId = isset($validated['vehicle_id']) ? (int) $validated['vehicle_id'] : null;
        $search = isset($validated['search']) ? trim((string) $validated['search']) : '';
        $paymentStatus = isset($validated['payment_status']) ? (string) $validated['payment_status'] : 'all';

        if ($vehicleId !== null && $vehicleId > 0) {
            $owns = Vehicle::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $customer->id)
                ->whereNull('deleted_at')
                ->whereKey($vehicleId)
                ->exists();
            if (! $owns) {
                $vehicleId = null;
            }
        }

        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->whereBetween('issued_at', [$from, $to])
            ->whereNotIn('status', [InvoiceStatus::Cancelled, InvoiceStatus::Refunded, InvoiceStatus::Draft])
            ->when($orgUnitScope !== [], static function (Builder $q) use ($orgUnitScope): void {
                $q->whereHas('createdBy', static fn ($uq) => $uq->whereIn('org_unit_id', $orgUnitScope));
            })
            ->when($vehicleId !== null && $vehicleId > 0, static fn (Builder $q) => $q->where('vehicle_id', $vehicleId))
            ->when($minAmount !== null, static fn (Builder $q) => $q->where('total', '>=', $minAmount))
            ->when($maxAmount !== null, static fn (Builder $q) => $q->where('total', '<=', $maxAmount))
            ->when($search !== '', static function (Builder $q) use ($search): void {
                $idNumeric = ctype_digit($search) ? (int) $search : 0;
                $q->where(static function (Builder $x) use ($search, $idNumeric): void {
                    $x->where('invoice_number', 'ilike', '%'.$search.'%');
                    if ($idNumeric > 0) {
                        $x->orWhereKey($idNumeric);
                    }
                });
            });

        $this->applyInvoicePaymentStatusFilter($query, $paymentStatus);

        $paginator = $query
            ->with(['vehicle:id,plate_number,make,model'])
            ->orderByDesc('issued_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(static function (Invoice $inv): array {
            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'issued_at' => $inv->issued_at?->toIso8601String(),
                'due_at' => $inv->due_at?->toIso8601String(),
                'total' => $inv->total !== null ? (string) $inv->total : null,
                'paid_amount' => $inv->paid_amount !== null ? (string) $inv->paid_amount : null,
                'due_amount' => $inv->due_amount !== null ? (string) $inv->due_amount : null,
                'status' => $inv->status instanceof InvoiceStatus ? $inv->status->value : (string) $inv->status,
                'vehicle' => $inv->vehicle ? [
                    'plate_number' => $inv->vehicle->plate_number,
                    'make' => $inv->vehicle->make,
                    'model' => $inv->vehicle->model,
                ] : null,
            ];
        })->values()->all();

        return response()->json([
            'data' => ['rows' => $rows],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function itemsByService(CustomerPortalReportRangeRequest $request): JsonResponse
    {
        [, $customer, $companyId] = $this->requirePortalCustomer($request);
        if ($customer === null) {
            return $this->emptyPaginated($request);
        }

        $validated = $request->validated();
        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $orgUnitScope = $this->resolveOrgUnitScope($request, $companyId);
        $perPage = min(100, max(1, (int) ($validated['per_page'] ?? 25)));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $serviceFilter = isset($validated['service_id']) ? (int) $validated['service_id'] : null;

        $statuses = [WorkOrderStatus::Completed->value, WorkOrderStatus::Delivered->value];

        $query = DB::table('work_order_items as woi')
            ->join('work_orders as wo', 'wo.id', '=', 'woi.work_order_id')
            ->join('services as s', function ($join) use ($companyId): void {
                $join->on('s.id', '=', 'woi.service_id')
                    ->where('s.company_id', '=', $companyId);
            })
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customer->id)
            ->whereNull('wo.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereIn('wo.status', $statuses)
            ->whereNotNull('woi.service_id')
            ->whereRaw('COALESCE(wo.completed_at, wo.delivered_at, wo.updated_at) BETWEEN ? AND ?', [$from, $to])
            ->when($orgUnitScope !== [], static function ($q) use ($orgUnitScope): void {
                $q->whereExists(static function ($uq) use ($orgUnitScope): void {
                    $uq->selectRaw('1')
                        ->from('users')
                        ->whereColumn('users.id', 'wo.created_by_user_id')
                        ->whereIn('users.org_unit_id', $orgUnitScope);
                });
            })
            ->when($serviceFilter, static fn ($q) => $q->where('woi.service_id', $serviceFilter))
            ->groupBy('woi.service_id', 's.name', 's.name_ar', 's.code')
            ->selectRaw(
                'woi.service_id as id, s.name as name, s.name_ar as name_ar, s.code as code, '.
                'SUM(woi.quantity) as total_quantity, SUM(woi.total) as total_amount, COUNT(*) as lines_count',
            )
            ->orderByDesc('total_amount');

        $rows = $query->get();
        $total = $rows->count();
        $slice = $rows->forPage($page, $perPage)->values()->map(static function ($r): array {
            return [
                'id' => (int) $r->id,
                'name' => $r->name,
                'name_ar' => $r->name_ar,
                'code' => $r->code,
                'total_quantity' => (string) $r->total_quantity,
                'total_amount' => (string) $r->total_amount,
                'lines_count' => (int) $r->lines_count,
            ];
        })->all();

        $summaryQty = (string) $rows->sum('total_quantity');
        $summaryAmt = (string) $rows->sum('total_amount');

        return response()->json([
            'data' => [
                'rows' => $slice,
                'summary' => ['total_quantity' => $summaryQty, 'total_amount' => $summaryAmt, 'groups' => $total],
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function itemsByProduct(CustomerPortalReportRangeRequest $request): JsonResponse
    {
        [, $customer, $companyId] = $this->requirePortalCustomer($request);
        if ($customer === null) {
            return $this->emptyPaginated($request);
        }

        $validated = $request->validated();
        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $orgUnitScope = $this->resolveOrgUnitScope($request, $companyId);
        $perPage = min(100, max(1, (int) ($validated['per_page'] ?? 25)));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $productFilter = isset($validated['product_id']) ? (int) $validated['product_id'] : null;

        $statuses = [WorkOrderStatus::Completed->value, WorkOrderStatus::Delivered->value];

        $query = DB::table('work_order_items as woi')
            ->join('work_orders as wo', 'wo.id', '=', 'woi.work_order_id')
            ->join('products as p', function ($join) use ($companyId): void {
                $join->on('p.id', '=', 'woi.product_id')
                    ->where('p.company_id', '=', $companyId);
            })
            ->where('wo.company_id', $companyId)
            ->where('wo.customer_id', $customer->id)
            ->whereNull('wo.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereIn('wo.status', $statuses)
            ->whereNotNull('woi.product_id')
            ->whereRaw('COALESCE(wo.completed_at, wo.delivered_at, wo.updated_at) BETWEEN ? AND ?', [$from, $to])
            ->when($orgUnitScope !== [], static function ($q) use ($orgUnitScope): void {
                $q->whereExists(static function ($uq) use ($orgUnitScope): void {
                    $uq->selectRaw('1')
                        ->from('users')
                        ->whereColumn('users.id', 'wo.created_by_user_id')
                        ->whereIn('users.org_unit_id', $orgUnitScope);
                });
            })
            ->when($productFilter, static fn ($q) => $q->where('woi.product_id', $productFilter))
            ->groupBy('woi.product_id', 'p.name', 'p.name_ar', 'p.sku')
            ->selectRaw(
                'woi.product_id as id, p.name as name, p.name_ar as name_ar, p.sku as sku, '.
                'SUM(woi.quantity) as total_quantity, SUM(woi.total) as total_amount, COUNT(*) as lines_count',
            )
            ->orderByDesc('total_amount');

        $rows = $query->get();
        $total = $rows->count();
        $slice = $rows->forPage($page, $perPage)->values()->map(static function ($r): array {
            return [
                'id' => (int) $r->id,
                'name' => $r->name,
                'name_ar' => $r->name_ar,
                'sku' => $r->sku,
                'total_quantity' => (string) $r->total_quantity,
                'total_amount' => (string) $r->total_amount,
                'lines_count' => (int) $r->lines_count,
            ];
        })->all();

        $summaryQty = (string) $rows->sum('total_quantity');
        $summaryAmt = (string) $rows->sum('total_amount');

        return response()->json([
            'data' => [
                'rows' => $slice,
                'summary' => ['total_quantity' => $summaryQty, 'total_amount' => $summaryAmt, 'groups' => $total],
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function workOrdersCompleted(CustomerPortalReportRangeRequest $request): JsonResponse
    {
        [, $customer, $companyId] = $this->requirePortalCustomer($request);
        if ($customer === null) {
            return response()->json([
                'data' => ['rows' => [], 'summary' => ['count' => 0, 'total_actual' => '0']],
                'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1],
                'trace_id' => app('trace_id'),
            ]);
        }

        $validated = $request->validated();
        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();
        $orgUnitScope = $this->resolveOrgUnitScope($request, $companyId);
        $perPage = min(100, max(1, (int) ($validated['per_page'] ?? 25)));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $serviceFilter = isset($validated['service_id']) ? (int) $validated['service_id'] : null;
        $productFilter = isset($validated['product_id']) ? (int) $validated['product_id'] : null;

        $base = WorkOrder::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereIn('status', [WorkOrderStatus::Completed, WorkOrderStatus::Delivered])
            ->whereRaw('COALESCE(completed_at, delivered_at, updated_at) BETWEEN ? AND ?', [$from, $to])
            ->when($orgUnitScope !== [], static function ($q) use ($orgUnitScope): void {
                $q->whereHas('createdBy', static fn ($uq) => $uq->whereIn('org_unit_id', $orgUnitScope));
            })
            ->when($serviceFilter, static function ($q) use ($serviceFilter): void {
                $q->whereHas('items', static fn ($iq) => $iq->where('service_id', $serviceFilter));
            })
            ->when($productFilter, static function ($q) use ($productFilter): void {
                $q->whereHas('items', static fn ($iq) => $iq->where('product_id', $productFilter));
            });

        $summaryTotal = (string) (clone $base)->sum('actual_total');

        $paginator = (clone $base)
            ->with(['vehicle:id,plate_number,make,model'])
            ->orderByRaw('COALESCE(completed_at, delivered_at, updated_at) DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(static function (WorkOrder $wo): array {
            return [
                'id' => $wo->id,
                'order_number' => $wo->order_number,
                'work_order_number' => $wo->work_order_number,
                'status' => $wo->status instanceof WorkOrderStatus ? $wo->status->value : (string) $wo->status,
                'completed_at' => $wo->completed_at?->toIso8601String(),
                'delivered_at' => $wo->delivered_at?->toIso8601String(),
                'actual_total' => $wo->actual_total !== null ? (string) $wo->actual_total : null,
                'vehicle' => $wo->vehicle ? [
                    'plate_number' => $wo->vehicle->plate_number,
                    'make' => $wo->vehicle->make,
                    'model' => $wo->vehicle->model,
                ] : null,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'rows' => $rows,
                'summary' => ['count' => $paginator->total(), 'total_actual' => $summaryTotal],
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @return array{0: User, 1: Customer|null, 2: int}
     */
    private function requirePortalCustomer(CustomerPortalReportRangeRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->role->isCustomer()) {
            abort(403, 'هذه الخاصية متاحة لحسابات العملاء فقط.');
        }
        $companyId = (int) $user->company_id;
        $customer = $this->resolvePortalCustomer($user);

        return [$user, $customer, $companyId];
    }

    private function resolvePortalCustomer(User $user): ?Customer
    {
        $companyId = $user->company_id;
        if ($companyId === null || (int) $companyId <= 0) {
            return null;
        }

        if ($user->customer_id !== null && (int) $user->customer_id > 0) {
            return Customer::query()
                ->where('company_id', $companyId)
                ->whereKey((int) $user->customer_id)
                ->first();
        }

        return Customer::query()
            ->where('company_id', $companyId)
            ->where('email', $user->email)
            ->first();
    }

    private function emptyPaginated(CustomerPortalReportRangeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min(100, max(1, (int) ($validated['per_page'] ?? 25)));

        return response()->json([
            'data' => [
                'rows' => [],
                'summary' => ['total_quantity' => '0', 'total_amount' => '0', 'groups' => 0],
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function applyInvoicePaymentStatusFilter(Builder $query, string $paymentStatus): void
    {
        if ($paymentStatus === '' || $paymentStatus === 'all') {
            return;
        }

        $now = Carbon::now();
        match ($paymentStatus) {
            'paid' => $query->where(static function (Builder $q): void {
                $q->where('status', InvoiceStatus::Paid)
                    ->orWhereColumn('due_amount', '<=', 0);
            }),
            'partial' => $query->where('status', InvoiceStatus::PartialPaid),
            'overdue' => $query->where('due_amount', '>', 0)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $now),
            'unpaid' => $query->where('due_amount', '>', 0)
                ->where(static function (Builder $q) use ($now): void {
                    $q->whereNull('due_at')->orWhere('due_at', '>=', $now);
                }),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummaryPayload(): array
    {
        return [
            'period' => [],
            'invoices' => [
                'count' => 0,
                'total_invoiced' => '0',
                'total_paid' => '0',
                'total_due' => '0',
                'overdue_count' => 0,
                'paid_count' => 0,
                'unpaid_count' => 0,
                'partial_count' => 0,
            ],
            'work_orders' => [
                'active_open' => 0,
                'completed_in_period' => 0,
                'completed_amount_in_period' => '0',
                'opened_in_period_by_status' => [],
            ],
            'vehicles_registered' => 0,
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveOrgUnitScope(CustomerPortalReportRangeRequest $request, int $companyId): array
    {
        $orgUnitId = (int) ($request->validated('org_unit_id') ?? 0);
        if ($orgUnitId <= 0) {
            return [];
        }

        $root = OrgUnit::query()
            ->where('company_id', $companyId)
            ->whereKey($orgUnitId)
            ->first();
        if ($root === null) {
            return [];
        }

        $scope = [$root->id];
        $frontier = [$root->id];
        $guard = 0;
        while ($frontier !== [] && $guard < 16) {
            $children = OrgUnit::query()
                ->where('company_id', $companyId)
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $new = array_values(array_diff($children, $scope));
            if ($new === []) {
                break;
            }
            $scope = array_values(array_unique(array_merge($scope, $new)));
            $frontier = $new;
            $guard++;
        }

        return $scope;
    }
}
