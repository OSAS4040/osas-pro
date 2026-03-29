<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);

        $companyId = app('tenant_company_id');

        $summary = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$request->from, $request->to])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->selectRaw('COUNT(*) as invoice_count, SUM(total) as total_sales, SUM(tax_amount) as total_tax, SUM(discount_amount) as total_discount')
            ->first();

        $byBranch = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$request->from, $request->to])
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
        $companyId = app('tenant_company_id');

        $data = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$request->from, $request->to])
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

        $data = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.company_id', $companyId)
            ->whereBetween('invoices.issued_at', [$request->from, $request->to])
            ->whereNotIn('invoices.status', ['cancelled', 'draft'])
            ->selectRaw('products.name as product_name, products.sku, SUM(invoice_items.quantity) as total_qty, SUM(invoice_items.total) as total_sales')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_sales')
            ->limit(50)
            ->get();

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

        $summary = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$request->from, $request->to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $total = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$request->from, $request->to])
            ->count();

        return response()->json(['data' => compact('summary', 'total'), 'trace_id' => app('trace_id')]);
    }

    public function kpi(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');

        $cacheKey = "kpi:{$companyId}:{$request->from}:{$request->to}";
        $ttl = now()->diffInHours(now()->endOfDay()) < 2 ? 300 : 1800; // 5min if today, 30min otherwise

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, function () use ($companyId, $request) {
            $totalRevenue = Invoice::where('company_id', $companyId)
                ->whereBetween('issued_at', [$request->from, $request->to])
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->sum('total');

            $totalCollected = Payment::where('company_id', $companyId)
                ->whereBetween('created_at', [$request->from, $request->to])
                ->where('status', 'completed')
                ->sum('amount');

            $newCustomers = Customer::where('company_id', $companyId)
                ->whereBetween('created_at', [$request->from, $request->to])
                ->count();

            $woCompleted = WorkOrder::where('company_id', $companyId)
                ->whereBetween('updated_at', [$request->from, $request->to])
                ->where('status', 'completed')
                ->count();

            $woTotal = WorkOrder::where('company_id', $companyId)
                ->whereBetween('created_at', [$request->from, $request->to])
                ->count();

            $avgInvoiceValue = Invoice::where('company_id', $companyId)
                ->whereBetween('issued_at', [$request->from, $request->to])
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->avg('total');

            return [
                'total_revenue'      => round($totalRevenue, 2),
                'total_collected'    => round($totalCollected, 2),
                'collection_rate'    => $totalRevenue > 0 ? round(($totalCollected / $totalRevenue) * 100, 1) : 0,
                'new_customers'      => $newCustomers,
                'wo_completed'       => $woCompleted,
                'wo_total'           => $woTotal,
                'wo_completion_rate' => $woTotal > 0 ? round(($woCompleted / $woTotal) * 100, 1) : 0,
                'avg_invoice_value'  => round($avgInvoiceValue ?? 0, 2),
            ];
        });

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function vatReport(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->endOfMonth()->toDateString());
        $request->merge(['from' => $from, 'to' => $to]);
        $companyId = app('tenant_company_id');

        $data = Invoice::where('company_id', $companyId)
            ->whereBetween('issued_at', [$request->from, $request->to])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->selectRaw('
                COUNT(*) as invoice_count,
                SUM(subtotal) as taxable_amount,
                SUM(tax_amount) as vat_collected,
                SUM(total) as total_with_vat
            ')
            ->first();

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

        $payments = Payment::where('company_id', $companyId)
            ->whereBetween('created_at', [$request->from, $request->to])
            ->where('status', 'completed')
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('method')
            ->get();

        return response()->json([
            'data'     => ['payments_by_method' => $payments],
            'trace_id' => app('trace_id'),
        ]);
    }
}
