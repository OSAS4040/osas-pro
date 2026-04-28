<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\LoginPrincipalKind;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_00_CLOSURE_REPORT.md — مصادقة وسياق الحساب
 */
#[Group('phase0')]
class LoginAccountContextTest extends TestCase
{
    public function test_password_login_includes_account_context_for_tenant_owner(): void
    {
        $tenant = $this->createTenant('owner');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('account_context.principal_kind', LoginPrincipalKind::TenantUser->value)
            ->assertJsonPath('account_context.guard_hint', 'staff')
            ->assertJsonPath('account_context.user_id', $tenant['user']->id)
            ->assertJsonPath('account_context.company_id', $tenant['company']->id)
            ->assertJsonPath('account_context.requires_context_selection', false);
    }

    public function test_password_login_platform_admin_phone_identifier_maps_to_platform_employee(): void
    {
        Config::set('saas.platform_admin_emails', []);
        Config::set('saas.platform_admin_phones', ['05049998877']);

        User::withoutGlobalScopes()->create([
            'uuid'               => Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'name'               => 'Standalone platform',
            'email'              => 'platform+standalonetest@internal.platform.sa',
            'password'           => 'Password123!X',
            'phone'              => '9665049998877',
            'phone_verified_at'  => now(),
            'role'               => UserRole::Owner,
            'status'             => UserStatus::Active,
            'is_active'          => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier'   => '05049998877',
            'password'     => 'Password123!X',
            'device_name'  => 'phpunit',
            'device_type'  => 'unknown',
        ])
            ->assertOk()
            ->assertJsonPath('account_context.principal_kind', LoginPrincipalKind::PlatformEmployee->value)
            ->assertJsonPath('account_context.guard_hint', 'platform')
            ->assertJsonPath('account_context.home_route_hint', '/admin')
            ->assertJsonPath('account_context.company_id', null)
            ->assertJsonPath('account_context.platform_role', 'platform_admin')
            ->assertJsonPath('home_screen', 'dashboard');
    }

    public function test_password_login_platform_admin_email_maps_to_platform_employee(): void
    {
        Config::set('saas.platform_admin_emails', ['platform-lead@test.sa']);
        Config::set('saas.platform_admin_phones', []);

        $this->createStandalonePlatformOperator('platform-lead@test.sa');

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'platform-lead@test.sa',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('account_context.principal_kind', LoginPrincipalKind::PlatformEmployee->value)
            ->assertJsonPath('account_context.guard_hint', 'platform')
            ->assertJsonPath('account_context.home_route_hint', '/admin')
            ->assertJsonPath('account_context.company_id', null)
            ->assertJsonPath('account_context.platform_role', 'super_admin');
    }

    public function test_password_login_fleet_user_includes_fleet_context(): void
    {
        $tenant = $this->createTenant('fleet_manager');

        $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('account_context.principal_kind', LoginPrincipalKind::CustomerUser->value)
            ->assertJsonPath('account_context.guard_hint', 'fleet')
            ->assertJsonPath('account_context.home_route_hint', '/fleet-portal');
    }
}
