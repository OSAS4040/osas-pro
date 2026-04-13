<?php

namespace App\Enums;

/**
 * مصدر السعر المحفوظ في سطر أمر العمل (لقطـة وقت الإنشاء).
 */
enum WorkOrderPricingSource: string
{
    case CustomerSpecific = 'customer_specific';
    case CustomerGroup = 'customer_group';
    case Contract = 'contract';
    case GeneralPolicy = 'general_policy';
    case GeneralServiceBase = 'general_service_base';

    public function labelAr(): string
    {
        return match ($this) {
            self::CustomerSpecific => 'سعر خاص للعميل',
            self::CustomerGroup => 'سعر مجموعة العملاء',
            self::Contract => 'سعر عقد / اتفاقية',
            self::GeneralPolicy => 'سعر عام (سياسة)',
            self::GeneralServiceBase => 'السعر العام للخدمة',
        };
    }
}
