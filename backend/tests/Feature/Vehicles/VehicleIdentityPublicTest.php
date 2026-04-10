<?php

namespace Tests\Feature\Vehicles;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\VehicleIdentityService;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleIdentityPublicTest extends TestCase
{
    public function test_public_vehicle_identity_returns_payload_without_vin(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $user = $tenant['user'];

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Identity Test',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'IDT-9001',
            'vin' => '1HGBH41JXMN109186',
            'make' => 'TestMake',
            'model' => 'TestModel',
            'year' => 2024,
        ]);

        $token = app(VehicleIdentityService::class)->ensureActiveToken($vehicle);

        $response = $this->getJson("/api/v1/public/vehicle-identity/{$token->token}");

        $response->assertOk()
            ->assertJsonPath('data.company_name', $company->name)
            ->assertJsonPath('data.public_code', $token->public_code)
            ->assertJsonMissingPath('data.vin');

        $this->assertDatabaseHas('vehicle_identity_scan_events', [
            'vehicle_identity_token_id' => $token->id,
        ]);
    }

    public function test_resolve_returns_vehicle_id_for_same_tenant(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $user = $tenant['user'];

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Resolve Test',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'RSV-100',
            'make' => 'A',
            'model' => 'B',
            'year' => 2023,
        ]);

        $token = app(VehicleIdentityService::class)->ensureActiveToken($vehicle);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/vehicle-identity/resolve', ['token' => $token->token])
            ->assertOk()
            ->assertJsonPath('data.vehicle_id', $vehicle->id);
    }

    public function test_digital_card_does_not_auto_reissue_identity_after_revoke(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $user = $tenant['user'];

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Revoke Card Test',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'REV-900',
            'make' => 'R',
            'model' => 'V',
            'year' => 2024,
        ]);

        app(\App\Services\VehicleIdentityService::class)->ensureActiveToken($vehicle);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/identity/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}/digital-card")
            ->assertOk()
            ->assertJsonPath('data.identity.status', 'revoked')
            ->assertJsonPath('data.identity.public_url', null);
    }

    public function test_issue_restores_public_identity_after_revoke(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $user = $tenant['user'];

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Issue Test',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'ISS-001',
            'make' => 'I',
            'model' => 'S',
            'year' => 2024,
        ]);

        app(VehicleIdentityService::class)->ensureActiveToken($vehicle);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/identity/revoke")
            ->assertOk();

        $issue = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/vehicles/{$vehicle->id}/identity/issue")
            ->assertOk();

        $issue->assertJsonPath('data.status', 'active');
        $this->assertNotNull($issue->json('data.public_url'));
        $this->assertNotNull($issue->json('data.public_code'));

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/vehicles/{$vehicle->id}/digital-card")
            ->assertOk()
            ->assertJsonPath('data.identity.status', 'active');
    }

    public function test_resolve_returns_404_for_other_tenant(): void
    {
        $tenantA = $this->createTenant('owner');
        $tenantB = $this->createTenant('owner');

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $tenantA['company']->id,
            'branch_id' => $tenantA['branch']->id,
            'type' => 'individual',
            'name' => 'Other Co',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $tenantA['company']->id,
            'branch_id' => $tenantA['branch']->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $tenantA['user']->id,
            'plate_number' => 'OTH-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2022,
        ]);

        $token = app(VehicleIdentityService::class)->ensureActiveToken($vehicle);

        $this->actingAs($tenantB['user'], 'sanctum')
            ->postJson('/api/v1/vehicle-identity/resolve', ['token' => $token->token])
            ->assertStatus(404);
    }
}
