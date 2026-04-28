<?php

namespace Tests\Feature\Saas;

use App\Models\Plan;
use App\Models\PlanAddon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_01_PROGRESS_REPORT.md — باقات وكتالوج SaaS
 */
#[Group('phase1')]
class SaasPlanCatalogGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_plans_bootstraps_catalog_when_no_active_plans_exist(): void
    {
        $this->assertSame(0, Plan::query()->where('is_active', true)->count());

        $res = $this->getJson('/api/v1/plans');

        $res->assertSuccessful();
        $res->assertJsonStructure(['data', 'plan_addons', 'trace_id']);
        $data = $res->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
        $this->assertGreaterThan(0, Plan::query()->where('is_active', true)->count());
    }

    public function test_owner_cannot_seed_plans_when_tenant_catalog_edit_disabled(): void
    {
        config(['saas.allow_tenant_plan_catalog_edit' => false]);
        config(['saas.platform_admin_emails' => []]);

        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $user    = $this->createUser($company, $branch, 'owner');

        $token = $user->createToken('t')->plainTextToken;

        $res = $this->postJson('/api/v1/plans/seed', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertStatus(403);
        $res->assertJsonPath('code', 'PLAN_CATALOG_FORBIDDEN');
    }

    public function test_tenant_owner_with_allowlisted_email_cannot_seed_plans_when_tenant_catalog_edit_disabled(): void
    {
        config(['saas.allow_tenant_plan_catalog_edit' => false]);
        config(['saas.platform_admin_emails' => ['owner_test@platform.sa']]);

        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $user    = $this->createUser($company, $branch, 'owner', ['email' => 'owner_test@platform.sa']);

        $token = $user->createToken('t')->plainTextToken;

        $res = $this->postJson('/api/v1/plans/seed', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertStatus(403);
        $res->assertJsonPath('code', 'PLAN_CATALOG_FORBIDDEN');
    }

    public function test_standalone_platform_operator_can_seed_plans_when_tenant_catalog_edit_disabled(): void
    {
        config(['saas.allow_tenant_plan_catalog_edit' => false]);
        config(['saas.platform_admin_emails' => ['owner_test@platform.sa']]);

        $user  = $this->createStandalonePlatformOperator('owner_test@platform.sa');
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->postJson('/api/v1/plans/seed', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertSuccessful();
    }

    public function test_owner_cannot_update_global_plan_without_gate(): void
    {
        config(['saas.allow_tenant_plan_catalog_edit' => false]);
        config(['saas.platform_admin_emails' => []]);

        Plan::query()->create([
            'slug'           => 'gate-test-plan',
            'name'           => 'Gate',
            'name_ar'        => 'بوابة',
            'price_monthly'  => 100,
            'price_yearly'   => 1000,
            'currency'       => 'SAR',
            'max_branches'   => 1,
            'max_users'      => 5,
            'max_products'   => 100,
            'grace_period_days' => 3,
            'features'       => ['pos'],
            'is_active'      => true,
            'sort_order'     => 99,
        ]);

        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $this->createActiveSubscription($company);
        $user  = $this->createUser($company, $branch, 'owner');
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->putJson('/api/v1/plans/gate-test-plan', ['name_ar' => 'معدّل'], [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertStatus(403);
        $res->assertJsonPath('code', 'PLAN_CATALOG_FORBIDDEN');
    }

    public function test_standalone_platform_operator_can_update_plan_via_platform_route(): void
    {
        config(['saas.allow_tenant_plan_catalog_edit' => false]);
        config(['saas.platform_admin_emails' => ['catalog-ops@platform.sa']]);

        Plan::query()->create([
            'slug'           => 'platform-route-plan',
            'name'           => 'PR',
            'name_ar'        => 'مسار المنصة',
            'price_monthly'  => 50,
            'price_yearly'   => 500,
            'currency'       => 'SAR',
            'max_branches'   => 1,
            'max_users'      => 5,
            'max_products'   => 100,
            'grace_period_days' => 3,
            'features'       => ['pos'],
            'is_active'      => true,
            'sort_order'     => 88,
        ]);

        $user  = $this->createStandalonePlatformOperator('catalog-ops@platform.sa');
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->putJson('/api/v1/platform/plans/platform-route-plan', ['name_ar' => 'محدّث من المنصة'], [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertSuccessful();
        $res->assertJsonPath('data.name_ar', 'محدّث من المنصة');
    }

    public function test_standalone_platform_operator_can_create_plan_addon_via_platform_route(): void
    {
        config(['saas.allow_tenant_plan_catalog_edit' => false]);
        config(['saas.platform_admin_emails' => ['catalog-ops@platform.sa']]);

        Plan::query()->create([
            'slug'           => 'addon-eligible-plan',
            'name'           => 'P',
            'name_ar'        => 'باقة للإضافة',
            'price_monthly'  => 10,
            'price_yearly'   => 100,
            'currency'       => 'SAR',
            'max_branches'   => 1,
            'max_users'      => 2,
            'max_products'   => 50,
            'grace_period_days' => 3,
            'features'       => ['pos'],
            'is_active'      => true,
            'sort_order'     => 1,
        ]);

        $user  = $this->createStandalonePlatformOperator('catalog-ops@platform.sa');
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->postJson('/api/v1/platform/plan-addons', [
            'slug' => 'addon_e2e_catalog',
            'feature_key' => 'e2e_feature',
            'name_ar' => 'إضافة اختبار',
            'description_ar' => 'وصف',
            'price_monthly' => 15,
            'price_yearly' => 150,
            'eligible_plan_slugs' => ['addon-eligible-plan'],
            'is_active' => true,
            'sort_order' => 9,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.slug', 'addon_e2e_catalog');
        $this->assertDatabaseHas('plan_addons', ['slug' => 'addon_e2e_catalog', 'feature_key' => 'e2e_feature']);
    }

    public function test_create_plan_addon_rejects_unknown_plan_slug_in_eligible_list(): void
    {
        config(['saas.allow_tenant_plan_catalog_edit' => false]);
        config(['saas.platform_admin_emails' => ['catalog-ops@platform.sa']]);

        $user  = $this->createStandalonePlatformOperator('catalog-ops@platform.sa');
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->postJson('/api/v1/platform/plan-addons', [
            'slug' => 'addon_bad_eligible',
            'feature_key' => 'x',
            'name_ar' => 'X',
            'price_monthly' => 1,
            'price_yearly' => 10,
            'eligible_plan_slugs' => ['no-such-plan-slug'],
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertStatus(422);
        $this->assertSame(0, PlanAddon::query()->where('slug', 'addon_bad_eligible')->count());
    }

    public function test_admin_companies_requires_platform_operator_email(): void
    {
        config(['saas.platform_admin_emails' => []]);

        $tenant = $this->createTenant();
        $token  = $tenant['user']->createToken('t')->plainTextToken;

        $res = $this->getJson('/api/v1/admin/companies', [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertStatus(403);
        $res->assertJsonPath('code', 'PLATFORM_ACCESS_ONLY');
    }

    public function test_platform_operator_can_list_admin_companies(): void
    {
        config(['saas.platform_admin_emails' => ['operator@platform.sa']]);

        $user  = $this->createStandalonePlatformOperator('operator@platform.sa');
        $token = $user->createToken('t')->plainTextToken;

        $res = $this->getJson('/api/v1/admin/companies', [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertSuccessful();
        $res->assertJsonStructure(['data', 'pagination']);
    }
}
