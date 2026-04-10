<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\SaasPlatformAccess;
use Tests\TestCase;

class SaasPlatformAccessTest extends TestCase
{
    public function test_platform_operator_when_email_in_allowlist(): void
    {
        config(['saas.platform_admin_emails' => ['ops@platform.example']]);

        $user = new User(['email' => 'Ops@Platform.example']);

        $this->assertTrue(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_platform_operator_false_when_list_empty(): void
    {
        config(['saas.platform_admin_emails' => []]);

        $user = new User(['email' => 'ops@platform.example']);

        $this->assertFalse(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_can_manage_global_plan_catalog_when_tenant_edit_allowed(): void
    {
        config([
            'saas.allow_tenant_plan_catalog_edit' => true,
            'saas.platform_admin_emails' => [],
        ]);

        $user = new User(['email' => 'any@test.sa']);

        $this->assertTrue(SaasPlatformAccess::canManageGlobalPlanCatalog($user));
    }

    public function test_can_manage_global_plan_catalog_when_platform_operator_and_tenant_edit_off(): void
    {
        config([
            'saas.allow_tenant_plan_catalog_edit' => false,
            'saas.platform_admin_emails' => ['ops@platform.example'],
        ]);

        $user = new User(['email' => 'ops@platform.example']);

        $this->assertTrue(SaasPlatformAccess::canManageGlobalPlanCatalog($user));
    }

    public function test_can_manage_global_plan_catalog_false_for_regular_owner_when_tenant_edit_off(): void
    {
        config([
            'saas.allow_tenant_plan_catalog_edit' => false,
            'saas.platform_admin_emails' => ['ops@platform.example'],
        ]);

        $user = new User(['email' => 'owner@tenant.sa']);

        $this->assertFalse(SaasPlatformAccess::canManageGlobalPlanCatalog($user));
    }
}
