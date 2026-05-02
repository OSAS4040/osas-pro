<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Support\TenantBusinessFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * GET /dashboard/summary — read-only aggregates for the tenant dashboard.
     * Query `refresh=1` bypasses cache (use after manual «تحديث» on the dashboard).
     */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->endOfMonth()->toDateString());
        $companyId = (int) app('tenant_company_id');

        $executionPartner = TenantBusinessFeatures::isPlatformExecutionPartnerTenant($companyId);

        /* v5: يفصل التخزين المؤقت لشركاء التنفيذ (بدون تجميعات فواتير/محافظ في الكاش) */
        $cacheKey = $executionPartner
            ? "dashboard:summary:v5:ep:{$companyId}:{$from}:{$to}"
            : "dashboard:summary:v5:{$companyId}:{$from}:{$to}";
        $ttl      = now()->diffInHours(now()->endOfDay()) < 2 ? 300 : 1800;

        $refresh = $request->boolean('refresh');

        $data = $refresh
            ? $this->buildDashboardSummaryPayload($companyId, $from, $to)
            : Cache::remember($cacheKey, $ttl, fn (): array => $this->buildDashboardSummaryPayload($companyId, $from, $to));

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardSummaryPayload(int $companyId, string $from, string $to): array
    {
        if (TenantBusinessFeatures::isPlatformExecutionPartnerTenant($companyId)) {
            return $this->buildExecutionPartnerDashboardSummaryPayload($companyId, $from, $to);
        }

        return $this->buildStandardDashboardSummaryPayload($companyId, $from, $to);
    }

    /**
     * ملخص لوحة التحكم لشركاء تنفيذ المنصّة — أوامر عمل فقط، بدون إيراد أو ذمم أو محافظ عملاء.
     *
     * @return array<string, mixed>
     */
    private function buildExecutionPartnerDashboardSummaryPayload(int $companyId, string $from, string $to): array
    {
        $fromDt = $from.' 00:00:00';
        $toDt = $to.' 23:59:59';

        $woCompleted = WorkOrder::where('company_id', $companyId)
            ->whereBetween('updated_at', [$fromDt, $toDt])
            ->where('status', 'completed')
            ->count();

        $woTotal = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->count();

        $woInProgressInPeriod = WorkOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$fromDt, $toDt])
            ->where('status', 'in_progress')
            ->count();

        $woDeliveredInPeriod = WorkOrder::where('company_id', $companyId)
            ->whereBetween('updated_at', [$fromDt, $toDt])
            ->where('status', 'delivered')
            ->count();

        $woCancelledInPeriod = WorkOrder::where('company_id', $companyId)
            ->whereBetween('updated_at', [$fromDt, $toDt])
            ->where('status', 'cancelled')
            ->count();

        $revenueLast7Days = [];
        $workOrdersLast7Days = [];
        $woLast7Created = 0;
        $woLast7Completed = 0;
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $revenueLast7Days[] = [
                'date' => $day,
                'revenue' => 0.0,
            ];
            $woDayCreated = WorkOrder::where('company_id', $companyId)
                ->whereDate('created_at', $day)
                ->count();
            $woDayCompleted = WorkOrder::where('company_id', $companyId)
                ->whereDate('updated_at', $day)
                ->where('status', 'completed')
                ->count();
            $workOrdersLast7Days[] = [
                'date' => $day,
                'count' => $woDayCreated,
            ];
            $woLast7Created += $woDayCreated;
            $woLast7Completed += $woDayCompleted;
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'sales' => [
                'total_revenue' => 0.0,
                'total_collected' => 0.0,
                'collection_rate' => 0.0,
                'avg_invoice_value' => 0.0,
            ],
            'receivables' => [
                'open_invoice_count' => 0,
                'total_outstanding' => 0.0,
            ],
            'customers' => [
                'new_in_period' => 0,
            ],
            'work_orders' => [
                'created_in_period' => $woTotal,
                'completed_in_period' => $woCompleted,
                'completion_rate' => $woTotal > 0 ? round(($woCompleted / $woTotal) * 100, 1) : 0.0,
                'created_last_7_days' => $woLast7Created,
                'completed_last_7_days' => $woLast7Completed,
                'completion_rate_last_7_days' => $woLast7Created > 0
                    ? round(($woLast7Completed / $woLast7Created) * 100, 1)
                    : 0.0,
                'in_progress_in_period' => $woInProgressInPeriod,
                'delivered_in_period' => $woDeliveredInPeriod,
                'cancelled_in_period' => $woCancelledInPeriod,
            ],
            'wallets' => [
                'balance_by_type' => [],
            ],
            'charts' => [
                'revenue_last_7_days' => $revenueLast7Days,
                'work_orders_last_7_days' => $workOrdersLast7Days,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStandardDashboardSummaryPayload(int $companyId, string $from, string $to): array
    {
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

        $walletTotals = CustomerWallet::totalsByTypeForCompany($companyId);

        $revenueLast7Days = [];
        $workOrdersLast7Days = [];
        $woLast7Created = 0;
        $woLast7Completed = 0;
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $revenueLast7Days[] = [
                'date'    => $day,
                'revenue' => round((float) Invoice::where('company_id', $companyId)
                    ->whereDate('issued_at', $day)
                    ->whereNotIn('status', ['cancelled', 'draft'])
                    ->sum('total'), 2),
            ];
            $woDayCreated = WorkOrder::where('company_id', $companyId)
                ->whereDate('created_at', $day)
                ->count();
            $woDayCompleted = WorkOrder::where('company_id', $companyId)
                ->whereDate('updated_at', $day)
                ->where('status', 'completed')
                ->count();
            $workOrdersLast7Days[] = [
                'date'  => $day,
                'count' => $woDayCreated,
            ];
            $woLast7Created += $woDayCreated;
            $woLast7Completed += $woDayCompleted;
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
                'created_last_7_days' => $woLast7Created,
                'completed_last_7_days' => $woLast7Completed,
                'completion_rate_last_7_days' => $woLast7Created > 0
                    ? round(($woLast7Completed / $woLast7Created) * 100, 1)
                    : 0.0,
            ],
            'wallets' => [
                'balance_by_type' => array_map(fn ($v) => round($v, 2), $walletTotals),
            ],
            'charts' => [
                'revenue_last_7_days'     => $revenueLast7Days,
                'work_orders_last_7_days' => $workOrdersLast7Days,
            ],
        ];
    }
}
