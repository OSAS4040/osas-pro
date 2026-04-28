<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Platform\PlatformPermissionService;
use Tests\TestCase;

class PlatformPermissionServiceTest extends TestCase
{
    public function test_can_manage_global_plan_catalog_when_tenant_edit_allowed(): void
    {
        config([
            'saas.allow_tenant_plan_catalog_edit' => true,
            'saas.platform_admin_emails' => [],
            'saas.platform_admin_phones' => [],
            'platform.admin_enabled' => true,
        ]);

        $user = new User(['email' => 'any@test.sa', 'company_id' => 1]);

        $this->assertTrue(app(PlatformPermissionService::class)->canManageGlobalPlanCatalog($user));
    }

    public function test_can_manage_global_plan_catalog_when_platform_operator_and_tenant_edit_off(): void
    {
        config([
            'saas.allow_tenant_plan_catalog_edit' => false,
            'saas.platform_admin_emails' => ['ops@platform.example'],
            'saas.platform_admin_phones' => [],
            'platform.admin_enabled' => true,
        ]);

        $user = new User(['email' => 'ops@platform.example', 'company_id' => null]);

        $this->assertTrue(app(PlatformPermissionService::class)->canManageGlobalPlanCatalog($user));
    }

    public function test_can_manage_global_plan_catalog_false_for_regular_owner_when_tenant_edit_off(): void
    {
        config([
            'saas.allow_tenant_plan_catalog_edit' => false,
            'saas.platform_admin_emails' => ['ops@platform.example'],
            'saas.platform_admin_phones' => [],
            'platform.admin_enabled' => true,
        ]);

        $user = new User(['email' => 'owner@tenant.sa', 'company_id' => 1]);

        $this->assertFalse(app(PlatformPermissionService::class)->canManageGlobalPlanCatalog($user));
    }

    public function test_is_super_admin(): void
    {
        config(['platform.admin_enabled' => true]);

        $u = $this->createStandalonePlatformOperator('super-check@platform.test', [
            'platform_role' => 'super_admin',
        ]);

        $this->assertTrue(app(PlatformPermissionService::class)->isSuperAdmin($u));
    }

    public function test_hybrid_platform_user_with_company_retains_platform_permissions(): void
    {
        config([
            'saas.allow_tenant_plan_catalog_edit' => false,
            'platform.admin_enabled' => true,
        ]);

        $tenant = $this->createTenant('owner');
        $tenant['user']->update([
            'is_platform_user' => true,
            'platform_role'      => 'super_admin',
        ]);

        $this->assertTrue(app(PlatformPermissionService::class)->canManageGlobalPlanCatalog($tenant['user']->fresh()));
    }

    public function test_has_permission_false_for_null_user(): void
    {
        config(['platform.admin_enabled' => true]);

        $this->assertFalse(app(PlatformPermissionService::class)->hasPermission(null, 'platform.ops.read'));
    }

    public function test_has_permission_false_for_tenant_user_without_platform_flag(): void
    {
        config(['platform.admin_enabled' => true]);

        $tenant = $this->createTenant('owner');

        $this->assertFalse(
            app(PlatformPermissionService::class)->hasPermission($tenant['user'], 'platform.companies.read')
        );
    }

    public function test_has_permission_false_when_platform_admin_disabled(): void
    {
        config(['platform.admin_enabled' => false]);

        $user = $this->createStandalonePlatformOperator('disabled-gate@platform.test');

        $this->assertFalse(app(PlatformPermissionService::class)->hasPermission($user, 'platform.ops.read'));
    }

    public function test_has_permission_true_when_role_grants_permission(): void
    {
        config(['platform.admin_enabled' => true]);

        $user = $this->createStandalonePlatformOperator('support@platform.test', [
            'platform_role' => 'support_agent',
        ]);

        $this->assertTrue(
            app(PlatformPermissionService::class)->hasPermission($user, 'platform.companies.read')
        );
        $this->assertTrue(
            app(PlatformPermissionService::class)->hasPermission($user, 'platform.support.read')
        );
        $this->assertTrue(
            app(PlatformPermissionService::class)->hasPermission($user, 'platform.support.manage')
        );
    }

    public function test_has_permission_false_when_role_lacks_permission(): void
    {
        config(['platform.admin_enabled' => true]);

        $user = $this->createStandalonePlatformOperator('support2@platform.test', [
            'platform_role' => 'support_agent',
        ]);

        $this->assertFalse(
            app(PlatformPermissionService::class)->hasPermission($user, 'platform.catalog.manage')
        );
    }

    public function test_has_permission_super_admin_wildcard_grants_any_key(): void
    {
        config(['platform.admin_enabled' => true]);

        $user = $this->createStandalonePlatformOperator('super-wild@platform.test', [
            'platform_role' => 'super_admin',
        ]);

        $this->assertTrue(
            app(PlatformPermissionService::class)->hasPermission($user, 'platform.future.permission')
        );
    }

    public function test_is_super_admin_false_for_platform_admin_role(): void
    {
        config(['platform.admin_enabled' => true]);

        $user = $this->createStandalonePlatformOperator('plat-admin@platform.test', [
            'platform_role' => 'platform_admin',
        ]);

        $this->assertFalse(app(PlatformPermissionService::class)->isSuperAdmin($user));
    }

    public function test_is_super_admin_false_for_null_user(): void
    {
        config(['platform.admin_enabled' => true]);

        $this->assertFalse(app(PlatformPermissionService::class)->isSuperAdmin(null));
    }
}
