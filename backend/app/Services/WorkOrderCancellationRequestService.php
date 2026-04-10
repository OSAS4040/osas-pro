<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WorkOrderCancellationRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\SupportTicket;
use App\Models\WalletTransaction;
use App\Models\WorkOrder;
use App\Models\WorkOrderCancellationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class WorkOrderCancellationRequestService
{
    public function __construct(
        private readonly WorkOrderService $workOrderService,
        private readonly BillingModelPolicyService $billingModelPolicy,
        private readonly CompanyReceivableService $receivableService,
        private readonly WalletService $walletService,
    ) {}

    public function submit(WorkOrder $workOrder, int $userId, string $reason): WorkOrderCancellationRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('سبب طلب الإلغاء إلزامي.');
        }

        return DB::transaction(function () use ($workOrder, $userId, $reason) {
            $wo = WorkOrder::where('id', $workOrder->id)->lockForUpdate()->firstOrFail();

            if (! in_array($wo->status, [
                WorkOrderStatus::Approved,
                WorkOrderStatus::InProgress,
                WorkOrderStatus::OnHold,
            ], true)) {
                throw new \DomainException('طلب الإلغاء الرسمي متاح فقط لأوامر معتمدة أو قيد التنفيذ أو المعلّقة.');
            }

            $this->billingModelPolicy->assertTenantMayOperate((int) $wo->company_id);

            $dup = WorkOrderCancellationRequest::query()
                ->where('work_order_id', $wo->id)
                ->where('status', WorkOrderCancellationRequestStatus::Pending)
                ->exists();
            if ($dup) {
                throw new \DomainException('يوجد بالفعل طلب إلغاء قيد المراجعة لهذا الأمر.');
            }

            $restoration = $wo->status->value;

            $req = WorkOrderCancellationRequest::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $wo->company_id,
                'work_order_id' => $wo->id,
                'requested_by_user_id' => $userId,
                'reason' => $reason,
                'status' => WorkOrderCancellationRequestStatus::Pending,
                'restoration_work_order_status' => $restoration,
            ]);

            $ticket = SupportTicket::create([
                'uuid' => (string) Str::uuid(),
                'ticket_number' => SupportTicket::generateTicketNumber(),
                'company_id' => $wo->company_id,
                'branch_id' => $wo->branch_id,
                'created_by' => $userId,
                'subject' => 'مراجعة طلب إلغاء أمر عمل #'.$wo->order_number,
                'description' => $reason."\n\nمرجع طلب الإلغاء: {$req->uuid}\nأمر العمل: #{$wo->id}",
                'category' => 'operational',
                'priority' => 'high',
                'status' => 'open',
                'channel' => 'internal',
                'source_module' => 'work_order_cancellation',
                'source_id' => $req->id,
                'is_private' => true,
            ]);

            $req->update(['support_ticket_id' => $ticket->id]);

            $this->workOrderService->applySystemStatusChange($wo, WorkOrderStatus::CancellationRequested);

            Log::info('work_order.cancellation_request.submitted', [
                'work_order_id' => $wo->id,
                'cancellation_request_id' => $req->id,
                'support_ticket_id' => $ticket->id,
                'trace_id' => app('trace_id'),
            ]);

            return $req->fresh();
        });
    }

    public function approve(WorkOrderCancellationRequest $request, int $reviewerUserId, ?string $note = null): WorkOrderCancellationRequest
    {
        return DB::transaction(function () use ($request, $reviewerUserId, $note) {
            $locked = WorkOrderCancellationRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== WorkOrderCancellationRequestStatus::Pending) {
                throw new \DomainException('طلب الإلغاء ليس قيد المراجعة.');
            }

            $wo = WorkOrder::withoutGlobalScopes()
                ->where('id', $locked->work_order_id)
                ->where('company_id', $locked->company_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($wo->status !== WorkOrderStatus::CancellationRequested) {
                throw new \DomainException('حالة أمر العمل لا تسمح باعتماد الإلغاء.');
            }

            $this->billingModelPolicy->assertTenantMayOperate((int) $wo->company_id);

            $isCreditTenant = false;
            try {
                $this->billingModelPolicy->assertCreditOperations((int) $wo->company_id);
                $isCreditTenant = true;
            } catch (\DomainException) {
            }

            if ($isCreditTenant) {
                $charge = \App\Models\CompanyReceivableLedger::query()
                    ->where('company_id', $wo->company_id)
                    ->where('work_order_id', $wo->id)
                    ->where('entry_type', \App\Enums\CompanyReceivableEntryType::Charge)
                    ->orderByDesc('id')
                    ->first();
                if ($charge && bccomp((string) $charge->amount, '0', 4) > 0) {
                    $this->receivableService->recordReversalForWorkOrder(
                        $wo,
                        (string) $charge->amount,
                        (string) ($note ?? 'cancellation_approved'),
                        'wo_credit_reversal:'.$locked->uuid,
                    );
                }
            } else {
                try {
                    $this->billingModelPolicy->assertPrepaidWalletTopUp((int) $wo->company_id);
                    if ($wo->invoice_id) {
                        $traceId = app()->bound('trace_id') && app('trace_id') !== null
                            ? (string) app('trace_id')
                            : (string) Str::uuid();
                        $debits = WalletTransaction::query()
                            ->where('company_id', $wo->company_id)
                            ->where('invoice_id', $wo->invoice_id)
                            ->where('type', WalletTransactionType::InvoiceDebit)
                            ->where('payment_mode', 'prepaid')
                            ->orderBy('id')
                            ->get();
                        foreach ($debits as $orig) {
                            $wallet = CustomerWallet::query()->find($orig->customer_wallet_id);
                            if (! $wallet) {
                                continue;
                            }
                            try {
                                $this->walletService->reverse(
                                    (int) $wo->company_id,
                                    (int) $wallet->customer_id,
                                    $orig->vehicle_id !== null ? (int) $orig->vehicle_id : null,
                                    (float) $orig->amount,
                                    $orig->invoice_id !== null ? (int) $orig->invoice_id : null,
                                    $orig->payment_id !== null ? (int) $orig->payment_id : null,
                                    $reviewerUserId,
                                    $traceId,
                                    'wo_cancel_inv_debit_rev:'.$locked->uuid.':'.$orig->id,
                                    $orig->branch_id !== null ? (int) $orig->branch_id : null,
                                    $note ?? 'work_order_cancellation',
                                    (int) $orig->id,
                                );
                            } catch (\DomainException $e) {
                                $msg = strtolower($e->getMessage());
                                if (str_contains($msg, 'already reversed')
                                    || str_contains($msg, 'duplicate idempotency')) {
                                    continue;
                                }
                                throw $e;
                            }
                        }
                    }
                } catch (\DomainException) {
                    // not a prepaid wallet tenant or no wallet debits for this invoice
                }
            }

            if ($wo->invoice_id) {
                $inv = Invoice::query()->where('id', $wo->invoice_id)->lockForUpdate()->first();
                if ($inv) {
                    $inv->update(['status' => InvoiceStatus::Cancelled]);
                }
            }

            $this->workOrderService->applySystemStatusChange($wo, WorkOrderStatus::Cancelled);

            $locked->update([
                'status' => WorkOrderCancellationRequestStatus::Approved,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at' => now(),
                'review_notes' => $note,
            ]);

            Log::info('work_order.cancellation_request.approved', [
                'cancellation_request_id' => $locked->id,
                'work_order_id' => $wo->id,
                'trace_id' => app('trace_id'),
            ]);

            return $locked->fresh();
        });
    }

    public function reject(WorkOrderCancellationRequest $request, int $reviewerUserId, string $reviewNotes): WorkOrderCancellationRequest
    {
        $reviewNotes = trim($reviewNotes);
        if ($reviewNotes === '') {
            throw new \DomainException('ملاحظات الرفض إلزامية.');
        }

        return DB::transaction(function () use ($request, $reviewerUserId, $reviewNotes) {
            $locked = WorkOrderCancellationRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== WorkOrderCancellationRequestStatus::Pending) {
                throw new \DomainException('طلب الإلغاء ليس قيد المراجعة.');
            }

            $wo = WorkOrder::withoutGlobalScopes()
                ->where('id', $locked->work_order_id)
                ->where('company_id', $locked->company_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($wo->status !== WorkOrderStatus::CancellationRequested) {
                throw new \DomainException('حالة أمر العمل لا تسمح برفض طلب الإلغاء.');
            }

            $restore = WorkOrderStatus::tryFrom($locked->restoration_work_order_status);
            if (! $restore) {
                throw new \DomainException('تعذر استعادة حالة أمر العمل الأصلية.');
            }

            $this->workOrderService->applySystemStatusChange($wo, $restore);

            $locked->update([
                'status' => WorkOrderCancellationRequestStatus::Rejected,
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
            ]);

            Log::info('work_order.cancellation_request.rejected', [
                'cancellation_request_id' => $locked->id,
                'work_order_id' => $wo->id,
                'trace_id' => app('trace_id'),
            ]);

            return $locked->fresh();
        });
    }
}
