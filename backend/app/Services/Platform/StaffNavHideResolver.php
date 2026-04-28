<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\UserRole;
use App\Models\PlatformTenantNavHide;
use App\Models\User;

final class StaffNavHideResolver
{
    /**
     * @return list<string>
     */
    public function hiddenKeysForStaffUser(User $user): array
    {
        if ($user->company_id === null || (int) $user->company_id <= 0) {
            return [];
        }
        $companyId = (int) $user->company_id;
        $userId = (int) $user->id;

        return PlatformTenantNavHide::query()
            ->where(function ($q) use ($companyId, $userId): void {
                $q->where(function ($q2) use ($companyId): void {
                    $q2->where('scope', 'company')->where('company_id', $companyId);
                })->orWhere(function ($q2) use ($userId): void {
                    $q2->where('scope', 'user')->where('user_id', $userId);
                });
            })
            ->pluck('nav_key')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function hiddenKeysForCustomerUser(User $user): array
    {
        if ($user->role !== UserRole::Customer) {
            return [];
        }
        if ($user->customer_id === null || (int) $user->customer_id <= 0) {
            return [];
        }

        return PlatformTenantNavHide::query()
            ->where('scope', 'customer')
            ->where('customer_id', (int) $user->customer_id)
            ->pluck('nav_key')
            ->unique()
            ->values()
            ->all();
    }
}
