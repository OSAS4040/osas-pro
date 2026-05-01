<?php

declare(strict_types=1);

namespace Tests\Feature\FleetPortal;

use App\Models\Customer;
use App\Models\Service;
use App\Models\Vehicle;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FleetPortalPricingGateTest extends TestCase
{
    public function test_fleet_cannot_create_work_order_without_platform_approved_price_version(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $staff = $tenant['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Fleet Gate Customer',
            'is_active' => true,
        ]);

        $fleetUser = $this->createUser($company, $branch, 'fleet_contact', [
            'customer_id' => $customer->id,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'FLT-G-'.Str::upper(Str::random(3)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
            'is_active' => true,
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'name' => 'Gate Service',
            'name_ar' => 'خدمة بوابة',
            'code' => 'GSV-'.Str::upper(Str::random(3)),
            'base_price' => 80,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        $res = $this->actingAsUser($fleetUser)->postJson('/api/v1/fleet-portal/work-orders', [
            'vehicle_id' => $vehicle->id,
            'service_id' => $service->id,
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('اعتماد', (string) $res->json('message'));
    }
}
