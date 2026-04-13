<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompanyProfileTest extends TestCase
{
    private function profileUrl(int $companyId): string
    {
        return '/api/v1/companies/'.$companyId.'/profile';
    }

    public function test_owner_receives_profile_for_own_company(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2c',
            'name' => 'Prof Cust',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number' => 'PR-'.Str::upper(Str::random(4)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2022,
            'is_active' => true,
        ]);

        WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $tenant['user']->id,
            'order_number' => 'WO-PR-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->profileUrl((int) $tenant['company']->id));

        $res->assertOk()
            ->assertJsonPath('data.company.id', $tenant['company']->id)
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonPath('meta.financial_metrics_included', true);

        $this->assertGreaterThanOrEqual(1, $res->json('data.summary.work_orders_active'));
        $this->assertNotNull($res->json('data.activity_snapshot.last_work_order'));

        $res->assertJsonPath('data.relationships.operational_map.scope', 'company')
            ->assertJsonPath('data.relationships.operational_map.company_id', $tenant['company']->id)
            ->assertJsonPath('data.relationships.operational_map.visibility.customer_profiles', true)
            ->assertJsonPath('data.relationships.operational_map.visibility.user_directory', true);
        $this->assertGreaterThanOrEqual(1, $res->json('data.relationships.operational_map.counts.customers'));
    }

    public function test_viewer_hides_financial_snapshot_fields(): void
    {
        $tenant = $this->createTenant('viewer');

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->profileUrl((int) $tenant['company']->id));

        $res->assertOk()
            ->assertJsonPath('meta.financial_metrics_included', false)
            ->assertJsonPath('data.summary.invoices_in_period', null)
            ->assertJsonPath('data.activity_snapshot.last_invoice', null)
            ->assertJsonPath('data.activity_snapshot.last_payment', null)
            ->assertJsonPath('data.relationships.operational_map.visibility.user_directory', false);

        $this->assertSame([], $res->json('data.relationships.top_users'));
    }

    public function test_other_company_profile_is_forbidden(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $this->actingAsUser($a['user'])
            ->getJson($this->profileUrl((int) $b['company']->id))
            ->assertForbidden();
    }
}
