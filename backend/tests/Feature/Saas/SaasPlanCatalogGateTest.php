<?php

namespace Tests\Feature\Saas;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasPlanCatalogGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_plans_bootstraps_catalog_when_no_active_plans_exist(): void
    {
        $this->assertSame(0, Plan::query()->where('is_active', true)->count());

        $res = $this->getJson('/api/v1/plans');

        $res->assertSuccessful();
        $res->assertJsonStructure(['data', 'trace_id']);
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

    public function test_platform_email_can_seed_plans_when_tenant_catalog_edit_disabled(): void
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
        $res->assertJsonPath('code', 'PLATFORM_OPERATOR_REQUIRED');
    }

    public function test_platform_operator_can_list_admin_companies(): void
    {
        config(['saas.platform_admin_emails' => ['operator@platform.sa']]);

        $tenant = $this->createTenant();
        $tenant['user']->update(['email' => 'operator@platform.sa']);
        $token = $tenant['user']->createToken('t')->plainTextToken;

        $res = $this->getJson('/api/v1/admin/companies', [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ]);

        $res->assertSuccessful();
        $res->assertJsonStructure(['data', 'pagination']);
    }
}
