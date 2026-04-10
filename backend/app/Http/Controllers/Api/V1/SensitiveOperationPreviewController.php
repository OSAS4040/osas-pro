<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\WorkOrder;
use App\Services\BillingModelPolicyService;
use App\Services\CreditLimitService;
use App\Services\SensitivePreviewTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pre-execution summary for sensitive / batch UI (client must still send real mutations with idempotency).
 */
class SensitiveOperationPreviewController extends Controller
{
    public function __construct(
        private readonly BillingModelPolicyService $billingModelPolicy,
        private readonly CreditLimitService $creditLimit,
        private readonly SensitivePreviewTokenService $previewTokens,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation' => 'required|string|max:64',
            'work_order_ids' => 'nullable|array',
            'work_order_ids.*' => 'integer',
            'lines' => 'nullable|array',
            'lines.*.customer_id' => 'required_with:lines|integer',
            'lines.*.vehicle_id' => 'required_with:lines|integer',
            'lines.*.items' => 'nullable|array',
        ]);

        $companyId = (int) $request->user()->company_id;
        $branchId = $request->user()->branch_id ? (int) $request->user()->branch_id : null;
        $userId = (int) $request->user()->id;

        $billing = $this->billingModelPolicy->snapshotForCompany($companyId);
        $warnings = [];

        try {
            $this->billingModelPolicy->assertTenantMayOperate($companyId);
        } catch (\DomainException $e) {
            $warnings[] = $e->getMessage();
        }

        $netExposure = null;
        $creditLimit = null;
        $isCredit = false;
        try {
            $this->billingModelPolicy->assertCreditOperations($companyId);
            $isCredit = true;
            $netExposure = $this->creditLimit->netOpenExposure($companyId);
            $co = Company::query()->find($companyId);
            $creditLimit = $co?->credit_limit !== null ? (string) $co->credit_limit : null;
        } catch (\DomainException) {
            // not credit
        }

        $batchFingerprint = null;
        if (($data['operation'] ?? '') === SensitivePreviewTokenService::OP_BATCH_CREATE) {
            $lines = $data['lines'] ?? [];
            if ($lines === [] || ! is_array($lines)) {
                return response()->json([
                    'message' => 'معاينة الدفعة تتطلب إرسال lines.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }
            $batchFingerprint = SensitivePreviewTokenService::fingerprintBatchLines($lines);
        }

        $vehicleCount = 0;
        $estimatedTotal = '0';
        $workOrderIds = array_map('intval', $data['work_order_ids'] ?? []);

        if ($workOrderIds !== []) {
            $orders = WorkOrder::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $workOrderIds)
                ->get();
            if ($orders->count() !== count($workOrderIds)) {
                $warnings[] = 'بعض أوامر العمل غير موجودة أو خارج نطاق شركتك.';
            }
            $vehicleCount = $orders->pluck('vehicle_id')->unique()->count();
            foreach ($orders as $o) {
                $estimatedTotal = bcadd($estimatedTotal, (string) $o->estimated_total, 4);
            }
        } elseif (($data['operation'] ?? '') === SensitivePreviewTokenService::OP_BATCH_CREATE && is_array($data['lines'] ?? null)) {
            $lines = $data['lines'];
            $vehicleCount = count(array_unique(array_map(
                static fn ($l) => (int) ($l['vehicle_id'] ?? 0),
                $lines
            )));
            foreach ($lines as $line) {
                $items = $line['items'] ?? [];
                if (! is_array($items)) {
                    continue;
                }
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $qty = (float) ($item['quantity'] ?? 0);
                    $price = (float) ($item['unit_price'] ?? 0);
                    $tax = (float) ($item['tax_rate'] ?? 15);
                    $lineSub = $qty * $price;
                    $lineTax = $lineSub * ($tax / 100);
                    $estimatedTotal = bcadd($estimatedTotal, (string) ($lineSub + $lineTax), 4);
                }
            }
        }

        $financialAfter = null;
        if ($isCredit && $netExposure !== null && bccomp($estimatedTotal, '0', 4) > 0) {
            $financialAfter = bcadd($netExposure, $estimatedTotal, 4);
            if ($creditLimit !== null && bccomp($financialAfter, $creditLimit, 4) > 0) {
                $warnings[] = 'تنبيه: المجموع المقدّر قد يتجاوز حد الائتمان بعد التنفيذ.';
            }
        }

        $company = Company::query()->find($companyId);

        $branchName = null;
        if ($branchId !== null) {
            $branchName = Branch::query()
                ->where('company_id', $companyId)
                ->where('id', $branchId)
                ->value('name');
        }

        $token = $this->previewTokens->issue(
            $companyId,
            $userId,
            (string) $data['operation'],
            $workOrderIds,
            $batchFingerprint,
        );

        return response()->json([
            'data' => [
                'operation' => $data['operation'],
                'sensitive_preview_token' => $token,
                'company' => [
                    'id' => $companyId,
                    'name' => $company?->name,
                ],
                'branch_id' => $branchId,
                'branch_name' => $branchName,
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                ],
                'billing' => $billing,
                'credit_net_receivable_exposure_before' => $netExposure,
                'credit_limit' => $creditLimit,
                'credit_net_receivable_exposure_after_estimate' => $financialAfter,
                'affected_vehicles_estimate' => $vehicleCount,
                'work_orders_estimated_total' => $estimatedTotal,
                'warnings' => $warnings,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
