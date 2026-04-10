<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * GET /dashboard/summary — read-only aggregates for the tenant dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());
        $companyId = (int) app('tenant_company_id');

        /* v2: يتضمن charts — تغيير المفتاح يبطل الكاش القديم */
        $cacheKey = "dashboard:summary:v2:{$companyId}:{$from}:{$to}";
        $ttl      = now()->diffInHours(now()->endOfDay()) < 2 ? 300 : 1800;

        $data = Cache::remember($cacheKey, $ttl, function () use ($companyId, $from, $to) {
            $totalRevenue = Invoice::where('company_id', $companyId)
                ->whereBetween('issued_at', [$from, $to])
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->sum('total');

            $totalCollected = Payment::where('company_id', $companyId)
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->where('status', 'completed')
                ->sum('amount');

            $newCustomers = Customer::where('company_id', $companyId)
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->count();

            $woCompleted = WorkOrder::where('company_id', $companyId)
                ->whereBetween('updated_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->where('status', 'completed')
                ->count();

            $woTotal = WorkOrder::where('company_id', $companyId)
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->count();

            $avgInvoiceValue = Invoice::where('company_id', $companyId)
                ->whereBetween('issued_at', [$from, $to])
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->avg('total');

            $openInvoices = Invoice::where('company_id', $companyId)
                ->whereIn('status', ['pending', 'partial_paid'])
                ->count();

            $totalOutstanding = Invoice::where('company_id', $companyId)
                ->whereIn('status', ['pending', 'partial_paid'])
                ->sum('due_amount');

            $walletTotals = [
                'cash'        => (float) CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'cash')->sum('balance'),
                'promotional' => (float) CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'promotional')->sum('balance'),
                'reserved'    => (float) CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'reserved')->sum('balance'),
                'credit'      => (float) CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'credit')->sum('balance'),
            ];

            $revenueLast7Days = [];
            $workOrdersLast7Days = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = now()->subDays($i)->toDateString();
                $revenueLast7Days[] = [
                    'date'    => $day,
                    'revenue' => round((float) Invoice::where('company_id', $companyId)
                        ->whereDate('issued_at', $day)
                        ->whereNotIn('status', ['cancelled', 'draft'])
                        ->sum('total'), 2),
                ];
                $workOrdersLast7Days[] = [
                    'date'  => $day,
                    'count' => WorkOrder::where('company_id', $companyId)
                        ->whereDate('created_at', $day)
                        ->count(),
                ];
            }

            return [
                'period' => ['from' => $from, 'to' => $to],
                'sales'  => [
                    'total_revenue'      => round((float) $totalRevenue, 2),
                    'total_collected'    => round((float) $totalCollected, 2),
                    'collection_rate'    => $totalRevenue > 0 ? round(((float) $totalCollected / (float) $totalRevenue) * 100, 1) : 0.0,
                    'avg_invoice_value'  => round((float) ($avgInvoiceValue ?? 0), 2),
                ],
                'receivables' => [
                    'open_invoice_count' => $openInvoices,
                    'total_outstanding'  => round((float) $totalOutstanding, 2),
                ],
                'customers' => [
                    'new_in_period' => $newCustomers,
                ],
                'work_orders' => [
                    'created_in_period' => $woTotal,
                    'completed_in_period' => $woCompleted,
                    'completion_rate'     => $woTotal > 0 ? round(($woCompleted / $woTotal) * 100, 1) : 0.0,
                ],
                'wallets' => [
                    'balance_by_type' => array_map(fn ($v) => round($v, 2), $walletTotals),
                ],
                'charts' => [
                    'revenue_last_7_days'     => $revenueLast7Days,
                    'work_orders_last_7_days' => $workOrdersLast7Days,
                ],
            ];
        });

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }
}
