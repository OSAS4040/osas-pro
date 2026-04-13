<?php

namespace App\Services\WorkOrderPricing;

use App\Enums\WorkOrderPricingSource;

/**
 * نتيجة موحّدة لخدمة التسعير المركزية (قيم غير قابلة للتعديل من الواجهة).
 */
final class ResolvedWorkOrderLinePrice
{
    public function __construct(
        public readonly float $unitPrice,
        public readonly float $taxRate,
        public readonly WorkOrderPricingSource $source,
        public readonly ?int $policyId,
        public readonly string $resolutionLevel,
        public readonly ?string $notes = null,
        public readonly ?int $contractServiceItemId = null,
    ) {}
}
