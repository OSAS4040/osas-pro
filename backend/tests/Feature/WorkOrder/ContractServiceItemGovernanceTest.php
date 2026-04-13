<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Enums\ServicePricingPolicyType;
use App\Enums\WorkOrderPricingSource;
use App\Models\Contract;
use App\Models\ContractServiceItem;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServicePricingPolicy;
use App\Models\Vehicle;
use App\Services\WorkOrderPricingResolverService;
use App\Services\WorkOrderService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ContractServiceItemGovernanceTest extends TestCase
{
    private function baseFx(): array
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Contract Cust',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'CNT-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'Oil',
            'name_ar' => 'زيت',
            'code' => 'OIL',
            'base_price' => 50,
            'tax_rate' => 15,
            'estimated_minutes' => 20,
            'is_active' => true,
        ]);

        $contract = Contract::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'title' => 'Fleet MA',
            'party_name' => $customer->name,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $customer->update(['pricing_contract_id' => $contract->id]);

        return compact('company', 'branch', 'user', 'customer', 'vehicle', 'service', 'contract');
    }

    public function test_contract_service_line_price_wins_over_customer_specific_policy(): void
    {
        $fx = $this->baseFx();

        ContractServiceItem::create([
            'company_id' => $fx['company']->id,
            'contract_id' => $fx['contract']->id,
            'service_id' => $fx['service']->id,
            'unit_price' => 88,
            'tax_rate' => 15,
            'applies_to_all_vehicles' => true,
            'status' => 'active',
            'priority' => 0,
        ]);

        ServicePricingPolicy::create([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => ServicePricingPolicyType::CustomerSpecific,
            'customer_id' => $fx['customer']->id,
            'unit_price' => 200,
            'tax_rate' => 15,
            'status' => 'active',
            'priority' => 10,
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
            null,
            (int) $fx['vehicle']->id,
            false,
            1.0,
        );

        $this->assertSame(88.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::Contract, $resolved->source);
        $this->assertSame('contract_service_item', $resolved->resolutionLevel);
        $this->assertNotNull($resolved->contractServiceItemId);
        $this->assertNull($resolved->policyId);
    }

    public function test_fleet_origin_rejects_service_not_on_contract(): void
    {
        $fx = $this->baseFx();

        $this->expectException(\DomainException::class);
        app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
            null,
            (int) $fx['vehicle']->id,
            true,
            1.0,
        );
    }

    public function test_vehicle_scoped_line_excludes_wrong_vehicle_for_fleet(): void
    {
        $fx = $this->baseFx();

        $otherVehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $fx['company']->id,
            'branch_id' => $fx['branch']->id,
            'customer_id' => $fx['customer']->id,
            'created_by_user_id' => $fx['user']->id,
            'plate_number' => 'CNT-002',
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => 2023,
        ]);

        ContractServiceItem::create([
            'company_id' => $fx['company']->id,
            'contract_id' => $fx['contract']->id,
            'service_id' => $fx['service']->id,
            'unit_price' => 77,
            'tax_rate' => 15,
            'applies_to_all_vehicles' => false,
            'vehicle_ids' => [(int) $fx['vehicle']->id],
            'status' => 'active',
        ]);

        $ok = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
            null,
            (int) $fx['vehicle']->id,
            true,
            1.0,
        );
        $this->assertSame(77.0, $ok->unitPrice);

        $this->expectException(\DomainException::class);
        app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
            null,
            (int) $otherVehicle->id,
            true,
            1.0,
        );
    }

    public function test_work_order_item_stores_contract_line_snapshot(): void
    {
        $fx = $this->baseFx();

        $line = ContractServiceItem::create([
            'company_id' => $fx['company']->id,
            'contract_id' => $fx['contract']->id,
            'service_id' => $fx['service']->id,
            'unit_price' => 60,
            'tax_rate' => 5,
            'applies_to_all_vehicles' => true,
            'status' => 'active',
        ]);

        $order = app(WorkOrderService::class)->create([
            'customer_id' => $fx['customer']->id,
            'vehicle_id' => $fx['vehicle']->id,
            'items' => [[
                'item_type' => 'service',
                'service_id' => $fx['service']->id,
                'quantity' => 1,
            ]],
        ], $fx['company']->id, $fx['branch']->id, $fx['user']->id);

        $item = $order->items()->firstOrFail();
        $this->assertSame((int) $line->id, (int) $item->pricing_contract_service_item_id);
        $this->assertSame(WorkOrderPricingSource::Contract->value, $item->pricing_source);
        $this->assertNull($item->pricing_policy_id);
    }

    public function test_contract_line_max_total_quantity_enforced(): void
    {
        $fx = $this->baseFx();

        ContractServiceItem::create([
            'company_id' => $fx['company']->id,
            'contract_id' => $fx['contract']->id,
            'service_id' => $fx['service']->id,
            'unit_price' => 10,
            'tax_rate' => 15,
            'applies_to_all_vehicles' => true,
            'max_total_quantity' => 1,
            'status' => 'active',
        ]);

        app(WorkOrderService::class)->create([
            'customer_id' => $fx['customer']->id,
            'vehicle_id' => $fx['vehicle']->id,
            'items' => [[
                'item_type' => 'service',
                'service_id' => $fx['service']->id,
                'quantity' => 1,
            ]],
        ], $fx['company']->id, $fx['branch']->id, $fx['user']->id);

        $this->expectException(\DomainException::class);
        app(WorkOrderService::class)->create([
            'customer_id' => $fx['customer']->id,
            'vehicle_id' => $fx['vehicle']->id,
            'items' => [[
                'item_type' => 'service',
                'service_id' => $fx['service']->id,
                'quantity' => 1,
            ]],
        ], $fx['company']->id, $fx['branch']->id, $fx['user']->id);
    }

    public function test_staff_line_pricing_preview_returns_contract_price(): void
    {
        $fx = $this->baseFx();

        ContractServiceItem::create([
            'company_id' => $fx['company']->id,
            'contract_id' => $fx['contract']->id,
            'service_id' => $fx['service']->id,
            'unit_price' => 88,
            'tax_rate' => 15,
            'applies_to_all_vehicles' => true,
            'status' => 'active',
            'priority' => 0,
        ]);

        $this->actingAs($fx['user'], 'sanctum')
            ->postJson('/api/v1/work-orders/line-pricing-preview', [
                'customer_id' => $fx['customer']->id,
                'vehicle_id' => $fx['vehicle']->id,
                'service_id' => $fx['service']->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.unit_price', 88.0)
            ->assertJsonPath('data.pricing_source', WorkOrderPricingSource::Contract->value);
    }
}
