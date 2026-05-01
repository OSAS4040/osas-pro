<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkOrderBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_intake_lookup_does_not_resolve_order_from_other_branch(): void
    {
        $t = $this->createTenant('technician');
        $company = $t['company'];
        $branchOther = $this->createBranch($company, [
            'name' => 'Second',
            'code' => 'SEC',
            'is_main' => false,
        ]);
        $tech = $t['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branchOther->id,
            'type' => 'individual',
            'name' => 'عميل فرع 2',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branchOther->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tech->id,
            'plate_number' => 'ISO 2222',
            'make' => 'Test',
            'model' => 'V',
            'year' => 2024,
            'is_active' => true,
        ]);

        WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branchOther->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $tech->id,
            'order_number' => 'WO-ISO-'.Str::upper(Str::random(5)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 50,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $this->actingAsUser($tech)
            ->getJson('/api/v1/work-orders/intake-lookup?'.http_build_query(['plate_number' => 'ISO 2222']))
            ->assertOk()
            ->assertJsonPath('data.work_order', null);
    }

    public function test_manager_intake_lookup_resolves_order_from_other_branch_without_branch_query(): void
    {
        $t = $this->createTenant('manager');
        $company = $t['company'];
        $branchOther = $this->createBranch($company, [
            'name' => 'Second',
            'code' => 'SEC2',
            'is_main' => false,
        ]);
        $manager = $t['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branchOther->id,
            'type' => 'individual',
            'name' => 'عميل فرع 2',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branchOther->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $manager->id,
            'plate_number' => 'MGR 3333',
            'make' => 'Test',
            'model' => 'V',
            'year' => 2024,
            'is_active' => true,
        ]);

        $order = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branchOther->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $manager->id,
            'order_number' => 'WO-MGR-'.Str::upper(Str::random(5)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 50,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $this->actingAsUser($manager)
            ->getJson('/api/v1/work-orders/intake-lookup?'.http_build_query(['plate_number' => 'MGR 3333']))
            ->assertOk()
            ->assertJsonPath('data.work_order.id', $order->id);
    }

    public function test_work_orders_index_rejects_foreign_company_id_query(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $this->actingAsUser($a['user'])
            ->getJson('/api/v1/work-orders?company_id='.$b['company']->id)
            ->assertForbidden();
    }
}
