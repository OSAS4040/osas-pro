<?php

namespace Tests\Feature\Tenancy;

use App\Enums\WorkOrderStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    private function enablePlatformExecutionPartner(Company $company): void
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $profile = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $matrix = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];
        $matrix['platform_execution_partner'] = true;
        $profile['feature_matrix'] = $matrix;
        $profile['business_type'] = $profile['business_type'] ?? 'service_center';
        $settings['business_profile'] = $profile;
        $company->update(['settings' => $settings]);
    }

    public function test_user_cannot_see_other_companys_invoices(): void
    {
        $tenant1 = $this->createTenant('owner');
        $tenant2 = $this->createTenant('owner');

        $response = $this->actingAsUser($tenant1['user'])
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);

        $ids = collect($response->json('data.data'))->pluck('company_id')->unique();

        $this->assertNotContains($tenant2['company']->id, $ids->toArray());
    }

    public function test_suspended_subscription_blocks_login(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update(['status' => 'suspended']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(402);
    }

    public function test_grace_period_blocks_write_operations(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update([
            'ends_at'       => now()->subDay(),
            'grace_ends_at' => now()->addDays(14),
            'status'        => 'grace_period',
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/customers', [
                'name'  => 'Test Customer',
                'type'  => 'b2c',
                'phone' => '+966500000002',
            ]);

        $response->assertStatus(423);
    }

    public function test_grace_period_allows_read_operations(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update([
            'ends_at'       => now()->subDay(),
            'grace_ends_at' => now()->addDays(14),
            'status'        => 'grace_period',
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/invoices')->assertStatus(401);
    }

    public function test_trace_id_is_present_in_response(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/auth/me');

        $response->assertJsonStructure(['trace_id']);
        $this->assertNotEmpty($response->json('trace_id'));
    }

    public function test_work_orders_index_never_lists_other_company_orders(): void
    {
        $tenant1 = $this->createTenant('owner');
        $tenant2 = $this->createTenant('owner');

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'type' => 'b2c',
            'name' => 'WO Iso Cust',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenant1['user']->id,
            'plate_number' => 'WO-TEN-'.Str::upper(Str::random(4)),
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2021,
            'is_active' => true,
        ]);

        $foreignWo = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $tenant1['user']->id,
            'order_number' => 'WO-XT-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Draft,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $response = $this->actingAsUser($tenant2['user'])
            ->getJson('/api/v1/work-orders?per_page=100');

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($foreignWo->id, $ids);
    }

    public function test_vehicle_detail_from_other_company_returns_404(): void
    {
        $tenant1 = $this->createTenant('owner');
        $tenant2 = $this->createTenant('owner');

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'type' => 'b2c',
            'name' => 'V Iso Cust',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenant1['user']->id,
            'plate_number' => 'VEH-XT-'.Str::upper(Str::random(4)),
            'make' => 'Nissan',
            'model' => 'Altima',
            'year' => 2022,
            'is_active' => true,
        ]);

        $this->actingAsUser($tenant2['user'])
            ->getJson('/api/v1/vehicles/'.$vehicle->id)
            ->assertNotFound();
    }

    public function test_work_order_show_from_other_tenant_returns_404(): void
    {
        $tenant1 = $this->createTenant('owner');
        $tenant2 = $this->createTenant('owner');

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'type' => 'b2c',
            'name' => 'WO detail iso',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenant1['user']->id,
            'plate_number' => 'WO-DTL-'.Str::upper(Str::random(4)),
            'make' => 'Test',
            'model' => 'Iso',
            'year' => 2024,
            'is_active' => true,
        ]);

        $wo = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant1['company']->id,
            'branch_id' => $tenant1['branch']->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $tenant1['user']->id,
            'order_number' => 'WO-DTL-'.Str::upper(Str::random(5)),
            'status' => WorkOrderStatus::Draft,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $this->actingAsUser($tenant2['user'])
            ->getJson('/api/v1/work-orders/'.$wo->id)
            ->assertNotFound();
    }

    public function test_platform_delegate_on_behalf_cannot_load_foreign_execution_partner_work_order(): void
    {
        Config::set('platform.admin_enabled', true);

        $ta = $this->createTenant('owner');
        $tb = $this->createTenant('owner');
        $ta['subscription']->delete();
        $tb['subscription']->delete();

        $this->enablePlatformExecutionPartner($ta['company']->fresh());
        $this->enablePlatformExecutionPartner($tb['company']->fresh());

        $companyA = $ta['company']->fresh();
        $companyB = $tb['company']->fresh();

        $customerB = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyB->id,
            'branch_id' => $tb['branch']->id,
            'type' => 'b2c',
            'name' => 'Other EP cust',
            'is_active' => true,
        ]);

        $vehicleB = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyB->id,
            'branch_id' => $tb['branch']->id,
            'customer_id' => $customerB->id,
            'created_by_user_id' => $tb['user']->id,
            'plate_number' => 'EP-XISO-'.Str::upper(Str::random(4)),
            'make' => 'Test',
            'model' => 'Other',
            'year' => 2024,
            'is_active' => true,
        ]);

        $orderB = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyB->id,
            'branch_id' => $tb['branch']->id,
            'customer_id' => $customerB->id,
            'vehicle_id' => $vehicleB->id,
            'created_by_user_id' => $tb['user']->id,
            'order_number' => 'WO-EP-X-'.Str::upper(Str::random(5)),
            'status' => WorkOrderStatus::Draft,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $this->createStandalonePlatformOperator('plat-wo-iso-xt@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', 'plat-wo-iso-xt@platform.test')->firstOrFail();

        $this->actingAsUser($platformUser)
            ->withHeaders(['X-On-Behalf-Company-Id' => (string) $companyA->id])
            ->getJson('/api/v1/work-orders/'.$orderB->id)
            ->assertNotFound();
    }
}
