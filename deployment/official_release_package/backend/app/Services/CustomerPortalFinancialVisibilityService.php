<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\TenantBusinessFeatures;

/**
 * يتحكم في إظهار الأرقام المالية في بوابة العميل (تقارير/ملخصات).
 * عند الإخفاء يطبّق المتحكم إخفاءً جزئياً للحقول النقدية مع الإبقاء على الأعداد غير المالية.
 */
final class CustomerPortalFinancialVisibilityService
{
    public function canViewFinancialData(User $user): bool
    {
        if (! $user->is_platform_user) {
            return false;
        }

        $company = $user->company;
        if ($company instanceof Company && TenantBusinessFeatures::platformExecutionPartner($company)) {
            return false;
        }

        return true;
    }
}
