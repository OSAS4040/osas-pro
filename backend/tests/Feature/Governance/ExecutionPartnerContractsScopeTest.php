<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Models\Company;
use App\Models\Contract;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ExecutionPartnerContractsScopeTest extends TestCase
{
    private function enablePlatformExecutionPartner(Company $company): void
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $profile = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $matrix = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];
        $matrix['platform_execution_partner'] = true;
        $profile['feature_matrix'] = $matrix;
        $profile['business_type'] = $profile['business_type'] ?? 'service_center';
        $settings['business_profile'] = $profile;
        $company->update(['settings' => $settings]);
    }

    public function test_execution_partner_index_lists_only_platform_provider_agreements(): void
    {
        $t = $this->createTenant('owner');

        $other = Contract::create([
            'uuid'       => Str::uuid(),
            'company_id' => $t['company']->id,
            'title'      => 'عقد طرف آخر',
            'party_name' => 'عميل',
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'status'     => 'active',
            'created_by' => $t['user']->id,
            'metadata'   => null,
        ]);

        $withPlatform = Contract::create([
            'uuid'       => Str::uuid(),
            'company_id' => $t['company']->id,
            'title'      => 'إطار مع المنصّة',
            'party_name' => 'أسس برو',
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'status'     => 'active',
            'created_by' => $t['user']->id,
            'metadata'   => ['platform_provider_agreement' => true],
        ]);

        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $res = $this->actingAsUser($t['user'])->getJson('/api/v1/governance/contracts');
        $res->assertOk();
        $rows = $res->json('data.data');
        $this->assertIsArray($rows);
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($withPlatform->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_execution_partner_cannot_access_other_contract_service_items(): void
    {
        $t = $this->createTenant('owner');

        $other = Contract::create([
            'uuid'       => Str::uuid(),
            'company_id' => $t['company']->id,
            'title'      => 'عقد داخلي',
            'party_name' => 'X',
            'start_date' => now()->subDay()->toDateString(),
            'end_date'   => now()->addYear()->toDateString(),
            'status'     => 'active',
            'created_by' => $t['user']->id,
        ]);

        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/governance/contracts/'.$other->id.'/service-items')
            ->assertNotFound();
    }

    public function test_execution_partner_cannot_create_contract_via_api(): void
    {
        $t = $this->createTenant('owner');
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $this->actingAsUser($t['user'])
            ->postJson('/api/v1/governance/contracts', [
                'title' => 'Test',
                'party_name' => 'Y',
                'party_type' => 'company',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
            ])
            ->assertForbidden();
    }
}
