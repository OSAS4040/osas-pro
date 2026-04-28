<?php

namespace Tests\Feature\Saas;

use Database\Seeders\PlanAddonSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_01_PROGRESS_REPORT.md
 */
#[Group('phase1')]
class PlanAddonSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plans_payload_includes_plan_addons_catalog(): void
    {
        (new PlanSeeder)->run();
        (new PlanAddonSeeder)->run();

        $res = $this->getJson('/api/v1/plans');

        $res->assertSuccessful();
        $res->assertJsonStructure(['data', 'plan_addons', 'trace_id']);
        $slugs = collect($res->json('plan_addons'))->pluck('slug')->all();
        $this->assertContains('addon_smart_reports', $slugs);
    }

    public function test_tenant_owner_can_attach_addon_and_feature_surfaces_on_subscription(): void
    {
        (new PlanSeeder)->run();
        (new PlanAddonSeeder)->run();

        ['company' => $company, 'branch' => $branch, 'user' => $user, 'subscription' => $sub] = $this->createTenant('owner');
        $sub->update(['plan' => 'basic']);

        $token = $user->createToken('t')->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept'        => 'application/json',
        ];

        $purchase = $this->postJson('/api/v1/subscription/addons', ['addon_slug' => 'addon_smart_reports'], $headers);
        $purchase->assertStatus(201);
        $purchase->assertJsonPath('data.active_addons.0.slug', 'addon_smart_reports');

        $current = $this->getJson('/api/v1/subscription', $headers);
        $current->assertSuccessful();
        $current->assertJsonPath('data.plan.features.smart_reports', true);
        $current->assertJsonPath('data.active_addons.0.feature_key', 'smart_reports');
    }
}
