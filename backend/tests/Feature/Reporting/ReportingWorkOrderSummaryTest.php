<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\UserStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingWorkOrderSummaryTest extends TestCase
{
    private function reportingUrl(): string
    {
        return '/api/v1/reporting/v1/operations/work-order-summary';
    }

    private function dateRangeQuery(): array
    {
        return [
            'from' => now()->subDays(7)->toDateString(),
            'to'   => now()->toDateString(),
        ];
    }

    public function test_owner_receives_unified_report_envelope(): void
    {
        $tenant = $this->createTenant('owner');
        $user = $tenant['user'];

        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Rep Customer',
            'is_active'  => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number'       => 'RPT-'.Str::upper(Str::random(5)),
            'make'               => 'X',
            'model'              => 'Y',
            'year'               => 2024,
            'is_active'          => true,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $vehicle->id,
            'created_by_user_id' => $user->id,
            'order_number'       => 'WO-RPT-'.Str::upper(Str::random(5)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $response = $this->actingAsUser($user)
            ->getJson($this->reportingUrl().'?'.http_build_query($this->dateRangeQuery()));

        $response->assertOk()
            ->assertJsonPath('report.id', 'operations.work_order_summary')
            ->assertJsonPath('report.read_only', true)
            ->assertJsonPath('report.export.supported', false)
            ->assertJsonPath('meta.query_kind', 'aggregate')
            ->assertJsonPath('meta.intelligence_version', 'v1')
            ->assertJsonStructure([
                'report' => ['id', 'version', 'generated_at', 'period', 'filters', 'read_only', 'export'],
                'data'   => ['by_status', 'totals', 'intelligence'],
                'trace_id',
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.totals.work_orders'));
    }

    public function test_technician_without_report_permissions_is_forbidden(): void
    {
        $tenant = $this->createTenant('technician');

        $this->actingAsUser($tenant['user'])
            ->getJson($this->reportingUrl().'?'.http_build_query($this->dateRangeQuery()))
            ->assertForbidden();
    }

    public function test_no_cross_company_data_via_tenant_scope(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $customerB = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $b['company']->id,
            'branch_id'  => $b['branch']->id,
            'type'       => 'b2c',
            'name'       => 'B Only',
            'is_active'  => true,
        ]);

        $vehicleB = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $b['company']->id,
            'branch_id'          => $b['branch']->id,
            'customer_id'        => $customerB->id,
            'created_by_user_id' => $b['user']->id,
            'plate_number'       => 'B-'.Str::upper(Str::random(5)),
            'make'               => 'X',
            'model'              => 'Y',
            'year'               => 2024,
            'is_active'          => true,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $b['company']->id,
            'branch_id'          => $b['branch']->id,
            'customer_id'        => $customerB->id,
            'vehicle_id'         => $vehicleB->id,
            'created_by_user_id' => $b['user']->id,
            'order_number'       => 'WO-B-'.Str::upper(Str::random(5)),
            'status'             => WorkOrderStatus::InProgress,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $response = $this->actingAsUser($a['user'])
            ->getJson($this->reportingUrl().'?'.http_build_query($this->dateRangeQuery()));

        $response->assertOk();
        $this->assertSame(0, (int) $response->json('data.totals.work_orders'));
    }

    public function test_customer_filter_rejects_foreign_customer(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $customerB = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $b['company']->id,
            'branch_id'  => $b['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Foreign',
            'is_active'  => true,
        ]);

        $q = array_merge($this->dateRangeQuery(), ['customer_id' => $customerB->id]);

        $this->actingAsUser($a['user'])
            ->getJson($this->reportingUrl().'?'.http_build_query($q))
            ->assertStatus(422);
    }

    public function test_date_range_exceeds_config_returns_validation_error(): void
    {
        Config::set('reporting.max_date_range_days', 5);
        $tenant = $this->createTenant('owner');

        $q = [
            'from' => now()->subDays(20)->toDateString(),
            'to'   => now()->toDateString(),
        ];

        $this->actingAsUser($tenant['user'])
            ->getJson($this->reportingUrl().'?'.http_build_query($q))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_staff_without_cross_branch_only_sees_own_branch(): void
    {
        $company = $this->createCompany();
        $branch1 = $this->createBranch($company, ['name' => 'B1', 'code' => 'B1', 'is_main' => true]);
        $branch2 = $this->createBranch($company, ['name' => 'B2', 'code' => 'B2', 'is_main' => false]);
        $this->createActiveSubscription($company);

        $staff = $this->createUser($company, $branch1, 'staff');

        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id'  => $branch1->id,
            'type'       => 'b2c',
            'name'       => 'C1',
            'is_active'  => true,
        ]);

        $v1 = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $branch1->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number'       => 'V1-'.Str::upper(Str::random(4)),
            'make'               => 'A',
            'model'              => 'B',
            'year'               => 2023,
            'is_active'          => true,
        ]);

        $v2 = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $branch2->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number'       => 'V2-'.Str::upper(Str::random(4)),
            'make'               => 'A',
            'model'              => 'B',
            'year'               => 2023,
            'is_active'          => true,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $branch1->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $v1->id,
            'created_by_user_id' => $staff->id,
            'order_number'       => 'WO-B1-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $branch2->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $v2->id,
            'created_by_user_id' => $staff->id,
            'order_number'       => 'WO-B2-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $response = $this->actingAsUser($staff)
            ->getJson($this->reportingUrl().'?'.http_build_query($this->dateRangeQuery()));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.totals.work_orders'));
    }

    public function test_user_id_filter_limits_rows(): void
    {
        $tenant = $this->createTenant('owner');
        $owner = $tenant['user'];

        $other = User::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'name'       => 'Other Staff',
            'email'      => 'other_'.Str::random(6).'@test.sa',
            'password'   => bcrypt('Password123!'),
            'role'       => 'staff',
            'status'     => UserStatus::Active,
            'is_active'  => true,
        ]);

        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Cu',
            'is_active'  => true,
        ]);

        $v = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $owner->id,
            'plate_number'       => 'VF-'.Str::upper(Str::random(4)),
            'make'               => 'A',
            'model'              => 'B',
            'year'               => 2023,
            'is_active'          => true,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $v->id,
            'created_by_user_id' => $owner->id,
            'order_number'       => 'WO-O-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $v->id,
            'created_by_user_id' => $other->id,
            'order_number'       => 'WO-X-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $q = array_merge($this->dateRangeQuery(), ['user_id' => $owner->id]);

        $response = $this->actingAsUser($owner)
            ->getJson($this->reportingUrl().'?'.http_build_query($q));

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.totals.work_orders'));
    }
}
