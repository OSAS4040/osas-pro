<?php

namespace Tests\Feature\Onboarding;

use Tests\TestCase;

class SetupStatusTest extends TestCase
{
    public function test_setup_status_returns_json_for_authenticated_user(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['company']->update([
            'phone' => '0500000000',
            'tax_number' => '300000000000003',
        ]);

        $res = $this->actingAsUser($tenant['user'])->getJson('/api/v1/onboarding/setup-status');

        $res->assertOk();
        $res->assertJsonPath('data.company_profile_ok', true);
        $res->assertJsonPath('data.branch_ok', true);
        $res->assertJsonPath('data.team_ok', false);
        $res->assertJsonStructure([
            'data' => [
                'company_profile_ok',
                'branches_count',
                'users_count',
                'policies_count',
                'products_count',
                'has_priced_catalog',
                'team_ok',
                'permissions_ok',
                'branch_ok',
                'product_ok',
            ],
        ]);
    }
}
