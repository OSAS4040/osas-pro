<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\LoginGuardHint;
use App\Enums\LoginPrincipalKind;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Auth\LoginAccountContext;
use App\Support\Auth\LoginContextResolution;
use App\Support\SaasPlatformAccess;

/**
 * WAVE 1 / PR2 — Runs {@see ResolveLoginEligibilityAction} then builds {@see LoginAccountContext}.
 * Does not repeat eligibility rules; does not evaluate company/subscription (caller middleware).
 */
final class ResolveLoginContextAction
{
    public function __construct(
        private readonly ResolveLoginEligibilityAction $resolveLoginEligibility,
    ) {}

    /**
     * @param  list<string>|null  $permissions  Permissions snapshot (e.g. from role); computed from role if null.
     */
    public function __invoke(User $user, ?array $permissions = null): LoginContextResolution
    {
        $eligibility = ($this->resolveLoginEligibility)($user);
        if (! $eligibility->allowed) {
            return new LoginContextResolution($eligibility, null);
        }

        $roleRaw = (string) $user->getRawOriginal('role');
        $role = UserRole::tryFrom($roleRaw) ?? null;
        $permissions ??= $this->permissionsForRoleKey($roleRaw);

        $isPlatformPrincipal = SaasPlatformAccess::isPlatformOperator($user);

        [$principalKind, $guardHint, $homeRouteHint, $requiresSelection] = $this->resolveRoutingHints(
            $user,
            $role,
            $isPlatformPrincipal,
            $permissions,
        );

        $platformRole = null;
        if ($isPlatformPrincipal) {
            $pr = $user->getRawOriginal('platform_role');
            $platformRole = is_string($pr) && $pr !== ''
                ? $pr
                : (string) config('platform.default_role_for_whitelist', 'platform_admin');
        }

        $context = new LoginAccountContext(
            principalKind: $principalKind,
            userId: (int) $user->id,
            companyId: $user->company_id !== null ? (int) $user->company_id : null,
            customerId: $user->customer_id !== null ? (int) $user->customer_id : null,
            homeRouteHint: $homeRouteHint,
            guardHint: $guardHint,
            role: $roleRaw,
            requiresContextSelection: $requiresSelection,
            displayContext: [
                'has_company' => $user->company_id !== null,
            ],
            platformRole: $platformRole,
        );

        return new LoginContextResolution($eligibility, $context);
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: LoginPrincipalKind, 1: LoginGuardHint, 2: string, 3: bool}
     */
    private function resolveRoutingHints(User $user, ?UserRole $role, bool $isPlatformPrincipal, array $permissions): array
    {
        if ($isPlatformPrincipal) {
            if ($user->company_id !== null) {
                $home = $this->homeRouteHintFromPermissions($permissions);

                return [LoginPrincipalKind::PlatformEmployee, LoginGuardHint::Staff, $home, false];
            }

            return [LoginPrincipalKind::PlatformEmployee, LoginGuardHint::Platform, '/admin', false];
        }

        if ($user->company_id === null) {
            if ($role === UserRole::PhoneOnboarding) {
                return [LoginPrincipalKind::Unknown, LoginGuardHint::Onboarding, '/phone/onboarding', false];
            }

            return [LoginPrincipalKind::Unknown, LoginGuardHint::Unknown, '/login', false];
        }

        if ($role !== null && $role->isFleetSide()) {
            return [LoginPrincipalKind::CustomerUser, LoginGuardHint::Fleet, '/fleet-portal', false];
        }

        if ($role !== null && $role->isCustomer()) {
            return [LoginPrincipalKind::CustomerUser, LoginGuardHint::Customer, '/customer/dashboard', false];
        }

        if ($role !== null && $role->isWorkshopSide()) {
            $home = $this->homeRouteHintFromPermissions($permissions);

            return [LoginPrincipalKind::TenantUser, LoginGuardHint::Staff, $home, false];
        }

        return [LoginPrincipalKind::Unknown, LoginGuardHint::Unknown, '/', false];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function homeRouteHintFromPermissions(array $permissions): string
    {
        $permSet = array_fill_keys($permissions, true);
        $modules = (array) config('mobile_bootstrap.modules', []);
        $enabled = [];

        foreach ($modules as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            foreach ((array) ($row['requires_any'] ?? []) as $p) {
                if (isset($permSet[(string) $p])) {
                    $enabled[] = $id;
                    break;
                }
            }
        }

        $set = array_fill_keys($enabled, true);
        foreach ((array) config('mobile_bootstrap.home_screen_priority', []) as $id) {
            if (isset($set[(string) $id])) {
                return $this->moduleIdToRouteHint((string) $id);
            }
        }

        return '/';
    }

    private function moduleIdToRouteHint(string $moduleId): string
    {
        return match ($moduleId) {
            'dashboard' => '/',
            'pos' => '/pos',
            'work_orders' => '/work-orders',
            'invoices' => '/invoices',
            'vehicles' => '/vehicles',
            'customers' => '/customers',
            'inventory' => '/inventory',
            'fleet' => '/fleet-portal',
            default => '/',
        };
    }

    /**
     * @return list<string>
     */
    private function permissionsForRoleKey(string $roleKey): array
    {
        $permissions = config('permissions.roles', []);
        $perms = $permissions[$roleKey] ?? [];

        if (in_array('*', $perms, true)) {
            return array_values(config('permissions.all_permissions', []));
        }

        return is_array($perms) ? array_values($perms) : [];
    }
}
