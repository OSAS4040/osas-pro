<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Customer;
use App\Models\PlatformTenantNavHide;
use App\Support\StaffNav\StaffNavKey;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlatformTenantNavHideTest extends TestCase
{
    public function test_auth_me_merges_company_scoped_nav_hides(): void
    {
        $tenant = $this->createTenant('staff');
        PlatformTenantNavHide::query()->create([
            'nav_key'     => StaffNavKey::forStaffHref('/vehicles'),
            'scope'       => 'company',
            'company_id'  => $tenant['company']->id,
            'user_id'     => null,
            'customer_id' => null,
        ]);

        Sanctum::actingAs($tenant['user']);

        $keys = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.hidden_staff_nav_keys');
        $this->assertContains(StaffNavKey::forStaffHref('/vehicles'), $keys);
    }

    public function test_auth_me_merges_user_scoped_nav_hides(): void
    {
        $tenant = $this->createTenant('staff');
        PlatformTenantNavHide::query()->create([
            'nav_key'     => StaffNavKey::forStaffHref('/pos'),
            'scope'       => 'user',
            'company_id'  => null,
            'user_id'     => $tenant['user']->id,
            'customer_id' => null,
        ]);

        Sanctum::actingAs($tenant['user']);

        $keys = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.hidden_staff_nav_keys');
        $this->assertContains(StaffNavKey::forStaffHref('/pos'), $keys);
    }

    public function test_auth_me_returns_customer_nav_hides_for_customer_users(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid'        => Str::uuid(),
            'company_id'  => $tenant['company']->id,
            'branch_id'   => $tenant['branch']->id,
            'type'        => 'individual',
            'name'        => 'Portal Customer',
            'is_active'   => true,
        ]);

        $portalUser = $this->createUser($tenant['company'], $tenant['branch'], 'customer', [
            'customer_id' => $customer->id,
            'email'       => 'cust_nav_'.$customer->id.'@test.sa',
        ]);

        PlatformTenantNavHide::query()->create([
            'nav_key'     => StaffNavKey::forCustomerHref('/customer/pricing'),
            'scope'       => 'customer',
            'company_id'  => null,
            'user_id'     => null,
            'customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($portalUser);

        $cKeys = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.hidden_customer_nav_keys');
        $this->assertContains(StaffNavKey::forCustomerHref('/customer/pricing'), $cKeys);
    }

    public function test_platform_operator_can_create_and_list_nav_hide(): void
    {
        $tenant = $this->createTenant('owner');
        $platformUser = $this->createStandalonePlatformOperator('nav_hide_admin@test.sa', [
            'platform_role' => 'platform_admin',
        ]);
        Sanctum::actingAs($platformUser);

        $this->postJson('/api/v1/platform/tenant-nav-hides', [
            'scope'      => 'company',
            'nav_key'    => StaffNavKey::forStaffHref('/crm/quotes'),
            'company_id' => $tenant['company']->id,
        ])->assertStatus(201);

        $this->getJson('/api/v1/platform/tenant-nav-hides?company_id='.$tenant['company']->id)
            ->assertOk()
            ->assertJsonFragment(['nav_key' => StaffNavKey::forStaffHref('/crm/quotes')]);
    }
}
