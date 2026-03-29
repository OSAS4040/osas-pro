<?php

namespace Tests\Feature\Company;

use Tests\TestCase;

class CompanyTest extends TestCase
{
    public function test_owner_can_view_own_company(): void
    {
        $tenant = $this->createTenant('owner');

        $response = $this->actingAsUser($tenant['user'])
            ->getJson("/api/v1/companies/{$tenant['company']->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tenant['company']->id);
    }

    public function test_owner_cannot_view_another_companys_data(): void
    {
        $tenant1 = $this->createTenant('owner');
        $tenant2 = $this->createTenant('owner');

        $response = $this->actingAsUser($tenant1['user'])
            ->getJson("/api/v1/companies/{$tenant2['company']->id}");

        $response->assertStatus(403);
    }

    public function test_owner_can_update_own_company(): void
    {
        $tenant = $this->createTenant('owner');

        $response = $this->actingAsUser($tenant['user'])
            ->putJson("/api/v1/companies/{$tenant['company']->id}", [
                'name' => 'Updated Company Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Company Name');
    }

    public function test_cashier_cannot_update_company(): void
    {
        $tenant = $this->createTenant('cashier');

        $response = $this->actingAsUser($tenant['user'])
            ->putJson("/api/v1/companies/{$tenant['company']->id}", [
                'name' => 'Hacked Company Name',
            ]);

        $response->assertStatus(403);
    }
}
