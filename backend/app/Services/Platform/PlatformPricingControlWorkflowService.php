<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\PlatformPricingRequestStatus;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\PlatformCustomerPriceVersion;
use App\Models\PlatformPricingAuditLog;
use App\Models\PlatformPricingRequest;
use App\Models\PlatformPricingRequestLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PlatformPricingControlWorkflowService
{
    public function createDraft(User $actor, array $data): PlatformPricingRequest
    {
        return DB::transaction(function () use ($actor, $data) {
            $companyId = (int) $data['company_id'];
            $customerId = (int) $data['customer_id'];
            $this->assertCustomerBelongsToCompany($customerId, $companyId);

            $req = PlatformPricingRequest::query()->create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'status' => PlatformPricingRequestStatus::Draft,
                'title' => $data['title'] ?? null,
                'vehicle_types' => $data['vehicle_types'] ?? null,
                'created_by_user_id' => $actor->id,
                'version_no' => 1,
            ]);

            foreach ($data['lines'] as $line) {
                PlatformPricingRequestLine::query()->create([
                    'platform_pricing_request_id' => $req->id,
                    'service_code' => (string) $line['service_code'],
                    'tenant_service_id' => isset($line['tenant_service_id']) ? (int) $line['tenant_service_id'] : null,
                    'quantity' => (float) ($line['quantity'] ?? 1),
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            $this->audit($req, $actor, 'created', ['status' => $req->status->value]);

            return $req->fresh('lines');
        });
    }

    public function submitForReview(PlatformPricingRequest $req, User $actor): PlatformPricingRequest
    {
        return DB::transaction(function () use ($req, $actor) {
            $req->refresh();
            if (! in_array($req->status, [PlatformPricingRequestStatus::Draft, PlatformPricingRequestStatus::ReturnedForEdit], true)) {
                throw new \DomainException('يمكن إرسال الطلب للمراجعة فقط من حالة مسودة أو مُعاد للتعديل.');
            }
            $req->update(['status' => PlatformPricingRequestStatus::PendingReview]);
            $this->audit($req, $actor, 'submit_for_review', ['status' => $req->status->value]);

            return $req->fresh();
        });
    }

    public function beginReview(PlatformPricingRequest $req, User $actor): PlatformPricingRequest
    {
        return DB::transaction(function () use ($req, $actor) {
            $req->refresh();
            if ($req->status !== PlatformPricingRequestStatus::PendingReview) {
                throw new \DomainException('بدء المراجعة متاح فقط لطلبات «بانتظار المراجعة».');
            }
            $req->update([
                'status' => PlatformPricingRequestStatus::UnderReview,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);
            $this->audit($req, $actor, 'begin_review', ['status' => $req->status->value]);

            return $req->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $recommendation
     */
    public function completeReview(PlatformPricingRequest $req, User $actor, array $recommendation): PlatformPricingRequest
    {
        return DB::transaction(function () use ($req, $actor, $recommendation) {
            $req->refresh();
            if ($req->status !== PlatformPricingRequestStatus::UnderReview) {
                throw new \DomainException('إكمال التوصية متاح فقط أثناء «قيد المراجعة».');
            }
            $req->update([
                'status' => PlatformPricingRequestStatus::ReviewedRecommended,
                'review_payload' => $recommendation,
                'review_completed_at' => now(),
            ]);
            $this->audit($req, $actor, 'review_completed', ['status' => $req->status->value]);

            return $req->fresh();
        });
    }

    public function escalateToPlatformApproval(PlatformPricingRequest $req, User $actor): PlatformPricingRequest
    {
        return DB::transaction(function () use ($req, $actor) {
            $req->refresh();
            if ($req->status !== PlatformPricingRequestStatus::ReviewedRecommended) {
                throw new \DomainException('إرسال الطلب للاعتماد يتطلب إكمال مراجعة وتوصية أولاً.');
            }
            if ($req->review_completed_at === null) {
                throw new \DomainException('لا يوجد اعتماد بدون مراجعة مكتملة.');
            }
            $req->update([
                'status' => PlatformPricingRequestStatus::PendingPlatformApproval,
                'escalated_by_user_id' => $actor->id,
                'escalated_at' => now(),
            ]);
            $this->audit($req, $actor, 'escalate_to_approval', ['status' => $req->status->value]);

            return $req->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $sellSnapshot
     */
    public function approve(
        PlatformPricingRequest $req,
        User $actor,
        array $sellSnapshot,
        ?int $contractId = null,
    ): PlatformPricingRequest {
        return DB::transaction(function () use ($req, $actor, $sellSnapshot, $contractId) {
            $req->refresh();
            if ($req->status !== PlatformPricingRequestStatus::PendingPlatformApproval) {
                throw new \DomainException('الاعتماد متاح فقط لطلبات «بانتظار اعتماد المنصة».');
            }
            if ($req->review_completed_at === null || $req->review_payload === null) {
                throw new \DomainException('لا يوجد اعتماد بدون مراجعة مكتملة وتوصية مسجّلة.');
            }

            $req->update([
                'status' => PlatformPricingRequestStatus::Approved,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->activateNewPriceVersion($req, $sellSnapshot, $contractId);
            $this->audit($req, $actor, 'approved', ['status' => $req->status->value]);

            return $req->fresh();
        });
    }

    public function reject(PlatformPricingRequest $req, User $actor, string $reason): PlatformPricingRequest
    {
        return DB::transaction(function () use ($req, $actor, $reason) {
            $req->refresh();
            if ($req->status !== PlatformPricingRequestStatus::PendingPlatformApproval) {
                throw new \DomainException('الرفض متاح فقط أثناء «بانتظار اعتماد المنصة».');
            }
            $reason = trim($reason);
            if ($reason === '') {
                throw new \DomainException('سبب الرفض إلزامي.');
            }
            $req->update([
                'status' => PlatformPricingRequestStatus::Rejected,
                'rejection_reason' => $reason,
            ]);
            $this->audit($req, $actor, 'rejected', ['reason' => $reason]);

            return $req->fresh();
        });
    }

    public function returnForEdit(PlatformPricingRequest $req, User $actor, string $note): PlatformPricingRequest
    {
        return DB::transaction(function () use ($req, $actor, $note) {
            $req->refresh();
            if ($req->status !== PlatformPricingRequestStatus::PendingPlatformApproval) {
                throw new \DomainException('إعادة الطلب للتعديل متاحة فقط أثناء «بانتظار اعتماد المنصة».');
            }
            $req->update([
                'status' => PlatformPricingRequestStatus::ReturnedForEdit,
                'rejection_reason' => $note,
            ]);
            $this->audit($req, $actor, 'returned_for_edit', ['note' => $note]);

            return $req->fresh();
        });
    }

    private function assertCustomerBelongsToCompany(int $customerId, int $companyId): void
    {
        $ok = Customer::query()->where('id', $customerId)->where('company_id', $companyId)->exists();
        if (! $ok) {
            throw new \DomainException('العميل لا ينتمي إلى الشركة المحددة.');
        }
    }

    /**
     * @param  array<string, mixed>  $sellSnapshot
     */
    private function activateNewPriceVersion(PlatformPricingRequest $req, array $sellSnapshot, ?int $contractId): void
    {
        if ($contractId !== null) {
            $ok = Contract::query()->whereKey($contractId)->where('company_id', $req->company_id)->exists();
            if (! $ok) {
                throw new \DomainException('العقد غير موجود أو لا يتبع نفس الشركة المستهدفة.');
            }
        }

        $rootContractId = $contractId;

        PlatformCustomerPriceVersion::query()
            ->where('company_id', $req->company_id)
            ->where('customer_id', $req->customer_id)
            ->when($contractId !== null, fn ($q) => $q->where('contract_id', $contractId))
            ->when($contractId === null, fn ($q) => $q->whereNull('contract_id'))
            ->where('is_reference', true)
            ->update(['is_reference' => false]);

        $nextVersion = (int) PlatformCustomerPriceVersion::query()
            ->where('company_id', $req->company_id)
            ->where('customer_id', $req->customer_id)
            ->when($contractId !== null, fn ($q) => $q->where('contract_id', $contractId))
            ->when($contractId === null, fn ($q) => $q->whereNull('contract_id'))
            ->max('version_no') + 1;

        PlatformCustomerPriceVersion::query()->create([
            'company_id' => $req->company_id,
            'customer_id' => $req->customer_id,
            'contract_id' => $contractId,
            'root_contract_id' => $rootContractId,
            'platform_pricing_request_id' => $req->id,
            'version_no' => max(1, $nextVersion),
            'is_reference' => true,
            'sell_snapshot' => $sellSnapshot,
            'activated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(PlatformPricingRequest $req, User $actor, string $action, array $payload = []): void
    {
        PlatformPricingAuditLog::query()->create([
            'platform_pricing_request_id' => $req->id,
            'user_id' => $actor->id,
            'action' => $action,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
