<?php

namespace App\Support;

/** @see frontend/src/config/businessFeatureProfileDefaults.ts (يجب إبقاؤهما متطابقين) */
final class BusinessFeatureProfileDefaults
{
    /**
     * @return array<string, bool>
     */
    public static function featureMatrixForType(string $businessType): array
    {
        return match ($businessType) {
            'service_center' => [
                'operations' => true,
                'hr' => true,
                'finance' => true,
                'accounting' => true,
                /** واجهة الأصول الثابتة — تُفعَّل صراحةً عند جاهزية المنتج والخلفية */
                'fixed_assets' => false,
                'inventory' => true,
                'reports' => true,
                'intelligence' => true,
                'crm' => true,
                'fleet' => true,
                /** هيكل قطاع/قسم — يناسب مراكز الخدمة؛ يُعطّل في التجزئة افتراضياً */
                'org_structure' => true,
                /** عقود الموردين وتنبيهات الانتهاء — تشغيل/أسطول أكثر؛ تجزئة اختياري */
                'supplier_contract_mgmt' => true,
            ],
            'retail' => [
                'operations' => true,
                'hr' => true,
                'finance' => true,
                'accounting' => true,
                'fixed_assets' => false,
                'inventory' => true,
                'reports' => true,
                'intelligence' => false,
                'crm' => true,
                'fleet' => false,
                'org_structure' => false,
                'supplier_contract_mgmt' => false,
            ],
            'fleet_operator' => [
                'operations' => true,
                'hr' => true,
                'finance' => true,
                'accounting' => true,
                'fixed_assets' => false,
                'inventory' => false,
                'reports' => true,
                'intelligence' => true,
                'crm' => true,
                'fleet' => true,
                'org_structure' => true,
                'supplier_contract_mgmt' => true,
            ],
            default => self::featureMatrixForType('service_center'),
        };
    }
}
