<?php

namespace App\Support;

use App\Models\Company;

/**
 * خصائص فعّالة لكل مستأجر — تدمج الافتراضي حسب نوع النشاط مع تخصيص المنشأة.
 * يضمن عدم فرض سيناريو (ورشة/أسطول/موردين) على منشأة نشاطها مختلف إلا إذا فعّلت الصلاحية صراحة أو يناسبها الافتراض.
 */
final class TenantBusinessFeatures
{
    /**
     * @return array<string, bool>
     */
    public static function effectiveMatrix(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $profile  = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];

        $businessType = (string) ($profile['business_type'] ?? 'service_center');
        $custom       = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];

        $defaults = BusinessFeatureProfileDefaults::featureMatrixForType($businessType);

        return array_merge($defaults, $custom);
    }

    public static function isEnabled(Company $company, string $key): bool
    {
        $matrix = self::effectiveMatrix($company);

        return ($matrix[$key] ?? false) === true;
    }
}
