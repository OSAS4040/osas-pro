<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BranchScopeTenantValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_branch_id_from_foreign_company(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $this->actingAsUser($a['user'])
            ->withHeaders(['X-Branch-Id' => (string) $b['branch']->id])
            ->getJson('/api/v1/work-orders/intake-lookup?'.http_build_query(['plate_number' => 'ZZZ 9999']))
            ->assertForbidden();
    }

    public function test_allows_branch_id_from_same_company_for_cross_branch_role(): void
    {
        $t = $this->createTenant('owner');
        $branch2 = $this->createBranch($t['company'], [
            'name' => 'Other',
            'code' => 'OTH',
            'is_main' => false,
        ]);

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/work-orders/intake-lookup?'.http_build_query([
                'branch_id' => $branch2->id,
                'plate_number' => 'ZZZ 8888',
            ]))
            ->assertOk();
    }
}
