<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlatformPricingRequestStatus;
use App\Models\PlatformCustomerPriceVersion;

/**
 * يتحقق من وجود نسخة أسعار مرجعية صالحة قبل السماح بتسعير/إنشاء أوامر عمل معتمدة على الكتالوج فقط.
 */
final class PlatformPricingApprovalGateService
{
    public function hasApprovedActiveReference(int $companyId, int $customerId): bool
    {
        return PlatformCustomerPriceVersion::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('is_reference', true)
            ->whereNotNull('activated_at')
            ->where(static function ($q): void {
                $q->whereNull('platform_pricing_request_id')
                    ->orWhereHas('pricingRequest', static function ($rq): void {
                        $rq->where('status', PlatformPricingRequestStatus::Approved)
                            ->whereNotNull('approved_at');
                    });
            })
            ->exists();
    }
}
