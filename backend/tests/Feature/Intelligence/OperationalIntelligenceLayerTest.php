<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OperationalIntelligenceLayerTest extends TestCase
{
    public function test_company_profile_includes_intelligence_block(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2c',
            'name' => 'Intel Cust',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number' => 'IN-'.Str::upper(Str::random(4)),
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
            'order_number' => 'WO-IN-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/companies/'.$tenant['company']->id.'/profile');

        $res->assertOk()
            ->assertJsonPath('meta.intelligence_version', 'v1')
            ->assertJsonStructure([
                'data' => [
                    'intelligence' => [
                        'health_status',
                        'indicators' => ['activity_level', 'engagement_level', 'payment_behavior'],
                        'attention_items',
                    ],
                ],
            ]);
        $this->assertContains($res->json('data.intelligence.health_status'), ['healthy', 'watch', 'at_risk', 'inactive']);
    }

    public function test_customer_profile_includes_intelligence_block(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2c',
            'name' => 'Intel Cust 2',
            'is_active' => true,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/customers/'.$customer->id.'/profile');

        $res->assertOk()
            ->assertJsonPath('meta.intelligence_version', 'v1');
        $this->assertContains(
            $res->json('data.intelligence.indicators.payment_behavior'),
            ['good', 'delayed', 'risky', 'unknown'],
        );
    }

    public function test_global_operations_feed_includes_intelligence(): void
    {
        $tenant = $this->createTenant('owner');
        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $res = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/reporting/v1/operations/global-feed?from='.$from.'&to='.$to);

        $res->assertOk()
            ->assertJsonPath('meta.intelligence_version', 'v1')
            ->assertJsonStructure([
                'data' => [
                    'intelligence' => [
                        'health_status',
                        'indicators',
                        'attention_items',
                    ],
                ],
            ]);
    }
}
