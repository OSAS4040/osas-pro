<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Enums\ServicePricingPolicyType;
use App\Enums\WorkOrderPricingSource;
use App\Models\Contract;
use App\Models\ContractServiceItem;
use App\Models\Customer;
use App\Models\PlatformCustomerPriceVersion;
use App\Models\Service;
use App\Models\ServicePricingPolicy;
use App\Models\Vehicle;
use App\Services\WorkOrderPricingResolverService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ربط تسعير أمر العمل بكتالوج المنصّة المعتمد (sell_snapshot لكل عميل).
 */
final class WorkOrderPlatformCatalogPricingResolverTest extends TestCase
{
    private function seedFx(): array
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Platform priced cust',
            'is_active' => true,
        ]);

        Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'PLT-PC',
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
            'base_price' => 50,
            'tax_rate' => 15,
            'estimated_minutes' => 30,
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'user', 'customer', 'service');
    }

    public function test_platform_snapshot_overrides_policies_and_base_price(): void
    {
        Config::set('pricing.platform_catalog_resolver.enabled', true);

        $fx = $this->seedFx();

        ServicePricingPolicy::create([
            'company_id' => $fx['company']->id,
            'service_id' => $fx['service']->id,
            'policy_type' => ServicePricingPolicyType::CustomerSpecific,
            'customer_id' => $fx['customer']->id,
            'unit_price' => 999,
            'tax_rate' => 15,
            'status' => 'active',
            'priority' => 10,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => now()->addMonth()->toDateString(),
        ]);

        PlatformCustomerPriceVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $fx['company']->id,
            'customer_id' => $fx['customer']->id,
            'contract_id' => null,
            'root_contract_id' => null,
            'platform_pricing_request_id' => null,
            'version_no' => 1,
            'is_reference' => true,
            'sell_snapshot' => [
                ['service_code' => 'SRV-OIL', 'unit_price' => 175, 'tax_rate' => 15],
            ],
            'activated_at' => now(),
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(175.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::PlatformApprovedCatalog, $resolved->source);
        $this->assertSame('platform_approved_catalog', $resolved->resolutionLevel);
    }

    public function test_contract_line_wins_over_platform_snapshot(): void
    {
        Config::set('pricing.platform_catalog_resolver.enabled', true);

        $fx = $this->seedFx();

        $contract = Contract::create([
            'uuid' => Str::uuid(),
            'company_id' => $fx['company']->id,
            'title' => 'MA',
            'party_name' => $fx['customer']->name,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'created_by' => $fx['user']->id,
        ]);
        $fx['customer']->update(['pricing_contract_id' => $contract->id]);

        ContractServiceItem::create([
            'company_id' => $fx['company']->id,
            'contract_id' => $contract->id,
            'service_id' => $fx['service']->id,
            'unit_price' => 88,
            'tax_rate' => 15,
            'applies_to_all_vehicles' => true,
            'status' => 'active',
            'priority' => 0,
        ]);

        PlatformCustomerPriceVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $fx['company']->id,
            'customer_id' => $fx['customer']->id,
            'contract_id' => null,
            'root_contract_id' => null,
            'platform_pricing_request_id' => null,
            'version_no' => 1,
            'is_reference' => true,
            'sell_snapshot' => [
                ['service_code' => 'SRV-OIL', 'unit_price' => 500],
            ],
            'activated_at' => now(),
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(88.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::Contract, $resolved->source);
    }

    public function test_resolver_skips_snapshot_when_disabled_by_config(): void
    {
        Config::set('pricing.platform_catalog_resolver.enabled', false);

        $fx = $this->seedFx();

        PlatformCustomerPriceVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $fx['company']->id,
            'customer_id' => $fx['customer']->id,
            'contract_id' => null,
            'root_contract_id' => null,
            'platform_pricing_request_id' => null,
            'version_no' => 1,
            'is_reference' => true,
            'sell_snapshot' => [
                ['service_code' => 'SRV-OIL', 'unit_price' => 175],
            ],
            'activated_at' => now(),
        ]);

        $resolved = app(WorkOrderPricingResolverService::class)->resolve(
            $fx['company']->id,
            $fx['customer']->id,
            $fx['service']->id,
            $fx['branch']->id,
        );

        $this->assertSame(50.0, $resolved->unitPrice);
        $this->assertSame(WorkOrderPricingSource::GeneralServiceBase, $resolved->source);
    }
}
