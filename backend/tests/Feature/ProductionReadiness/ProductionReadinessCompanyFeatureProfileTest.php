<?php

declare(strict_types=1);

namespace Tests\Feature\ProductionReadiness;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * COMPANY gate — rich profile + feature profile GET/PATCH (no financial core changes).
 */
#[Group('production-readiness')]
final class ProductionReadinessCompanyFeatureProfileTest extends TestCase
{
    public function test_company_profile_endpoint_returns_envelope(): void
    {
        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/companies/'.$tenant['company']->id.'/profile')
            ->assertOk()
            ->assertJsonPath('data.company.id', $tenant['company']->id)
            ->assertJsonStructure(['data', 'meta', 'trace_id']);
    }

    public function test_feature_profile_get_and_patch_round_trip(): void
    {
        $tenant = $this->createTenant('owner');
        $cid = $tenant['company']->id;
        $auth = fn () => $this->actingAsUser($tenant['user']);

        $auth()->getJson("/api/v1/companies/{$cid}/feature-profile")
            ->assertOk()
            ->assertJsonStructure(['data' => ['business_type', 'effective_feature_matrix'], 'trace_id']);

        $auth()->patchJson("/api/v1/companies/{$cid}/feature-profile", [
            'business_type'  => 'fleet_operator',
            'feature_matrix' => ['fleet' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.business_type', 'fleet_operator')
            ->assertJsonStructure(['data' => ['effective_feature_matrix'], 'trace_id']);

        $auth()->getJson("/api/v1/companies/{$cid}/feature-profile")
            ->assertOk()
            ->assertJsonPath('data.business_type', 'fleet_operator');
    }
}
