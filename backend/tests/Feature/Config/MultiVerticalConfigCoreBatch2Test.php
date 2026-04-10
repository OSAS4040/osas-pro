<?php

namespace Tests\Feature\Config;

use App\Enums\WalletType;
use App\Models\ConfigSetting;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiVerticalConfigCoreBatch2Test extends TestCase
{
    public function test_quotes_enabled_toggle_changes_behavior(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);

        $this->setConfig('company', (string) $company->id, 'quotes.enabled', false);

        $response = $this->postJson('/api/v1/quotes', []);
        $response->assertStatus(403)->assertJsonFragment(['message' => 'Quotes are disabled by configuration.']);
    }

    public function test_wallet_enabled_toggle_changes_behavior(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);
        app()->instance('tenant_company_id', $company->id);
        app()->instance('tenant_branch_id', $branch->id);

        $this->setConfig('company', (string) $company->id, 'wallet.enabled', false);

        $response = $this->getJson('/api/v1/wallet');
        $response->assertStatus(403)->assertJsonFragment(['message' => 'Wallet is disabled by configuration.']);
    }

    public function test_bookings_enabled_toggle_changes_behavior(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);

        $this->setConfig('company', (string) $company->id, 'bookings.enabled', false);

        $response = $this->postJson('/api/v1/bookings', []);
        $response->assertStatus(403)->assertJsonFragment(['message' => 'Bookings are disabled by configuration.']);
    }

    public function test_work_order_requires_bay_assignment_when_enabled(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);
        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Customer A',
            'phone' => '0500000000',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'TST-101',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'is_active' => true,
        ]);

        $this->setConfig('company', (string) $company->id, 'work_orders.require_bay_assignment', true);

        $response = $this->postJson('/api/v1/work-orders', [
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
        ]);

        $response->assertStatus(422)->assertJsonFragment(['message' => 'bay_id is required when bay assignment is enabled.']);
    }

    public function test_fleet_approval_required_toggle_changes_verify_plate_behavior(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);
        app()->instance('tenant_company_id', $company->id);
        app()->instance('tenant_branch_id', $branch->id);

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'fleet',
            'name' => 'Fleet Co',
            'phone' => '0511111111',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'FLT-999',
            'make' => 'Ford',
            'model' => 'Ranger',
            'year' => 2023,
            'is_active' => true,
        ]);
        WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $user->id,
            'order_number' => 'WO-FLT-001',
            'status' => 'pending_manager_approval',
            'approval_status' => 'pending',
            'credit_authorized' => false,
        ]);
        CustomerWallet::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'wallet_type' => WalletType::VehicleWallet->value,
            'status' => 'active',
            'balance' => 100,
            'currency' => 'SAR',
            'version' => 1,
        ]);

        $this->setConfig('company', (string) $company->id, 'fleet.approval_required', false);

        $response = $this->postJson('/api/v1/fleet/verify-plate', ['plate_number' => 'FLT-999']);
        $response->assertOk()->assertJsonPath('verdict.can_proceed', true);
    }

    private function setConfig(string $scopeType, string $scopeKey, string $configKey, bool $value): void
    {
        ConfigSetting::query()->updateOrCreate(
            ['scope_type' => $scopeType, 'scope_key' => $scopeKey, 'config_key' => $configKey],
            ['config_value' => $value ? 'true' : 'false', 'value_type' => 'boolean', 'is_active' => true]
        );
    }
}

