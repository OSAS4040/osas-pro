<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Support;

use App\Models\Branch;
use App\Models\Company;

final class ResolveCompanyBillingBranch
{
    public function __invoke(Company $company): Branch
    {
        $branch = Branch::query()
            ->where('company_id', $company->id)
            ->where('is_main', true)
            ->first();

        if ($branch !== null) {
            return $branch;
        }

        $fallback = Branch::query()->where('company_id', $company->id)->orderBy('id')->first();
        if ($fallback === null) {
            throw new \DomainException('Company has no branch for billing documents.');
        }

        return $fallback;
    }
}
