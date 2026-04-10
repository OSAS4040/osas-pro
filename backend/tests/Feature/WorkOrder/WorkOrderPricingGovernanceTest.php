<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Enums\WorkOrderPricingSource;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServicePricingPolicy;
use App\Models\Subscription;
use App\Models\Vehicle;
use App\Models\WorkOrderItem;
use App\Services\WorkOrderPricingResolverService;
use App\Services\WorkOrderService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkOrderPricingGovernanceTest extends TestCase
{
    private function seedTenantFixture(): array
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
            'name' => 'Fleet Customer',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'GOV-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2024,
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'Oil Change',
            'name_ar' => 'تغيير زيت',
            'code' => 'SRV-OIL',
            'base_price' => 100,
            'tax_rate' => 15,
            'estimated_minutes' => 30,
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'user', 'customer', 'vehicle', 'service');
    }

    private function createPolicy(array $overrides): ServicePricingPolicy
    {
        return ServicePricingPolicy::query()->create(array_merge([
            'status' => 'active',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
            'priority' => 1,
        ], $overrides));
    }

    public function test_resolver_uses_customer_specific_before_other_levels(): void
    {
        $fx = $this->seedTenantFixture();

        $group = CustomerGroup::create(['company_id' => $fx['company']->id, 'name' => 'VIP']);
        $fx['customer']->update(['customer_group_id' => $group->id]);

        $contract = Contract::create([
            'uuid' => Str::uuid(),
            'company_id' => $fx['company']->id,
            'title' => 'Fleet Contract',
            'party_name' => 'Fleet Customer',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'created_by' => $fx['user']->id,
        ]);
        $fx['customer']->update(['pricing_contract_id' => $contract->id]);

        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'general',
            'unit_price' => 140,
        ]);
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'contract',
            'contract_id' => $contract->id,
            'unit_price' => 130,
        ]);
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'customer_group',
            'customer_group_id' => $group->id,
            'unit_price' => 120,
        ]);
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'customer_specific',
            'customer_id' => $fx['customer']->id,
            'unit_price' => 110,
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(110.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::CustomerSpecific, $resolved->source);
    }

    public function test_resolver_uses_customer_group_when_no_customer_specific(): void
    {
        $fx = $this->seedTenantFixture();
        $group = CustomerGroup::create(['company_id' => $fx['company']->id, 'name' => 'VIP']);
        $fx['customer']->update(['customer_group_id' => $group->id]);

        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'general',
            'unit_price' => 140,
        ]);
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'customer_group',
            'customer_group_id' => $group->id,
            'unit_price' => 120,
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(120.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::CustomerGroup, $resolved->source);
    }

    public function test_resolver_uses_contract_when_group_and_customer_specific_absent(): void
    {
        $fx = $this->seedTenantFixture();
        $contract = Contract::create([
            'uuid' => Str::uuid(),
            'company_id' => $fx['company']->id,
            'title' => 'Fleet Contract',
            'party_name' => 'Fleet Customer',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'created_by' => $fx['user']->id,
        ]);
        $fx['customer']->update(['pricing_contract_id' => $contract->id]);

        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'general',
            'unit_price' => 140,
        ]);
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'contract',
            'contract_id' => $contract->id,
            'unit_price' => 130,
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(130.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::Contract, $resolved->source);
    }

    public function test_resolver_uses_general_policy_when_higher_levels_absent(): void
    {
        $fx = $this->seedTenantFixture();

        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'general',
            'unit_price' => 140,
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(140.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::GeneralPolicy, $resolved->source);
    }

    public function test_resolver_falls_back_to_service_base_when_no_policy_matches(): void
    {
        $fx = $this->seedTenantFixture();
        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(100.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::GeneralServiceBase, $resolved->source);
    }

    public function test_resolver_uses_highest_priority_then_latest_effective_date_within_same_level(): void
    {
        $fx = $this->seedTenantFixture();
        $group = CustomerGroup::create(['company_id' => $fx['company']->id, 'name' => 'VIP']);
        $fx['customer']->update(['customer_group_id' => $group->id]);

        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'customer_group',
            'customer_group_id' => $group->id,
            'unit_price' => 119,
            'priority' => 10,
            'effective_from' => now()->subDays(7)->toDateString(),
        ]);
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'customer_group',
            'customer_group_id' => $group->id,
            'unit_price' => 118,
            'priority' => 10,
            'effective_from' => now()->subDay()->toDateString(),
        ]);
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'customer_group',
            'customer_group_id' => $group->id,
            'unit_price' => 117,
            'priority' => 11,
            'effective_from' => now()->subDays(30)->toDateString(),
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(117.0, $resolved->unitPrice);
    }

    public function test_manual_price_is_ignored_for_service_lines_in_work_order_service(): void
    {
        $fx = $this->seedTenantFixture();
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'general',
            'unit_price' => 210,
        ]);

        $order = app(WorkOrderService::class)->create([
            'customer_id' => $fx['customer']->id,
            'vehicle_id' => $fx['vehicle']->id,
            'items' => [[
                'item_type' => 'service',
                'service_id' => $fx['service']->id,
                'quantity' => 1,
                'unit_price' => 1,
                'tax_rate' => 0,
            ]],
        ], $fx['company']->id, $fx['branch']->id, $fx['user']->id);

        $item = WorkOrderItem::query()->where('work_order_id', $order->id)->firstOrFail();
        $this->assertSame('210.0000', $item->unit_price);
        $this->assertSame('15.00', $item->tax_rate);
        $this->assertTrue((bool) $item->pricing_resolved_by_system);
    }

    public function test_service_lines_store_pricing_snapshot_fields(): void
    {
        $fx = $this->seedTenantFixture();
        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'general',
            'unit_price' => 210,
            'notes' => 'Snapshot source test',
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

        $item = WorkOrderItem::query()->where('work_order_id', $order->id)->firstOrFail();
        $this->assertNotNull($item->pricing_resolved_at);
        $this->assertNotNull($item->pricing_policy_id);
        $this->assertSame(WorkOrderPricingSource::GeneralPolicy->value, $item->pricing_source);
        $this->assertSame('Snapshot source test', $item->pricing_notes);
    }

    public function test_policies_are_isolated_per_company(): void
    {
        $fxA = $this->seedTenantFixture();
        $fxB = $this->seedTenantFixture();

        $this->createPolicy([
            'company_id' => $fxA['company']->id,
            'service_id' => $fxA['service']->id,
            'policy_type' => 'general',
            'unit_price' => 444,
        ]);

        $resolvedB = app(WorkOrderPricingResolverService::class)->resolve(
            $fxB['company']->id,
            $fxB['customer']->id,
            $fxB['service']->id,
            $fxB['branch']->id,
        );

        $this->assertSame(100.0, $resolvedB->unitPrice);
        $this->assertSame(WorkOrderPricingSource::GeneralServiceBase, $resolvedB->source);
    }

    public function test_when_advanced_pricing_is_disabled_system_uses_service_base_only(): void
    {
        $fx = $this->seedTenantFixture();

        Plan::create([
            'slug' => 'basic',
            'name' => 'Basic',
            'name_ar' => 'أساسي',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency' => 'SAR',
            'features' => ['work_order_advanced_pricing' => false],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Subscription::withoutGlobalScopes()
            ->where('company_id', $fx['company']->id)
            ->update(['plan' => 'basic']);

        $this->createPolicy([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => 'general',
            'unit_price' => 999,
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(100.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::GeneralServiceBase, $resolved->source);
    }
}

