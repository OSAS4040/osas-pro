<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompanyFinancialModel;
use App\Enums\CompanyFinancialModelStatus;
use App\Models\Company;
use Illuminate\Support\Facades\Log;

/**
 * Central gate for tenant financial operations vs platform-approved billing model.
 */
final class BillingModelPolicyService
{
    public function assertTenantMayOperate(int $companyId): Company
    {
        $company = Company::query()->where('id', $companyId)->firstOrFail();

        $st = $company->financial_model_status;
        if (! $st instanceof CompanyFinancialModelStatus) {
            throw new \DomainException('حالة النموذج المالي للشركة غير مهيأة.');
        }

        if (in_array($st, [CompanyFinancialModelStatus::Rejected, CompanyFinancialModelStatus::Suspended], true)) {
            throw new \DomainException('الشركة موقوفة أو مرفوضة من ناحية النموذج المالي — لا يمكن تنفيذ العمليات.');
        }

        if ($st === CompanyFinancialModelStatus::PendingPlatformReview) {
            throw new \DomainException('النموذج المالي للشركة قيد مراجعة المنصة — لا يمكن تنفيذ العمليات حتى الاعتماد.');
        }

        if (! in_array($st, [CompanyFinancialModelStatus::ApprovedPrepaid, CompanyFinancialModelStatus::ApprovedCredit], true)) {
            throw new \DomainException('النموذج المالي للشركة غير معتمد للتشغيل.');
        }

        return $company;
    }

    public function assertPrepaidWalletTopUp(int $companyId): Company
    {
        $company = $this->assertTenantMayOperate($companyId);

        if ($company->financial_model_status !== CompanyFinancialModelStatus::ApprovedPrepaid) {
            throw new \DomainException('طلبات شحن المحفظة متاحة فقط للشركات المعتمدة كنموذج شحن مسبق (prepaid).');
        }

        if ($company->financial_model !== CompanyFinancialModel::Prepaid) {
            throw new \DomainException('نوع النموذج المالي لا يسمح بشحن المحفظة.');
        }

        return $company;
    }

    public function assertCreditOperations(int $companyId): Company
    {
        $company = $this->assertTenantMayOperate($companyId);

        if ($company->financial_model_status !== CompanyFinancialModelStatus::ApprovedCredit) {
            throw new \DomainException('عمليات الائتمان متاحة فقط للشركات المعتمدة كنموذج ائتمان.');
        }

        if ($company->financial_model !== CompanyFinancialModel::Credit) {
            throw new \DomainException('نوع النموذج المالي لا يسمح بمسار الائتمان.');
        }

        return $company;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForCompany(int $companyId): array
    {
        $c = Company::query()->where('id', $companyId)->firstOrFail();

        return [
            'company_id' => $c->id,
            'financial_model' => $c->financial_model?->value,
            'financial_model_status' => $c->financial_model_status instanceof CompanyFinancialModelStatus
                ? $c->financial_model_status->value
                : (string) $c->financial_model_status,
            'credit_limit' => $c->credit_limit !== null ? (string) $c->credit_limit : null,
            'platform_financial_reviewed_at' => $c->platform_financial_reviewed_at?->toIso8601String(),
        ];
    }

    public function logGateDecision(string $action, int $companyId, bool $allowed, ?string $reason = null): void
    {
        Log::info('billing_model.policy', [
            'financial_operation_gate' => true,
            'action' => $action,
            'company_id' => $companyId,
            'allowed' => $allowed,
            'reason' => $reason,
            'trace_id' => app('trace_id'),
        ]);
    }
}
