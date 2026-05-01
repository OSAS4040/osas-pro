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

        $result = array_merge($defaults, $custom);

        foreach (config('tenant_features.platform_execution_partner_company_ids', []) as $id) {
            if ((int) $company->id === (int) $id) {
                $result['platform_execution_partner'] = true;
                break;
            }
        }

        $emails = config('tenant_features.platform_execution_partner_company_emails', []);
        if ($emails !== []) {
            $email = strtolower(trim((string) ($company->email ?? '')));
            if ($email !== '' && in_array($email, $emails, true)) {
                $result['platform_execution_partner'] = true;
            }
        }

        return $result;
    }

    public static function isEnabled(Company $company, string $key): bool
    {
        $matrix = self::effectiveMatrix($company);

        return ($matrix[$key] ?? false) === true;
    }

    /** شريك تنفيذ للمنصة — فصل فواتير العميل عن واجهة الورشة. */
    public static function platformExecutionPartner(Company $company): bool
    {
        return (self::effectiveMatrix($company)['platform_execution_partner'] ?? false) === true;
    }

    /**
     * نفس {@see platformExecutionPartner} لمعرّف شركة — لبوابات الاشتراك دون تمرير نموذج محمّل مسبقاً.
     */
    public static function isPlatformExecutionPartnerTenant(int $companyId): bool
    {
        if ($companyId < 1) {
            return false;
        }

        $company = Company::query()->find($companyId);

        return $company !== null && self::platformExecutionPartner($company);
    }
}
