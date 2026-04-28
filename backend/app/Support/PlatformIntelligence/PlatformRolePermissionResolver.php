<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence;

use App\Models\User;
use App\Services\Platform\PlatformPermissionService;
use App\Support\SaasPlatformAccess;

/**
 * Expands {@see config('platform_roles.roles')} into concrete permission keys for SPA + bootstrap.
 */
final class PlatformRolePermissionResolver
{
    public function __construct(
        private readonly PlatformPermissionService $platformPermissionService,
    ) {}

    /**
     * @return list<string>
     */
    public function platformPermissionGrantsForUser(?User $user): array
    {
        if ($user === null || ! SaasPlatformAccess::isPlatformOperator($user)) {
            return [];
        }

        if (! (bool) config('platform.admin_enabled', true)) {
            return [];
        }

        $role = $this->platformPermissionService->readEffectivePlatformRole($user);
        if ($role === null || $role === '') {
            return [];
        }

        /** @var array<string, list<string>> $roles */
        $roles = (array) config('platform_roles.roles', []);
        /** @var list<string>|null $grants */
        $grants = $roles[$role] ?? null;
        if ($grants === null || $grants === []) {
            return [];
        }

        if (in_array('*', $grants, true)) {
            /** @var list<string> $all */
            $all = array_values((array) config('platform_permissions.all', []));

            return $all;
        }

        return array_values($grants);
    }
}
