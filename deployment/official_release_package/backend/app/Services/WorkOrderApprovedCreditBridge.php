<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

/**
 * When a work order becomes {@see WorkOrderStatus::Approved}, credit-mode companies
 * receive one invoice + one receivable line (idempotent per work order).
 */
final class WorkOrderApprovedCreditBridge
{
    public function __construct(
        private readonly BillingModelPolicyService $billingModelPolicy,
        private readonly InvoiceService $invoiceService,
        private readonly CompanyReceivableService $receivableService,
    ) {}

    public function onApproved(WorkOrder $workOrder, int $userId): void
    {
        try {
            $this->billingModelPolicy->assertCreditOperations((int) $workOrder->company_id);
        } catch (\DomainException $e) {
            $this->billingModelPolicy->logGateDecision('work_order.approved.skip_credit_bridge', (int) $workOrder->company_id, true, $e->getMessage());

            return;
        }

        $invoice = $this->invoiceService->issueFromApprovedCreditWorkOrder($workOrder, $userId, 'wo_credit_invoice:'.$workOrder->uuid);

        $this->receivableService->recordChargeForApprovedWorkOrder(
            $workOrder->fresh(),
            (string) $invoice->total,
            'wo_credit_receivable:'.$workOrder->uuid,
            $invoice->id,
        );

        Log::info('work_order.credit_bridge.approved', [
            'financial_operation' => true,
            'work_order_id' => $workOrder->id,
            'invoice_id' => $invoice->id,
            'trace_id' => app('trace_id'),
        ]);
    }
}
