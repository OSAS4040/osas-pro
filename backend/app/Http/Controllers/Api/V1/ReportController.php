<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\WorkOrderStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Task;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    public function kpiDictionary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'operational' => [
                    ['key' => 'work_orders_completion_rate', 'label' => 'معدل إكمال أوامر العمل', 'formula' => 'completed_work_orders / total_work_orders * 100'],
                    ['key' => 'tasks_overdue', 'label' => 'المهام المتأخرة', 'formula' => 'COUNT(open_tasks where due_at < now)'],
                    ['key' => 'bookings_total', 'label' => 'إجمالي الحجوزات', 'formula' => 'SUM(bookings by status in period)'],
                ],
                'employees' => [
                    ['key' => 'employees_total', 'label' => 'إجمالي الموظفين', 'formula' => 'COUNT(employees)'],
                    ['key' => 'employees_active', 'label' => 'الموظفون النشطون', 'formula' => "COUNT(employees where status='active')"],
                    ['key' => 'new_hires', 'label' => 'التعيينات الجديدة', 'formula' => 'COUNT(hire_date in period)'],
                    ['key' => 'overdue_tasks', 'label' => 'المهام المتأخرة', 'formula' => 'COUNT(open_tasks where due_at < now)'],
                ],
                'financial' => [
                    ['key' => 'total_sales', 'label' => 'إجمالي المبيعات', 'formula' => 'SUM(invoices.total)'],
                    ['key' => 'total_vat', 'label' => 'إجمالي الضريبة', 'formula' => 'SUM(invoices.tax_amount)'],
                    ['key' => 'collection_rate', 'label' => 'معدل التحصيل', 'formula' => 'SUM(payments.completed)/SUM(invoices.total) * 100'],
                    ['key' => 'total_due', 'label' => 'إجمالي المستحقات', 'formula' => 'SUM(invoices.due_amount where status is pending/partial)'],
                ],
                'intelligence' => [
                    ['key' => 'sales_delta_pct', 'label' => 'نسبة تغير المبيعات', 'formula' => '(sales_current - sales_previous) / sales_previous * 100'],
                    ['key' => 'forecast.next_period_sales', 'label' => 'توقع الفترة القادمة', 'formula' => 'sales_current * (1 + sales_delta_pct/100)'],
                    ['key' => 'communications_open', 'label' => 'معاملات اتصالات إدارية مفتوحة', 'formula' => 'COUNT(communications where state not archived and not signed)'],
                    ['key' => 'communications_signature_pending', 'label' => 'معاملات بانتظار توقيع', 'formula' => 'COUNT(communications where signature.status = pending)'],
                    ['key' => 'smart_tasks_overdue', 'label' => 'مهام ذكية متأخرة', 'formula' => 'COUNT(tasks where due_at < now and status open)'],
                ],
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function sales(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $fromEnd = $request->from.' 00:00:00';
        $toEnd   = $request->to.' 23:59:59';

        $companyId = app('tenant_company_id');

        $summaryRow = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->selectRaw('COUNT(*) as invoice_count, SUM(total) as total_sales, SUM(tax_amount) as total_tax, SUM(discount_amount) as total_discount')
            ->first();

        $summary = $summaryRow ? [
            'invoice_count'  => (int) $summaryRow->invoice_count,
            'count'          => (int) $summaryRow->invoice_count,
            'total_sales'    => round((float) $summaryRow->total_sales, 2),
            'total_tax'      => round((float) $summaryRow->total_tax, 2),
            'total_discount' => round((float) $summaryRow->total_discount, 2),
        ] : null;

        $byBranch = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->selectRaw('branch_id, COUNT(*) as invoice_count, SUM(total) as total_sales')
            ->groupBy('branch_id')
            ->with('branch:id,name')
            ->get();

        return response()->json([
            'data'     => compact('summary', 'byBranch'),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function salesByCustomer(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $fromEnd = $request->from.' 00:00:00';
        $toEnd   = $request->to.' 23:59:59';
        $companyId = app('tenant_company_id');

        $data = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, COUNT(*) as invoice_count, SUM(total) as total_sales, SUM(tax_amount) as total_tax')
            ->groupBy('customer_id')
            ->with('customer:id,name,phone')
            ->orderByDesc('total_sales')
            ->limit(50)
            ->get();

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function salesByProduct(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');

        $fromEnd = $request->from.' 00:00:00';
        $toEnd   = $request->to.' 23:59:59';

        $data = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.company_id', $companyId)
            ->whereBetween('invoices.issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('invoices.status', ['cancelled', 'draft'])
            ->selectRaw('products.name as product_name, products.sku, SUM(invoice_items.quantity) as total_qty, SUM(invoice_items.total) as total_sales, SUM(invoice_items.total) as total_revenue')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_sales')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'product_name'  => $r->product_name,
                'sku'           => $r->sku,
                'total_qty'     => round((float) $r->total_qty, 4),
                'total_sales'   => round((float) $r->total_sales, 2),
                'total_revenue' => round((float) $r->total_revenue, 2),
            ]);

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function overdueReceivables(Request $request): JsonResponse
    {
        $companyId = app('tenant_company_id');

        $data = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'partial_paid'])
            ->where('due_at', '<', now())
            ->with('customer:id,name,phone')
            ->select('id', 'invoice_number', 'customer_id', 'due_amount', 'due_at', 'issued_at')
            ->orderBy('due_at')
            ->limit(100)
            ->get()
            ->map(fn ($inv) => array_merge($inv->toArray(), [
                'days_overdue' => now()->diffInDays($inv->due_at),
            ]));

        $total = $data->sum('due_amount');

        return response()->json(['data' => compact('data', 'total'), 'trace_id' => app('trace_id')]);
    }

    public function workOrders(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');

        $fromEnd = $request->from.' 00:00:00';
        $toEnd   = $request->to.' 23:59:59';

        $summary = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $total = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->count();

        return response()->json(['data' => compact('summary', 'total'), 'trace_id' => app('trace_id')]);
    }

    public function kpi(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');

        $fromEnd = $request->from.' 00:00:00';
        $toEnd   = $request->to.' 23:59:59';

        $cacheKey = "kpi:{$companyId}:{$request->from}:{$request->to}:v3";
        $ttl = now()->diffInHours(now()->endOfDay()) < 2 ? 300 : 1800;
        $data = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($data === null) {
            $lockKey = "lock:{$cacheKey}";
            $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 15);
            $computed = null;

            try {
                $lock->block(5, function () use (&$computed, $cacheKey, $ttl, $companyId, $fromEnd, $toEnd, $request) {
                    $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
                    if ($cached !== null) {
                        $computed = $cached;
                        return;
                    }

                    $computed = $this->buildKpiPayload($companyId, $fromEnd, $toEnd, $request);
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $computed, $ttl);
                });
            } catch (\Throwable) {
                $computed = \Illuminate\Support\Facades\Cache::get($cacheKey)
                    ?? $this->buildKpiPayload($companyId, $fromEnd, $toEnd, $request);
            } finally {
                try {
                    $lock->release();
                } catch (\Throwable) {
                }
            }

            $data = $computed;
        }

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    private function buildKpiPayload(int $companyId, string $fromEnd, string $toEnd, Request $request): array
    {
        $invoiceScope = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('status', ['cancelled', 'draft']);

        $totalRevenue = (clone $invoiceScope)->sum('total');
        $invoiceCount = (clone $invoiceScope)->count();
        $totalVat     = (clone $invoiceScope)->sum('tax_amount');

        $totalCollected = Payment::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->where('status', 'completed')
            ->sum('amount');

        $newCustomers = Customer::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->count();

        $woCompleted = WorkOrder::where('company_id', $companyId)
            ->whereBetween('updated_at', [$fromEnd, $toEnd])
            ->where('status', 'completed')
            ->count();

        $woTotal = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->count();

        $avgInvoiceValue = $invoiceCount > 0 ? round($totalRevenue / $invoiceCount, 2) : 0.0;

        $outstandingDue = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'partial_paid'])
            ->sum('due_amount');

        $dailyRevenue = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->selectRaw('DATE(issued_at) as day, COALESCE(SUM(total), 0) as total')
            ->groupBy(DB::raw('DATE(issued_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day'   => (string) $r->day,
                'total' => round((float) $r->total, 2),
            ]);

        $collectionRate = $totalRevenue > 0 ? round(($totalCollected / $totalRevenue) * 100, 1) : 0;

        return [
            'total_revenue'        => round((float) $totalRevenue, 2),
            'total_sales'          => round((float) $totalRevenue, 2),
            'total_collected'      => round((float) $totalCollected, 2),
            'total_paid'           => round((float) $totalCollected, 2),
            'collection_rate'      => $collectionRate,
            'invoice_count'        => (int) $invoiceCount,
            'total_vat'            => round((float) $totalVat, 2),
            'total_due'            => round((float) $outstandingDue, 2),
            'new_customers'        => $newCustomers,
            'wo_completed'         => $woCompleted,
            'wo_total'             => $woTotal,
            'work_order_count'     => $woTotal,
            'wo_completion_rate'   => $woTotal > 0 ? round(($woCompleted / $woTotal) * 100, 1) : 0,
            'avg_invoice_value'    => round((float) $avgInvoiceValue, 2),
            'daily_revenue'        => $dailyRevenue,
            'period'               => ['from' => $request->from, 'to' => $request->to],
        ];
    }

    public function vatReport(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $fromEnd = $request->from.' 00:00:00';
        $toEnd   = $request->to.' 23:59:59';
        $companyId = app('tenant_company_id');

        $row = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->selectRaw('
                COUNT(*) as invoice_count,
                SUM(subtotal) as taxable_amount,
                SUM(tax_amount) as vat_collected,
                SUM(total) as total_with_vat
            ')
            ->first();

        $base = $row ? [
            'invoice_count'  => (int) $row->invoice_count,
            'taxable_amount' => round((float) $row->taxable_amount, 2),
            'vat_collected'  => round((float) $row->vat_collected, 2),
            'total_with_vat' => round((float) $row->total_with_vat, 2),
        ] : [
            'invoice_count'  => 0,
            'taxable_amount' => 0,
            'vat_collected'  => 0,
            'total_with_vat' => 0,
        ];

        // Aggregate by line tax_rate (invoices table has no tax_rate column in schema)
        $byRate = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.company_id', $companyId)
            ->whereBetween('invoices.issued_at', [$fromEnd, $toEnd])
            ->whereNotIn('invoices.status', ['cancelled', 'draft'])
            ->selectRaw('invoice_items.tax_rate, SUM(invoice_items.subtotal) as taxable_amount, SUM(invoice_items.tax_amount) as tax_amount')
            ->groupBy('invoice_items.tax_rate')
            ->orderBy('invoice_items.tax_rate')
            ->get()
            ->map(fn ($r) => [
                'tax_rate'       => round((float) $r->tax_rate, 2),
                'taxable_amount' => round((float) $r->taxable_amount, 2),
                'tax_amount'     => round((float) $r->tax_amount, 2),
            ])
            ->values()
            ->all();

        $data = array_merge($base, [
            'total_tax'   => round((float) ($base['vat_collected'] ?? 0), 2),
            'net_sales'   => round((float) ($base['taxable_amount'] ?? 0), 2),
            'gross_sales' => round((float) ($base['total_with_vat'] ?? 0), 2),
            'by_rate'     => $byRate,
        ]);

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $companyId = app('tenant_company_id');

        $data = DB::table('inventory')
            ->where('inventory.company_id', $companyId)
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->join('branches', 'branches.id', '=', 'inventory.branch_id')
            ->select(
                'products.name as product_name',
                'products.barcode',
                'branches.name as branch_name',
                'inventory.quantity',
                'inventory.reserved_quantity',
                DB::raw('inventory.quantity - inventory.reserved_quantity as available_quantity'),
                'inventory.reorder_point'
            )
            ->when($request->low_stock, fn($q) => $q->whereRaw('inventory.quantity - inventory.reserved_quantity <= inventory.reorder_point'))
            ->paginate(50);

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function financial(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');

        $fromEnd = $request->from.' 00:00:00';
        $toEnd   = $request->to.' 23:59:59';

        $payments = Payment::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->where('status', 'completed')
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('method')
            ->get();

        return response()->json([
            'data'     => ['payments_by_method' => $payments],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * تحليل مالي وتشغيلي موحّد لصفحة ذكاء الأعمال (نفس نطاق التاريخ).
     */
    public function businessAnalytics(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');
        $fromEnd   = $request->from.' 00:00:00';
        $toEnd     = $request->to.' 23:59:59';

        $cacheKey = "reports:bi:analytics:{$companyId}:{$request->from}:{$request->to}:v1";
        $data     = Cache::remember($cacheKey, 600, function () use ($companyId, $fromEnd, $toEnd, $request) {
            $invoiceRows = DB::table('invoices')
                ->where('company_id', $companyId)
                ->whereBetween('issued_at', [$fromEnd, $toEnd])
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total), 0) as total_amount')
                ->groupBy('status')
                ->orderByDesc('total_amount')
                ->get()
                ->map(fn ($r) => [
                    'status'        => (string) $r->status,
                    'count'         => (int) $r->count,
                    'total_amount'  => round((float) $r->total_amount, 2),
                ])->values()->all();

            $agg = DB::table('invoices')
                ->where('company_id', $companyId)
                ->whereBetween('issued_at', [$fromEnd, $toEnd])
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->selectRaw('
                    COALESCE(SUM(subtotal), 0) as subtotal_sum,
                    COALESCE(SUM(discount_amount), 0) as discount_sum,
                    COALESCE(SUM(tax_amount), 0) as tax_sum,
                    COALESCE(SUM(total), 0) as total_sum,
                    COUNT(*) as invoice_count
                ')
                ->first();

            $subtotalSum = (float) ($agg->subtotal_sum ?? 0);
            $discountSum = (float) ($agg->discount_sum ?? 0);
            $totalSum    = (float) ($agg->total_sum ?? 0);
            $grossBeforeDiscount = $subtotalSum + $discountSum;
            $discount_ratio_pct  = $grossBeforeDiscount > 0
                ? round(($discountSum / $grossBeforeDiscount) * 100, 2)
                : 0.0;

            $payAgg = Payment::where('company_id', $companyId)
                ->whereBetween('created_at', [$fromEnd, $toEnd])
                ->where('status', 'completed')
                ->selectRaw('COUNT(*) as payment_count, COALESCE(SUM(amount), 0) as payment_total')
                ->first();

            $paymentCount = (int) ($payAgg->payment_count ?? 0);
            $paymentTotal = (float) ($payAgg->payment_total ?? 0);
            $avg_payment  = $paymentCount > 0 ? round($paymentTotal / $paymentCount, 2) : 0.0;

            $overdue = DB::table('invoices')
                ->where('company_id', $companyId)
                ->whereIn('status', ['pending', 'partial_paid'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->selectRaw('COUNT(*) as overdue_count, COALESCE(SUM(due_amount), 0) as overdue_amount')
                ->first();

            $woRows = DB::table('work_orders')
                ->where('company_id', $companyId)
                ->whereBetween('created_at', [$fromEnd, $toEnd])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn ($r) => [
                    'status' => (string) $r->status,
                    'count'  => (int) $r->count,
                ])->values()->all();

            $woTotal = array_sum(array_column($woRows, 'count'));

            $bookingRows = [];
            if (Schema::hasTable('bookings')) {
                $bookingRows = DB::table('bookings')
                    ->where('company_id', $companyId)
                    ->whereBetween('created_at', [$fromEnd, $toEnd])
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->orderBy('status')
                    ->get()
                    ->map(fn ($r) => [
                        'status' => (string) $r->status,
                        'count'  => (int) $r->count,
                    ])->values()->all();
            }

            $purchasesPeriod = [
                'count' => 0,
                'total' => 0.0,
            ];
            if (Schema::hasTable('purchases')) {
                $pRow = DB::table('purchases')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$fromEnd, $toEnd])
                    ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as tot')
                    ->first();
                $purchasesPeriod = [
                    'count' => (int) ($pRow->cnt ?? 0),
                    'total' => round((float) ($pRow->tot ?? 0), 2),
                ];
            }

            $low_stock_skus = 0;
            if (Schema::hasTable('inventory')) {
                $low_stock_skus = (int) DB::table('inventory')
                    ->where('company_id', $companyId)
                    ->whereRaw('COALESCE(quantity, 0) - COALESCE(reserved_quantity, 0) <= COALESCE(reorder_point, 0)')
                    ->count();
            }

            $financial = [
                'invoice_by_status'   => $invoiceRows,
                'subtotal_sum'        => round($subtotalSum, 2),
                'discount_sum'        => round($discountSum, 2),
                'tax_sum'             => round((float) ($agg->tax_sum ?? 0), 2),
                'total_sales_in_period' => round($totalSum, 2),
                'invoice_count_period' => (int) ($agg->invoice_count ?? 0),
                'discount_ratio_pct'  => $discount_ratio_pct,
                'payment_count'       => $paymentCount,
                'payment_total'       => round($paymentTotal, 2),
                'avg_payment'         => $avg_payment,
                'overdue_count'       => (int) ($overdue->overdue_count ?? 0),
                'overdue_amount'      => round((float) ($overdue->overdue_amount ?? 0), 2),
            ];

            $operational = [
                'work_orders_by_status' => $woRows,
                'work_orders_created_total' => $woTotal,
                'bookings_by_status'    => $bookingRows,
                'bookings_created_total' => array_sum(array_column($bookingRows, 'count')),
                'purchases'             => $purchasesPeriod,
                'low_stock_row_count'   => $low_stock_skus,
            ];

            return [
                'financial'   => $financial,
                'operational' => $operational,
                'period'      => ['from' => $request->from, 'to' => $request->to],
            ];
        });

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function employeeReport(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');
        $fromEnd = $request->from.' 00:00:00';
        $toEnd = $request->to.' 23:59:59';

        $total = Employee::where('company_id', $companyId)->count();
        $active = Employee::where('company_id', $companyId)->where('status', 'active')->count();
        $newHires = Employee::where('company_id', $companyId)
            ->whereBetween('hire_date', [$request->from, $request->to])
            ->count();
        $terminated = Employee::where('company_id', $companyId)
            ->whereBetween('termination_date', [$request->from, $request->to])
            ->count();

        $attendanceSummary = [];
        if (Schema::hasTable('attendance_logs')) {
            $attendanceSummary = DB::table('attendance_logs')
                ->join('employees', 'employees.id', '=', 'attendance_logs.employee_id')
                ->where('attendance_logs.company_id', $companyId)
                ->whereBetween('attendance_logs.logged_at', [$fromEnd, $toEnd])
                ->selectRaw('employees.id as employee_id, employees.name as employee_name, COUNT(*) as punches')
                ->groupBy('employees.id', 'employees.name')
                ->orderByDesc('punches')
                ->limit(20)
                ->get()
                ->map(fn ($r) => [
                    'employee_id' => (int) $r->employee_id,
                    'employee_name' => (string) $r->employee_name,
                    'attendance_punches' => (int) $r->punches,
                ])->values()->all();
        }

        $taskRows = Task::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->selectRaw('assigned_to, COUNT(*) as total_tasks')
            ->groupBy('assigned_to')
            ->get();

        $taskByAssignee = [];
        if ($taskRows->isNotEmpty()) {
            $employeeMap = Employee::where('company_id', $companyId)
                ->whereIn('id', $taskRows->pluck('assigned_to')->filter()->values())
                ->pluck('name', 'id');
            $taskByAssignee = $taskRows->map(fn ($r) => [
                'employee_id' => $r->assigned_to ? (int) $r->assigned_to : null,
                'employee_name' => $r->assigned_to ? (string) ($employeeMap[$r->assigned_to] ?? 'غير محدد') : 'غير محدد',
                'task_count' => (int) $r->total_tasks,
            ])->sortByDesc('task_count')->values()->all();
        }

        $openTasks = Task::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'review'])
            ->count();
        $overdueTasks = Task::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'review'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        return response()->json([
            'data' => [
                'summary' => [
                    'employees_total' => $total,
                    'employees_active' => $active,
                    'new_hires' => $newHires,
                    'terminated' => $terminated,
                    'open_tasks' => $openTasks,
                    'overdue_tasks' => $overdueTasks,
                ],
                'attendance_top' => $attendanceSummary,
                'tasks_by_assignee' => $taskByAssignee,
                'kpi_dictionary' => [
                    ['key' => 'employees_total', 'label' => 'إجمالي الموظفين', 'formula' => 'COUNT(employees)'],
                    ['key' => 'employees_active', 'label' => 'الموظفون النشطون', 'formula' => "COUNT(status='active')"],
                    ['key' => 'new_hires', 'label' => 'تعيينات جديدة', 'formula' => 'COUNT(hire_date in period)'],
                    ['key' => 'overdue_tasks', 'label' => 'مهام متأخرة', 'formula' => "COUNT(tasks where due_at < now and status open)"],
                ],
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function operationsReport(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');
        $fromEnd = $request->from.' 00:00:00';
        $toEnd = $request->to.' 23:59:59';

        $woRows = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $woTotal = (int) $woRows->sum('count');
        $woCompleted = (int) (($woRows->firstWhere('status', WorkOrderStatus::Completed)?->count) ?? 0);

        $bookingRows = [];
        if (Schema::hasTable('bookings')) {
            $bookingRows = DB::table('bookings')
                ->where('company_id', $companyId)
                ->whereBetween('created_at', [$fromEnd, $toEnd])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->map(fn ($r) => ['status' => (string) $r->status, 'count' => (int) $r->count])
                ->values()
                ->all();
        }

        $taskOpen = Task::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'review'])
            ->count();
        $taskOverdue = Task::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'review'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $workloadRows = Task::where('company_id', $companyId)
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'review'])
            ->selectRaw('assigned_to, COUNT(*) as open_tasks')
            ->groupBy('assigned_to')
            ->orderByDesc('open_tasks')
            ->get();
        $employeeMap = Employee::where('company_id', $companyId)
            ->pluck('name', 'id');
        $workload = $workloadRows->map(fn ($r) => [
            'employee_id' => $r->assigned_to ? (int) $r->assigned_to : null,
            'employee_name' => $r->assigned_to ? (string) ($employeeMap[$r->assigned_to] ?? 'غير محدد') : 'غير محدد',
            'open_tasks' => (int) $r->open_tasks,
        ])->values()->all();

        return response()->json([
            'data' => [
                'summary' => [
                    'work_orders_total' => $woTotal,
                    'work_orders_completed' => $woCompleted,
                    'work_orders_completion_rate' => $woTotal > 0 ? round(($woCompleted / $woTotal) * 100, 1) : 0.0,
                    'tasks_open' => $taskOpen,
                    'tasks_overdue' => $taskOverdue,
                    'bookings_total' => array_sum(array_column($bookingRows, 'count')),
                ],
                'work_orders_by_status' => $woRows->map(fn ($r) => [
                    'status' => $r->status instanceof \BackedEnum ? $r->status->value : (string) $r->status,
                    'count' => (int) $r->count,
                ])->values()->all(),
                'bookings_by_status' => $bookingRows,
                'workload' => $workload,
                'kpi_dictionary' => [
                    ['key' => 'work_orders_completion_rate', 'label' => 'معدل إكمال أوامر العمل', 'formula' => 'completed / total * 100'],
                    ['key' => 'tasks_overdue', 'label' => 'المهام المتأخرة', 'formula' => "COUNT(open tasks with due_at < now())"],
                    ['key' => 'bookings_total', 'label' => 'إجمالي الحجوزات', 'formula' => 'SUM(bookings by status)'],
                ],
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function intelligenceDigest(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');

        $currentFrom = Carbon::parse($request->from)->startOfDay();
        $currentTo = Carbon::parse($request->to)->endOfDay();
        $rangeDays = max(1, $currentFrom->diffInDays($currentTo) + 1);
        $prevTo = (clone $currentFrom)->subDay()->endOfDay();
        $prevFrom = (clone $prevTo)->subDays($rangeDays - 1)->startOfDay();

        $sumFor = function (Carbon $fromDate, Carbon $toDate) use ($companyId): array {
            $sales = (float) Invoice::where('company_id', $companyId)
                ->whereBetween('issued_at', [$fromDate, $toDate])
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->sum('total');
            $collected = (float) Payment::where('company_id', $companyId)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->where('status', 'completed')
                ->sum('amount');
            $wo = (int) WorkOrder::where('company_id', $companyId)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count();
            $overdueTasks = (int) Task::where('company_id', $companyId)
                ->whereIn('status', ['pending', 'assigned', 'in_progress', 'review'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count();
            return compact('sales', 'collected', 'wo', 'overdueTasks');
        };

        $current = $sumFor($currentFrom, $currentTo);
        $previous = $sumFor($prevFrom, $prevTo);

        $pct = function (float|int $currentValue, float|int $previousValue): float {
            if ((float) $previousValue === 0.0) {
                return (float) $currentValue > 0 ? 100.0 : 0.0;
            }
            return round((((float) $currentValue - (float) $previousValue) / (float) $previousValue) * 100, 1);
        };

        $salesDelta = $pct($current['sales'], $previous['sales']);
        $collectionRate = $current['sales'] > 0 ? round(($current['collected'] / $current['sales']) * 100, 1) : 0.0;
        $forecastNextPeriod = round($current['sales'] * (1 + ($salesDelta / 100)), 2);

        $communications = $this->communicationsMetrics($companyId);
        $smartTasks = $this->smartTasksMetrics($companyId, $currentFrom, $currentTo);

        $anomalies = [];
        if ($salesDelta < -20) {
            $anomalies[] = ['type' => 'sales_drop', 'severity' => 'high', 'message' => 'انخفاض ملحوظ في المبيعات مقارنة بالفترة السابقة'];
        }
        if ($collectionRate < 55 && $current['sales'] > 0) {
            $anomalies[] = ['type' => 'collection_risk', 'severity' => 'medium', 'message' => 'معدل التحصيل أقل من المستوى المستهدف'];
        }
        if ($current['overdueTasks'] >= 5) {
            $anomalies[] = ['type' => 'operational_delay', 'severity' => 'high', 'message' => 'ارتفاع عدد المهام المتأخرة'];
        }
        if (($communications['signature_pending'] ?? 0) >= 3) {
            $anomalies[] = ['type' => 'signature_backlog', 'severity' => 'medium', 'message' => 'تكدس معاملات بانتظار التوقيع في الاتصالات الإدارية'];
        }
        if (($smartTasks['overdue_open'] ?? 0) >= 5) {
            $anomalies[] = ['type' => 'smart_tasks_delay', 'severity' => 'high', 'message' => 'تأخر مرتفع في المهام الذكية المفتوحة'];
        }

        $recommendations = [];
        if ($collectionRate < 55) {
            $recommendations[] = 'إطلاق حملة تحصيل مركزة للفواتير المتأخرة خلال 48 ساعة';
        }
        if ($current['overdueTasks'] >= 5) {
            $recommendations[] = 'إعادة توزيع المهام على الفرق لتخفيف التكدس التشغيلي';
        }
        if (($communications['signature_pending'] ?? 0) > 0) {
            $recommendations[] = 'تفعيل دورة اعتماد يومية لمعالجة التوقيعات المعلقة في الاتصالات الإدارية';
        }
        if (($communications['open'] ?? 0) > 0 && ($communications['archived_rate'] ?? 0) < 35) {
            $recommendations[] = 'رفع معدل الأرشفة للمعاملات المغلقة لتحسين الامتثال وسهولة الاسترجاع';
        }
        if (($smartTasks['overdue_open'] ?? 0) > 0) {
            $recommendations[] = 'مراجعة أولويات المهام الذكية ذات SLA المنتهي وإعادة الجدولة حسب السعة';
        }
        if ($salesDelta >= 10) {
            $recommendations[] = 'تعزيز المنتجات الأعلى مبيعاً واستمرار الحملات ذات الأداء المرتفع';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'المؤشرات مستقرة، ينصح بالاستمرار مع متابعة يومية للانحرافات';
        }

        return response()->json([
            'data' => [
                'period' => [
                    'current' => ['from' => $currentFrom->toDateString(), 'to' => $currentTo->toDateString()],
                    'previous' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()],
                ],
                'metrics' => [
                    'sales' => round($current['sales'], 2),
                    'sales_delta_pct' => $salesDelta,
                    'collection_rate' => $collectionRate,
                    'work_orders' => $current['wo'],
                    'overdue_tasks' => $current['overdueTasks'],
                ],
                'forecast' => [
                    'next_period_sales' => $forecastNextPeriod,
                    'confidence' => $salesDelta < -20 || $salesDelta > 40 ? 'medium' : 'high',
                    'method' => 'period-over-period trend projection',
                ],
                'modern_features' => [
                    'communications' => $communications['summary'] ?? [],
                    'smart_tasks' => $smartTasks['summary'] ?? [],
                ],
                'anomalies' => $anomalies,
                'recommendations' => $recommendations,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function communicationsReport(Request $request): JsonResponse
    {
        $companyId = app('tenant_company_id');
        $data = $this->communicationsMetrics($companyId);

        return response()->json([
            'data' => $data,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function smartTasksReport(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');
        $fromEnd = Carbon::parse($request->from)->startOfDay();
        $toEnd = Carbon::parse($request->to)->endOfDay();

        $data = $this->smartTasksMetrics($companyId, $fromEnd, $toEnd);

        return response()->json([
            'data' => $data,
            'trace_id' => app('trace_id'),
        ]);
    }

    private function communicationsMetrics(int $companyId): array
    {
        $company = Company::find($companyId);
        $settings = is_array($company?->settings) ? $company->settings : [];
        $bucket = is_array($settings['administrative_communications'] ?? null) ? $settings['administrative_communications'] : [];
        $items = is_array($bucket['transactions'] ?? null) ? $bucket['transactions'] : [];

        $total = count($items);
        $byStateMap = [];
        $signaturePending = 0;
        $signatureCompleted = 0;
        $archived = 0;
        $overdue = 0;
        $byDestinationMap = [];

        foreach ($items as $item) {
            $state = (string) ($item['state'] ?? 'draft');
            $byStateMap[$state] = ($byStateMap[$state] ?? 0) + 1;

            $sigStatus = (string) (($item['signature']['status'] ?? 'not_requested'));
            if ($sigStatus === 'pending') {
                $signaturePending++;
            } elseif ($sigStatus === 'completed') {
                $signatureCompleted++;
            }

            $isArchived = (bool) ($item['archived'] ?? false) || $state === 'archived';
            if ($isArchived) {
                $archived++;
            }

            $dueDateRaw = $item['due_date'] ?? null;
            if (!empty($dueDateRaw) && in_array($state, ['draft', 'submitted', 'under_review', 'sent'], true)) {
                try {
                    if (Carbon::parse((string) $dueDateRaw)->endOfDay()->lt(now())) {
                        $overdue++;
                    }
                } catch (\Throwable) {
                }
            }

            $destination = trim((string) ($item['destination'] ?? ''));
            if ($destination !== '') {
                $byDestinationMap[$destination] = ($byDestinationMap[$destination] ?? 0) + 1;
            }
        }

        arsort($byStateMap);
        arsort($byDestinationMap);

        return [
            'summary' => [
                'total' => $total,
                'open' => max(0, $total - $archived - $signatureCompleted),
                'archived' => $archived,
                'overdue' => $overdue,
                'signature_pending' => $signaturePending,
                'signature_completed' => $signatureCompleted,
                'archived_rate' => $total > 0 ? round(($archived / $total) * 100, 1) : 0.0,
            ],
            'by_state' => collect($byStateMap)->map(fn($count, $state) => ['state' => $state, 'count' => (int) $count])->values()->all(),
            'by_destination' => collect($byDestinationMap)->take(10)->map(fn($count, $dest) => ['destination' => $dest, 'count' => (int) $count])->values()->all(),
        ];
    }

    private function smartTasksMetrics(int $companyId, Carbon $fromDate, Carbon $toDate): array
    {
        $fromEnd = $fromDate->copy()->startOfDay();
        $toEnd = $toDate->copy()->endOfDay();

        $statusRows = Task::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $priorityRows = Task::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromEnd, $toEnd])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->orderBy('priority')
            ->get();

        $openStates = ['pending', 'assigned', 'in_progress', 'review'];
        $overdueOpen = Task::where('company_id', $companyId)
            ->whereIn('status', $openStates)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $completed = Task::where('company_id', $companyId)
            ->whereBetween('completed_at', [$fromEnd, $toEnd])
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get(['started_at', 'completed_at']);

        $avgCycleHours = 0.0;
        if ($completed->isNotEmpty()) {
            $sum = 0.0;
            foreach ($completed as $row) {
                try {
                    $sum += Carbon::parse($row->started_at)->floatDiffInHours(Carbon::parse($row->completed_at));
                } catch (\Throwable) {
                }
            }
            $avgCycleHours = round($sum / max(1, $completed->count()), 1);
        }

        return [
            'summary' => [
                'total_in_period' => (int) $statusRows->sum('count'),
                'overdue_open' => (int) $overdueOpen,
                'completed_in_period' => (int) $completed->count(),
                'avg_cycle_hours' => $avgCycleHours,
            ],
            'by_status' => $statusRows->map(fn($r) => ['status' => (string) $r->status, 'count' => (int) $r->count])->values()->all(),
            'by_priority' => $priorityRows->map(fn($r) => ['priority' => (string) $r->priority, 'count' => (int) $r->count])->values()->all(),
        ];
    }
}
