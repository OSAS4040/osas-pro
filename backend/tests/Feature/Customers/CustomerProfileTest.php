<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerProfileTest extends TestCase
{
    private function profileUrl(int $customerId): string
    {
        return '/api/v1/customers/'.$customerId.'/profile';
    }

    public function test_owner_receives_customer_profile(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2c',
            'name' => 'Hub Customer',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number' => 'CP-'.Str::upper(Str::random(4)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2021,
            'is_active' => true,
        ]);

        WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $tenant['user']->id,
            'order_number' => 'WO-CP-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->profileUrl($customer->id));

        $res->assertOk()
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.customer.type', 'b2c')
            ->assertJsonPath('meta.financial_metrics_included', true)
            ->assertJsonPath('meta.read_only', true);

        $this->assertGreaterThanOrEqual(1, $res->json('data.summary.work_orders_count'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.relationships.vehicles_count'));
        $this->assertNotNull($res->json('data.activity_snapshot.last_work_order'));

        $res->assertJsonPath('data.relationships.operational_map.scope', 'customer')
            ->assertJsonPath('data.relationships.operational_map.customer_id', $customer->id)
            ->assertJsonPath('data.relationships.operational_map.visibility.vehicle_assets', true)
            ->assertJsonPath('data.relationships.operational_map.visibility.user_directory', true);
        $this->assertGreaterThanOrEqual(1, $res->json('data.relationships.top_vehicles.0.vehicle_id'));
        $this->assertSame($vehicle->id, $res->json('data.relationships.top_vehicles.0.vehicle_id'));
    }

    public function test_accountant_hides_vehicle_and_user_relation_details_without_permission(): void
    {
        $tenant = $this->createTenant('accountant');
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2c',
            'name' => 'Acct Cust',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number' => 'AC-'.Str::upper(Str::random(4)),
            'make' => 'M',
            'model' => 'N',
            'year' => 2020,
            'is_active' => true,
        ]);

        WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $tenant['user']->id,
            'order_number' => 'WO-AC-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->profileUrl($customer->id));

        $res->assertOk()
            ->assertJsonPath('data.relationships.operational_map.visibility.vehicle_assets', false)
            ->assertJsonPath('data.relationships.operational_map.visibility.user_directory', false);

        $this->assertSame([], $res->json('data.relationships.top_vehicles'));
        $this->assertSame([], $res->json('data.relationships.assigned_users'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.relationships.operational_map.counts.vehicles'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.relationships.operational_map.counts.assigned_users'));
    }

    public function test_viewer_hides_financial_summary_fields(): void
    {
        $tenant = $this->createTenant('viewer');
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2b',
            'name' => 'Viewer Cust',
            'is_active' => true,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->profileUrl($customer->id));

        $res->assertOk()
            ->assertJsonPath('meta.financial_metrics_included', false)
            ->assertJsonPath('data.summary.invoices_count', null)
            ->assertJsonPath('data.summary.payments_count', null)
            ->assertJsonPath('data.behavior_indicators.payment_behavior', 'unknown')
            ->assertJsonPath('data.activity_snapshot.last_invoice', null)
            ->assertJsonPath('data.activity_snapshot.last_payment', null)
            ->assertJsonPath('data.relationships.operational_map.visibility.user_directory', false);

        $this->assertSame([], $res->json('data.relationships.assigned_users'));
    }

    public function test_other_company_customer_returns_not_found(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');
        $customerB = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $b['company']->id,
            'branch_id' => $b['branch']->id,
            'type' => 'b2c',
            'name' => 'Other Co',
            'is_active' => true,
        ]);

        $this->actingAsUser($a['user'])
            ->getJson($this->profileUrl($customerB->id))
            ->assertNotFound();
    }

    public function test_staff_without_cross_branch_cannot_view_other_branch_customer(): void
    {
        $company = $this->createCompany();
        $branch1 = $this->createBranch($company, ['name' => 'Branch One', 'code' => 'B1', 'is_main' => true]);
        $branch2 = $this->createBranch($company, ['name' => 'Branch Two', 'code' => 'B2', 'is_main' => false]);
        $this->createActiveSubscription($company);

        $staff = $this->createUser($company, $branch1, 'staff');
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch2->id,
            'type' => 'b2c',
            'name' => 'Remote branch customer',
            'is_active' => true,
        ]);

        $this->actingAsUser($staff)
            ->getJson($this->profileUrl($customer->id))
            ->assertForbidden();
    }
}
