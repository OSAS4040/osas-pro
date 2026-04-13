<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Actions\Auth\ResolveLoginContextAction;
use App\Actions\Auth\ResolveLoginEligibilityAction;
use App\Enums\LoginGuardHint;
use App\Enums\LoginPrincipalKind;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ResolveLoginContextActionTest extends TestCase
{
    public function test_blocked_user_has_no_account_context(): void
    {
        $tenant = $this->createTenant('staff');
        $tenant['user']->update(['status' => UserStatus::Blocked]);

        $action = new ResolveLoginContextAction(new ResolveLoginEligibilityAction);
        $r = $action($tenant['user']->fresh());

        $this->assertFalse($r->eligibility->allowed);
        $this->assertNull($r->accountContext);
    }

    public function test_platform_phone_without_company_maps_to_platform_employee(): void
    {
        Config::set('saas.platform_admin_emails', []);
        Config::set('saas.platform_admin_phones', ['05041112233']);

        $user = User::withoutGlobalScopes()->create([
            'uuid'               => \Illuminate\Support\Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'name'               => 'Platform phone admin',
            'email'              => 'platform+testphone@internal.platform.sa',
            'password'           => 'Password123!X',
            'phone'              => '966504112233',
            'phone_verified_at'  => now(),
            'role'               => UserRole::Owner,
            'status'             => UserStatus::Active,
            'is_active'          => true,
        ]);

        $action = new ResolveLoginContextAction(new ResolveLoginEligibilityAction);
        $r = $action($user->fresh());

        $this->assertTrue($r->eligibility->allowed);
        $this->assertNotNull($r->accountContext);
        $this->assertSame(LoginPrincipalKind::PlatformEmployee, $r->accountContext->principalKind);
        $this->assertSame(LoginGuardHint::Platform, $r->accountContext->guardHint);
        $this->assertSame('/admin', $r->accountContext->homeRouteHint);
    }

    public function test_platform_email_maps_to_platform_employee(): void
    {
        config(['saas.platform_admin_emails' => ['boss@platform.test']]);

        $user = $this->createStandalonePlatformOperator('boss@platform.test');

        $action = new ResolveLoginContextAction(new ResolveLoginEligibilityAction);
        $r = $action($user->fresh());

        $this->assertTrue($r->eligibility->allowed);
        $this->assertNotNull($r->accountContext);
        $this->assertSame(LoginPrincipalKind::PlatformEmployee, $r->accountContext->principalKind);
        $this->assertSame(LoginGuardHint::Platform, $r->accountContext->guardHint);
        $this->assertSame('/admin', $r->accountContext->homeRouteHint);
        $this->assertSame('super_admin', $r->accountContext->toArray()['platform_role']);
    }

    public function test_platform_flag_with_tenant_anchor_maps_to_staff_guard_and_ops_home(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['user']->update([
            'is_platform_user' => true,
            'platform_role'      => 'super_admin',
        ]);

        $action = new ResolveLoginContextAction(new ResolveLoginEligibilityAction);
        $r = $action($tenant['user']->fresh());

        $this->assertTrue($r->eligibility->allowed);
        $this->assertNotNull($r->accountContext);
        $this->assertSame(LoginPrincipalKind::PlatformEmployee, $r->accountContext->principalKind);
        $this->assertSame(LoginGuardHint::Staff, $r->accountContext->guardHint);
        $this->assertSame('/work-orders', $r->accountContext->homeRouteHint);
        $this->assertSame($tenant['company']->id, $r->accountContext->companyId);
    }

    public function test_fleet_role_maps_to_customer_user_and_fleet_portal(): void
    {
        $tenant = $this->createTenant('fleet_manager');

        $action = new ResolveLoginContextAction(new ResolveLoginEligibilityAction);
        $r = $action($tenant['user']);

        $this->assertSame(LoginPrincipalKind::CustomerUser, $r->accountContext->principalKind);
        $this->assertSame(LoginGuardHint::Fleet, $r->accountContext->guardHint);
        $this->assertSame('/fleet-portal', $r->accountContext->homeRouteHint);
    }

    public function test_phone_onboarding_without_company_maps_to_onboarding_hint(): void
    {
        User::withoutGlobalScopes()->create([
            'uuid'               => \Illuminate\Support\Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'name'               => '966509998877',
            'email'              => null,
            'password'           => bcrypt('secret'),
            'phone'              => '966509998877',
            'phone_verified_at'  => now(),
            'role'               => UserRole::PhoneOnboarding,
            'status'             => UserStatus::Active,
            'is_active'          => true,
            'registration_stage' => 'phone_verified',
        ]);

        $user = User::withoutGlobalScopes()->where('phone', '966509998877')->firstOrFail();

        $action = new ResolveLoginContextAction(new ResolveLoginEligibilityAction);
        $r = $action($user);

        $this->assertSame(LoginPrincipalKind::Unknown, $r->accountContext->principalKind);
        $this->assertSame(LoginGuardHint::Onboarding, $r->accountContext->guardHint);
        $this->assertSame('/phone/onboarding', $r->accountContext->homeRouteHint);
    }
}
