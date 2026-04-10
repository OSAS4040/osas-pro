<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Database\Seeders\DemoOperationsSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoOperationsSimulationTest extends TestCase
{
    use RefreshDatabase;

    private function enableIntelligenceApis(): void
    {
        config(['intelligent.internal_dashboard.enabled' => true]);
        config(['intelligent.read_models.enabled' => true]);
        config(['intelligent.phase2.enabled' => true]);
        config(['intelligent.overview_api.enabled' => true]);
        config(['intelligent.insights.enabled' => true]);
        config(['intelligent.recommendations.enabled' => true]);
        config(['intelligent.alerts.enabled' => true]);
        config(['intelligent.command_center_api.enabled' => true]);
        config(['intelligent.phase2.features.overview' => true]);
        config(['intelligent.phase2.features.insights' => true]);
        config(['intelligent.phase2.features.recommendations' => true]);
        config(['intelligent.phase2.features.alerts' => true]);
        config(['intelligent.phase2.features.command_center' => true]);
    }

    public function test_demo_operations_seeder_counts_and_dashboard_and_intelligence_ok(): void
    {
        $this->enableIntelligenceApis();

        $this->seed(PlanSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoOperationsSeeder::class);

        $company = Company::where('email', DemoOperationsSeeder::COMPANY_EMAIL)->first();
        $this->assertNotNull($company);

        $cid = $company->id;
        $this->assertSame(20, Customer::withoutGlobalScope('tenant')->where('company_id', $cid)->count());
        $this->assertSame(100, Invoice::withoutGlobalScope('tenant')->where('company_id', $cid)->count());
        $this->assertSame(100, Payment::withoutGlobalScope('tenant')->where('company_id', $cid)->count());

        $user = \App\Models\User::where('email', DemoOperationsSeeder::OWNER_EMAIL)->first();
        $this->assertNotNull($user);

        $from = now()->subYear()->toDateString();
        $to   = now()->addDay()->toDateString();

        $this->actingAsUser($user)
            ->getJson('/api/v1/dashboard/summary?from='.$from.'&to='.$to)
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->actingAsUser($user)
            ->getJson('/api/v1/internal/intelligence/command-center')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->actingAsUser($user)
            ->getJson('/api/v1/internal/intelligence/overview')
            ->assertOk();
    }
}
