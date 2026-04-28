<?php

namespace Tests\Feature\System;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_00_CLOSURE_REPORT.md — كتالوج القدرات (مرحلة 0)
 */
#[Group('phase0')]
class SystemCapabilitiesEndpointTest extends TestCase
{
    public function test_owner_receives_capability_catalog_for_tenant(): void
    {
        $t = $this->createTenant('owner');

        $response = $this->actingAsUser($t['user'])->getJson('/api/v1/system/capabilities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'business_type',
                    'business_type_label' => ['ar', 'en'],
                    'items' => [
                        [
                            'id',
                            'section',
                            'title',
                            'description',
                            'status',
                            'rollout',
                        ],
                    ],
                ],
                'trace_id',
            ]);

        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual(10, count($items));
        $ids = array_column($items, 'id');
        $this->assertContains('supplier_contract_layer', $ids);
        $this->assertContains('dashboard', $ids);

        $this->assertSame('cancelled', collect($items)->firstWhere('id', 'supplier_portal')['status'] ?? null);
        $this->assertSame('post_launch', collect($items)->firstWhere('id', 'driver_field_app')['status'] ?? null);
    }

    public function test_fixed_assets_catalog_entry_is_planned_without_path(): void
    {
        $t = $this->createTenant('owner');

        $response = $this->actingAsUser($t['user'])->getJson('/api/v1/system/capabilities');
        $response->assertStatus(200);

        $items = $response->json('data.items');
        $row = collect($items)->firstWhere('id', 'fixed_assets');
        $this->assertNotNull($row, 'fixed_assets must exist in system_capabilities catalog');
        $this->assertSame('planned', $row['status'] ?? null);
        $this->assertArrayHasKey('path', $row);
        $this->assertNull($row['path']);
        $this->assertSame('fixed_assets', $row['gate'] ?? null);
    }

    public function test_fleet_contact_is_forbidden(): void
    {
        $t = $this->createTenant('fleet_contact');

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/system/capabilities')
            ->assertStatus(403)
            ->assertJsonPath('code', 'capabilities_staff_only');
    }

    public function test_retail_disables_supplier_contract_gate_in_catalog(): void
    {
        $t = $this->createTenant('owner');
        $t['company']->update([
            'settings' => [
                'business_profile' => [
                    'business_type'  => 'retail',
                    'feature_matrix' => [],
                ],
            ],
        ]);

        $response = $this->actingAsUser($t['user'])->getJson('/api/v1/system/capabilities');
        $response->assertStatus(200);

        $items = $response->json('data.items');
        $row    = collect($items)->firstWhere('id', 'supplier_contract_layer');
        $this->assertNotNull($row);
        $this->assertSame('restricted_activity', $row['status']);
        $this->assertNull($row['path']);
        $this->assertSame('supplier_contract_mgmt', $row['gate']);
    }
}
