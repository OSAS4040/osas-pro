<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\User;
use App\Support\PlatformIntelligence\PlatformIntelligenceCapability;
use App\Support\PlatformIntelligence\PlatformOperatorPermissionMatrix;
use App\Support\SaasPlatformAccess;

/**
 * IAM authority for platform scope — use for all platform permission checks (not raw role compares).
 */
final class PlatformPermissionService
{
    public function isSuperAdmin(?User $user): bool
    {
        return $this->effectivePlatformRole($user) === 'super_admin';
    }

    /**
     * Resolved {@see User::$platform_role} for grant lookup (same rules as permission checks).
     */
    public function readEffectivePlatformRole(?User $user): ?string
    {
        return $this->effectivePlatformRole($user);
    }

    public function intelligenceCapabilityGranted(?User $user, PlatformIntelligenceCapability $capability): bool
    {
        $key = PlatformOperatorPermissionMatrix::permissionFor($capability);

        return $this->hasPermission($user, $key);
    }

    public function hasPermission(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->company_id !== null && ! ((bool) ($user->is_platform_user ?? false))) {
            return false;
        }

        if (! (bool) config('platform.admin_enabled', true)) {
            return false;
        }

        if (! SaasPlatformAccess::isPlatformOperator($user)) {
            return false;
        }

        $role = $this->effectivePlatformRole($user);
        if ($role === null || $role === '') {
            return false;
        }

        /** @var array<string, list<string>> $roles */
        $roles = (array) config('platform_roles.roles', []);
        /** @var list<string>|null $grants */
        $grants = $roles[$role] ?? null;
        if ($grants === null || $grants === []) {
            return false;
        }

        if (in_array('*', $grants, true)) {
            return true;
        }

        return in_array($permission, $grants, true);
    }

    /**
     * Global plan catalog (non-tenant): tenant bypass flag handled here first.
     */
    public function canManageGlobalPlanCatalog(?User $user): bool
    {
        if (config('saas.allow_tenant_plan_catalog_edit', false)) {
            return true;
        }

        return $this->hasPermission($user, 'platform.catalog.manage');
    }

    private function effectivePlatformRole(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->company_id !== null && ! ((bool) ($user->is_platform_user ?? false))) {
            return null;
        }

        $raw = $user->getRawOriginal('platform_role');
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        if (SaasPlatformAccess::isPlatformOperator($user)) {
            return (string) config('platform.default_role_for_whitelist', 'platform_admin');
        }

        return null;
    }
}
