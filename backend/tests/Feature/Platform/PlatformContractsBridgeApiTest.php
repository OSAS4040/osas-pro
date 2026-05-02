<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Contract;
use App\Models\ContractServiceItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformContractsBridgeApiTest extends TestCase
{
    public function test_services_bridge_lists_tenant_services_for_platform_user(): void
    {
        Config::set('platform.admin_enabled', true);
        $t = $this->createTenant('owner');
        $company = $t['company'];
        $branch = $t['branch'];
        $user = $t['user'];

        $svc = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'Oil svc bridge',
            'code' => 'OIL-BRG',
            'base_price' => 55,
            'tax_rate' => 15,
            'estimated_minutes' => 20,
            'is_active' => true,
        ]);

        $platEmail = 'bridge-svc-'.Str::random(6).'@platform.test';
        $this->createStandalonePlatformOperator($platEmail, [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', $platEmail)->firstOrFail();

        $res = $this->actingAsUser($platformUser)->getJson('/api/v1/platform/companies/'.$company->id.'/services-bridge');
        $res->assertOk();
        $rows = $res->json('data');
        $this->assertIsArray($rows);
        $codes = array_column($rows, 'code');
        $this->assertContains('OIL-BRG', $codes);
        $this->assertSame($svc->id, collect($rows)->firstWhere('code', 'OIL-BRG')['id']);
    }

    public function test_contracts_bridge_filters_by_service_id(): void
    {
        Config::set('platform.admin_enabled', true);
        $t = $this->createTenant('owner');
        $company = $t['company'];
        $branch = $t['branch'];
        $user = $t['user'];

        $svcA = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'A',
            'code' => 'A',
            'base_price' => 10,
            'tax_rate' => 15,
            'estimated_minutes' => 10,
            'is_active' => true,
        ]);
        $svcB = Service::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'B',
            'code' => 'B',
            'base_price' => 20,
            'tax_rate' => 15,
            'estimated_minutes' => 10,
            'is_active' => true,
        ]);

        $contract = Contract::withoutGlobalScopes()->create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'title' => 'C1',
            'party_name' => 'P',
            'party_type' => 'company',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        ContractServiceItem::query()->create([
            'company_id' => $company->id,
            'contract_id' => $contract->id,
            'service_id' => $svcA->id,
            'unit_price' => 99,
            'tax_rate' => 15,
            'discount_amount' => 0,
            'applies_to_all_vehicles' => true,
            'status' => 'active',
            'priority' => 0,
        ]);

        $platEmail = 'bridge-co-'.Str::random(6).'@platform.test';
        $this->createStandalonePlatformOperator($platEmail, [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', $platEmail)->firstOrFail();

        $all = $this->actingAsUser($platformUser)->getJson('/api/v1/platform/companies/'.$company->id.'/contracts-bridge');
        $all->assertOk();
        $this->assertGreaterThanOrEqual(1, count($all->json('data')));

        $filtered = $this->actingAsUser($platformUser)->getJson(
            '/api/v1/platform/companies/'.$company->id.'/contracts-bridge?service_id='.$svcB->id
        );
        $filtered->assertOk();
        $this->assertCount(0, $filtered->json('data'));

        $matchA = $this->actingAsUser($platformUser)->getJson(
            '/api/v1/platform/companies/'.$company->id.'/contracts-bridge?service_id='.$svcA->id
        );
        $matchA->assertOk();
        $this->assertCount(1, $matchA->json('data'));
    }

    public function test_companies_index_accepts_search_query(): void
    {
        Config::set('platform.admin_enabled', true);
        $name = 'UniqueSearchCo-'.Str::random(8);
        $c = $this->createCompany(['name' => $name]);

        $platEmail = 'bridge-list-'.Str::random(6).'@platform.test';
        $this->createStandalonePlatformOperator($platEmail, [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', $platEmail)->firstOrFail();

        $res = $this->actingAsUser($platformUser)->getJson('/api/v1/platform/companies?search='.urlencode($name));
        $res->assertOk();
        $ids = array_column($res->json('data'), 'id');
        $this->assertContains($c->id, $ids);
    }
}
