<?php

namespace Tests\Feature\Tenancy;

use Tests\TestCase;
use App\Models\Invoice;

class TenantIsolationTest extends TestCase
{
    public function test_user_cannot_see_other_companys_invoices(): void
    {
        $tenant1 = $this->createTenant('owner');
        $tenant2 = $this->createTenant('owner');

        $response = $this->actingAsUser($tenant1['user'])
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);

        $ids = collect($response->json('data.data'))->pluck('company_id')->unique();

        $this->assertNotContains($tenant2['company']->id, $ids->toArray());
    }

    public function test_suspended_subscription_blocks_login(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update(['status' => 'suspended']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(402);
    }

    public function test_grace_period_blocks_write_operations(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update([
            'ends_at'       => now()->subDay(),
            'grace_ends_at' => now()->addDays(14),
            'status'        => 'grace_period',
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/customers', [
                'name'  => 'Test Customer',
                'type'  => 'b2c',
                'phone' => '+966500000002',
            ]);

        $response->assertStatus(423);
    }

    public function test_grace_period_allows_read_operations(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update([
            'ends_at'       => now()->subDay(),
            'grace_ends_at' => now()->addDays(14),
            'status'        => 'grace_period',
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/invoices')->assertStatus(401);
    }

    public function test_trace_id_is_present_in_response(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/auth/me');

        $response->assertJsonStructure(['trace_id']);
        $this->assertNotEmpty($response->json('trace_id'));
    }
}
