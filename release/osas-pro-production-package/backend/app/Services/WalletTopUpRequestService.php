<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WalletTopUpPaymentMethod;
use App\Enums\WalletTopUpRequestStatus;
use App\Models\Customer;
use App\Models\WalletTopUpRequest;
use App\Support\Media\TenantUploadDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class WalletTopUpRequestService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly BillingModelPolicyService $billingModelPolicy,
    ) {}

    /**
     * @param  array{customer_id: int, target: string, amount: float, payment_method: string, reference_number?: ?string, notes_from_customer?: ?string}  $data
     */
    public function createRequest(int $companyId, ?int $branchId, int $requestedByUserId, array $data, ?UploadedFile $receipt): WalletTopUpRequest
    {
        $this->billingModelPolicy->assertPrepaidWalletTopUp($companyId);
        $this->assertCustomerBelongsToCompany($companyId, (int) $data['customer_id']);
        $this->assertNoOpenRequest($companyId, (int) $data['customer_id'], (string) $data['target']);

        $path = $receipt ? $this->storeReceipt($companyId, $receipt) : null;

        return WalletTopUpRequest::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => (int) $data['customer_id'],
            'requested_by' => $requestedByUserId,
            'target' => (string) $data['target'],
            'amount' => $data['amount'],
            'currency' => 'SAR',
            'payment_method' => WalletTopUpPaymentMethod::from((string) $data['payment_method']),
            'reference_number' => $data['reference_number'] ?? null,
            'receipt_path' => $path,
            'status' => WalletTopUpRequestStatus::Pending,
            'notes_from_customer' => $data['notes_from_customer'] ?? null,
        ]);
    }

    /**
     * @param  array{amount?: float, payment_method?: string, reference_number?: ?string, notes_from_customer?: ?string}  $data
     */
    public function updateReturnedRequest(WalletTopUpRequest $req, array $data, ?UploadedFile $receipt): WalletTopUpRequest
    {
        $this->billingModelPolicy->assertPrepaidWalletTopUp((int) $req->company_id);
        if ($req->status !== WalletTopUpRequestStatus::ReturnedForRevision) {
            throw new \DomainException('يمكن تعديل الطلب فقط عند إرجاعه للتعديل.');
        }

        $updates = [];
        if (array_key_exists('amount', $data)) {
            $updates['amount'] = $data['amount'];
        }
        if (array_key_exists('payment_method', $data) && $data['payment_method'] !== null) {
            $updates['payment_method'] = WalletTopUpPaymentMethod::from((string) $data['payment_method']);
        }
        if (array_key_exists('reference_number', $data)) {
            $updates['reference_number'] = $data['reference_number'];
        }
        if (array_key_exists('notes_from_customer', $data)) {
            $updates['notes_from_customer'] = $data['notes_from_customer'];
        }

        if ($receipt) {
            $this->deleteReceiptIfExists($req->receipt_path);
            $updates['receipt_path'] = $this->storeReceipt($req->company_id, $receipt);
        }

        if ($updates !== []) {
            $req->update($updates);
        }

        return $req->fresh();
    }

    public function resubmit(WalletTopUpRequest $req): WalletTopUpRequest
    {
        $this->billingModelPolicy->assertPrepaidWalletTopUp((int) $req->company_id);
        if ($req->status !== WalletTopUpRequestStatus::ReturnedForRevision) {
            throw new \DomainException('إعادة الإرسال متاحة فقط للطلبات المُرجعة للتعديل.');
        }

        $req->update([
            'status' => WalletTopUpRequestStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return $req->fresh();
    }

    public function approve(WalletTopUpRequest $req, int $reviewerUserId, ?string $reviewNote = null): WalletTopUpRequest
    {
        return DB::transaction(function () use ($req, $reviewerUserId, $reviewNote) {
            /** @var WalletTopUpRequest $locked */
            $locked = WalletTopUpRequest::withoutGlobalScope('tenant')
                ->where('company_id', $req->company_id)
                ->lockForUpdate()
                ->findOrFail($req->id);

            if ($locked->status === WalletTopUpRequestStatus::Approved && $locked->approved_wallet_transaction_id) {
                Log::info('wallet_top_up_request.approve.idempotent_replay', [
                    'request_id' => $locked->id,
                    'wallet_transaction_id' => $locked->approved_wallet_transaction_id,
                ]);

                return $locked;
            }

            if ($locked->status !== WalletTopUpRequestStatus::Pending) {
                throw new \DomainException('اعتماد الطلب مسموح فقط عندما تكون الحالة «قيد الانتظار».');
            }

            $this->billingModelPolicy->assertPrepaidWalletTopUp((int) $locked->company_id);

            $traceId = trim((string) (app('trace_id') ?? '')) ?: (string) Str::uuid();
            $idemKey = 'wallet_top_up_approve:'.$locked->uuid;
            $note = trim(
                'اعتماد طلب شحن محفظة — طلب #'.$locked->uuid
                .($reviewNote ? ' — '.$reviewNote : '')
            );

            $refType = WalletTopUpRequest::class;
            $refId = $locked->id;

            if ($locked->target === 'fleet') {
                $txn = $this->walletService->topUpFleet(
                    companyId: $locked->company_id,
                    customerId: $locked->customer_id,
                    vehicleId: null,
                    amount: (float) $locked->amount,
                    invoiceId: null,
                    paymentId: null,
                    userId: $reviewerUserId,
                    traceId: $traceId,
                    idempotencyKey: $idemKey,
                    branchId: $locked->branch_id,
                    notes: $note,
                    referenceType: $refType,
                    referenceId: $refId,
                );
            } else {
                $txn = $this->walletService->topUpIndividual(
                    companyId: $locked->company_id,
                    customerId: $locked->customer_id,
                    vehicleId: null,
                    amount: (float) $locked->amount,
                    invoiceId: null,
                    paymentId: null,
                    userId: $reviewerUserId,
                    traceId: $traceId,
                    idempotencyKey: $idemKey,
                    branchId: $locked->branch_id,
                    notes: $note,
                    referenceType: $refType,
                    referenceId: $refId,
                );
            }

            $locked->update([
                'status' => WalletTopUpRequestStatus::Approved,
                'reviewed_by' => $reviewerUserId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNote,
                'approved_wallet_transaction_id' => $txn->id,
            ]);

            Log::info('wallet_top_up_request.approved', [
                'financial_operation' => true,
                'request_id' => $locked->id,
                'wallet_transaction_id' => $txn->id,
                'amount' => (float) $locked->amount,
                'reviewer_id' => $reviewerUserId,
                'company_id' => $locked->company_id,
            ]);

            return $locked->fresh();
        });
    }

    public function reject(WalletTopUpRequest $req, int $reviewerUserId, string $reviewNotes): WalletTopUpRequest
    {
        $this->billingModelPolicy->assertTenantMayOperate((int) $req->company_id);
        if ($req->status !== WalletTopUpRequestStatus::Pending) {
            throw new \DomainException('رفض الطلب مسموح فقط عندما تكون الحالة «قيد الانتظار».');
        }

        $req->update([
            'status' => WalletTopUpRequestStatus::Rejected,
            'reviewed_by' => $reviewerUserId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        Log::info('wallet_top_up_request.rejected', [
            'request_id' => $req->id,
            'reviewer_id' => $reviewerUserId,
            'company_id' => $req->company_id,
        ]);

        return $req->fresh();
    }

    public function returnForRevision(WalletTopUpRequest $req, int $reviewerUserId, string $reviewNotes): WalletTopUpRequest
    {
        $this->billingModelPolicy->assertTenantMayOperate((int) $req->company_id);
        if ($req->status !== WalletTopUpRequestStatus::Pending) {
            throw new \DomainException('إرجاع الطلب للتعديل مسموح فقط عندما تكون الحالة «قيد الانتظار».');
        }

        $req->update([
            'status' => WalletTopUpRequestStatus::ReturnedForRevision,
            'reviewed_by' => $reviewerUserId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        Log::info('wallet_top_up_request.returned_for_revision', [
            'request_id' => $req->id,
            'reviewer_id' => $reviewerUserId,
            'company_id' => $req->company_id,
        ]);

        return $req->fresh();
    }

    private function assertCustomerBelongsToCompany(int $companyId, int $customerId): void
    {
        $ok = Customer::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->where('id', $customerId)
            ->exists();
        if (! $ok) {
            throw new \DomainException('العميل غير موجود أو لا يتبع شركتك.');
        }
    }

    private function assertNoOpenRequest(int $companyId, int $customerId, string $target): void
    {
        $exists = WalletTopUpRequest::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('target', $target)
            ->whereIn('status', [
                WalletTopUpRequestStatus::Pending->value,
                WalletTopUpRequestStatus::ReturnedForRevision->value,
            ])
            ->exists();

        if ($exists) {
            throw new \DomainException(
                'يوجد بالفعل طلب شحن مفتوح (قيد المراجعة أو مُرجع للتعديل) لهذا العميل ونوع المحفظة. أكمله أو انتظر المراجعة.'
            );
        }
    }

    private function storeReceipt(int $companyId, UploadedFile $file): string
    {
        $dir = 'companies/'.$companyId.'/wallet-top-up-receipts/'.now()->format('Y/m');

        return $file->store($dir, TenantUploadDisk::name());
    }

    private function deleteReceiptIfExists(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $disk = TenantUploadDisk::name();
        if (Storage::disk($disk)->exists($relativePath)) {
            Storage::disk($disk)->delete($relativePath);
        }
    }
}
