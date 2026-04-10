<?php

namespace Tests\Feature\Subscription;

use Tests\TestCase;

/**
 * Real-time subscription rules without relying on queue to flip status.
 */
class SubscriptionRealtimeEnforcementTest extends TestCase
{
    public function test_expired_without_grace_blocks_reads(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update([
            'ends_at'       => now()->subDay(),
            'grace_ends_at' => now()->subHour(),
            'status'        => 'active',
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/customers')
            ->assertStatus(402);
    }

    public function test_active_period_allows_writes(): void
    {
        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/customers', [
                'name'  => 'Realtime OK',
                'type'  => 'b2c',
                'phone' => '+966500000099',
            ])
            ->assertSuccessful();
    }

    public function test_expired_with_active_grace_allows_login(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update([
            'ends_at'       => now()->subDay(),
            'grace_ends_at' => now()->addDays(5),
            'status'        => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ])->assertSuccessful();
    }

    public function test_grace_period_allows_read_blocks_write(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update([
            'ends_at'       => now()->subDay(),
            'grace_ends_at' => now()->addDays(14),
            'status'        => 'grace_period',
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/customers')
            ->assertSuccessful();

        $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/customers', [
                'name'  => 'Grace Write Block',
                'type'  => 'b2c',
                'phone' => '+966500000088',
            ])
            ->assertStatus(423);
    }

    public function test_suspended_blocks_api_access(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['subscription']->update(['status' => 'suspended']);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/customers')
            ->assertStatus(402);
    }
}
