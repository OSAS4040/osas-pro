<?php

declare(strict_types=1);

namespace Tests\Feature\ProductionReadiness;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** CUSTOMER gate — create + fetch (automated). */
#[Group('production-readiness')]
final class ProductionReadinessCustomerCrudTest extends TestCase
{
    public function test_customer_create_and_show_round_trip(): void
    {
        $tenant = $this->createTenant('owner');

        $create = $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/customers', [
                'type'  => 'b2b',
                'name'  => 'Gate Customer Co',
                'phone' => '0500999001',
            ]);

        $create->assertStatus(201)->assertJsonStructure(['data', 'trace_id']);
        $id = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $id);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/customers/'.$id)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Gate Customer Co');
    }
}
