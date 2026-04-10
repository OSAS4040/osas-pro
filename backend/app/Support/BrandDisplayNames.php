<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;

/**
 * Official trade names on PDFs/emails: أسس برو (AR) / Osas Pro (EN) — maps legacy seeded names.
 */
final class BrandDisplayNames
{
    /** @var list<string> */
    private const LEGACY_EN = ['osas platform', 'asas platform', 'asas pro'];

    /** @var list<string> */
    private const LEGACY_AR = ['منصة أواس', 'أسس'];

    public static function companyTradeNameAr(?Company $company): string
    {
        if ($company === null) {
            return '—';
        }
        if (self::isLegacyBrand($company)) {
            return 'أسس برو';
        }

        return trim((string) ($company->name_ar ?: $company->name)) ?: '—';
    }

    public static function companyTradeNameEn(?Company $company): string
    {
        if ($company === null) {
            return '—';
        }
        if (self::isLegacyBrand($company)) {
            return 'Osas Pro';
        }

        return trim((string) ($company->name ?: $company->name_ar)) ?: '—';
    }

    public static function isLegacyBrand(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }
        $en = strtolower(trim((string) $company->name));
        $ar = trim((string) $company->name_ar);

        return in_array($en, self::LEGACY_EN, true)
            || in_array($ar, self::LEGACY_AR, true);
    }
}
