<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Models\Customer;
use App\Models\PlatformCustomerPriceVersion;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerPortalPricingTest extends TestCase
{
    public function test_customer_can_list_own_sell_price_versions_read_only(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Portal Customer',
            'email' => 'portal-cust-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);

        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        PlatformCustomerPriceVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'contract_id' => null,
            'root_contract_id' => null,
            'platform_pricing_request_id' => null,
            'version_no' => 1,
            'is_reference' => true,
            'sell_snapshot' => [['service_code' => 'wash', 'unit_price' => 40, 'currency' => 'SAR']],
            'activated_at' => now(),
        ]);

        $res = $this->actingAsUser($customerUser)->getJson('/api/v1/customer-portal/pricing');
        $res->assertOk();
        $res->assertJsonPath('data.versions.0.version_no', 1);
        $res->assertJsonPath('data.versions.0.is_reference', true);
        $res->assertJsonPath('data.versions.0.sell_snapshot.0.service_code', 'wash');
    }

    public function test_staff_cannot_access_customer_pricing_endpoint(): void
    {
        $tenant = $this->createTenant('owner');
        $staff = $tenant['user'];

        $this->actingAsUser($staff)->getJson('/api/v1/customer-portal/pricing')->assertForbidden();
    }

    public function test_customer_without_linked_profile_gets_empty_versions(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];

        $orphan = $this->createUser($company, $branch, 'customer', [
            'email' => 'no-match-'.Str::random(8).'@test.sa',
            'customer_id' => null,
        ]);

        $res = $this->actingAsUser($orphan)->getJson('/api/v1/customer-portal/pricing');
        $res->assertOk();
        $res->assertJsonPath('data.versions', []);
    }
}
