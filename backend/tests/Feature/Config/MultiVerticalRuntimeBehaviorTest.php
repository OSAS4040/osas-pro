<?php

namespace Tests\Feature\Config;

use App\Models\ConfigSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Vehicle;
use App\Models\VerticalProfile;
use App\Services\InventoryService;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiVerticalRuntimeBehaviorTest extends TestCase
{
    public function test_work_order_endpoint_behavior_changes_by_vertical_profile(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        $this->seedProfiles();

        $customer = $this->createCustomer($company->id, $branch->id);
        $vehicle = $this->createVehicle($company->id, $branch->id, $customer->id, $user->id);

        $company->forceFill(['vertical_profile_code' => 'service_workshop'])->save();
        $this->setConfig('vertical', 'service_workshop', 'work_orders.require_vehicle_plate', true);
        $this->setConfig('vertical', 'service_workshop', 'work_orders.allow_quick_order', true);

        $payload = [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'priority' => 'normal',
            'items' => [
                [
                    'item_type' => 'service',
                    'name' => 'Test line',
                    'quantity' => 1,
                    'unit_price' => 10,
                    'tax_rate' => 15,
                ],
            ],
        ];

        $this->postJson('/api/v1/work-orders', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'vehicle_plate is required for this vertical profile.']);

        $company->forceFill(['vertical_profile_code' => 'retail_pos'])->save();
        $this->setConfig('vertical', 'retail_pos', 'work_orders.require_vehicle_plate', false);
        $this->setConfig('vertical', 'retail_pos', 'work_orders.allow_quick_order', true);

        $this->postJson('/api/v1/work-orders', $payload)->assertStatus(201);
    }

    public function test_inventory_behavior_changes_by_vertical_profile(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        $this->seedProfiles();

        $product = $this->createProduct($company->id, $user->id);
        $inventoryService = app(InventoryService::class);

        $thrown = false;
        try {
            $inventoryService->deductStock(
                companyId: $company->id,
                branchId: $branch->id,
                productId: $product->id,
                quantity: 1,
                userId: $user->id,
                referenceType: 'runtime_test',
                referenceId: 1,
                traceId: (string) Str::uuid(),
                allowNegativeStock: false,
            );
        } catch (\DomainException) {
            $thrown = true;
        }
        $this->assertTrue($thrown);

        $movement = $inventoryService->deductStock(
            companyId: $company->id,
            branchId: $branch->id,
            productId: $product->id,
            quantity: 1,
            userId: $user->id,
            referenceType: 'runtime_test',
            referenceId: 2,
            traceId: (string) Str::uuid(),
            allowNegativeStock: true,
        );

        $this->assertNotNull($movement->id);
    }

    public function test_services_behavior_requires_estimated_minutes_when_enabled(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        $this->seedProfiles();

        $payload = [
            'name' => 'Engine Diagnostics',
            'base_price' => 120,
            'branch_id' => $branch->id,
        ];

        $company->forceFill(['vertical_profile_code' => 'service_workshop'])->save();
        $this->setConfig('vertical', 'service_workshop', 'services.require_estimated_minutes', true);
        $this->postJson('/api/v1/services', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'estimated_minutes is required for this vertical profile.']);

        $company->forceFill(['vertical_profile_code' => 'retail_pos'])->save();
        $this->setConfig('vertical', 'retail_pos', 'services.require_estimated_minutes', false);
        $this->postJson('/api/v1/services', $payload)->assertStatus(201);
    }

    public function test_pos_non_financial_behavior_flags_are_enforced(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        $this->seedProfiles();

        $company->forceFill(['vertical_profile_code' => 'retail_pos'])->save();
        $this->setConfig('vertical', 'retail_pos', 'pos.quick_sale_enabled', true);
        $this->setConfig('vertical', 'retail_pos', 'pos.require_customer', true);
        $this->setConfig('vertical', 'retail_pos', 'pos.enable_cash_only_mode', true);

        $payload = [
            'items' => [
                ['name' => 'Quick service', 'quantity' => 1, 'unit_price' => 50],
            ],
            'payment' => ['method' => 'card', 'amount' => 50],
        ];

        $this->postJson('/api/v1/pos/sale', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'customer_id is required for this vertical profile.']);

        $customer = $this->createCustomer($company->id, $branch->id);
        $payload['customer_id'] = $customer->id;

        $this->postJson('/api/v1/pos/sale', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Only cash payment method is allowed for this vertical profile.']);
    }

    private function seedProfiles(): void
    {
        VerticalProfile::query()->updateOrCreate(['code' => 'service_workshop'], ['name' => 'Service Workshop', 'is_active' => true]);
        VerticalProfile::query()->updateOrCreate(['code' => 'retail_pos'], ['name' => 'Retail POS', 'is_active' => true]);
        VerticalProfile::query()->updateOrCreate(['code' => 'fleet_operations'], ['name' => 'Fleet Operations', 'is_active' => true]);
    }

    private function setConfig(string $scopeType, string $scopeKey, string $configKey, bool $value): void
    {
        ConfigSetting::query()->updateOrCreate(
            ['scope_type' => $scopeType, 'scope_key' => $scopeKey, 'config_key' => $configKey],
            ['config_value' => $value ? 'true' : 'false', 'value_type' => 'boolean', 'is_active' => true]
        );
    }

    private function createCustomer(int $companyId, int $branchId): Customer
    {
        return Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'type' => 'individual',
            'name' => 'Runtime Customer',
            'phone' => '0500000000',
            'is_active' => true,
        ]);
    }

    private function createVehicle(int $companyId, int $branchId, int $customerId, int $userId): Vehicle
    {
        return Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'created_by_user_id' => $userId,
            'plate_number' => 'RT-' . random_int(100, 999),
            'make' => 'Toyota',
            'model' => 'Yaris',
            'year' => 2022,
            'is_active' => true,
        ]);
    }

    private function createProduct(int $companyId, int $userId): Product
    {
        return Product::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'created_by_user_id' => $userId,
            'name' => 'Runtime Product',
            'product_type' => 'physical',
            'sale_price' => 10,
            'cost_price' => 8,
            'tax_rate' => 15,
            'is_taxable' => true,
            'track_inventory' => true,
            'is_active' => true,
        ]);
    }
}

