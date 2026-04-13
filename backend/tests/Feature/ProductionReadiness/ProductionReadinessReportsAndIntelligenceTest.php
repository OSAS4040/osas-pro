<?php

declare(strict_types=1);

namespace Tests\Feature\ProductionReadiness;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * REPORTS + INTELLIGENCE gate — no 5xx, structured payloads (read-only / flags on in test only).
 */
#[Group('production-readiness')]
final class ProductionReadinessReportsAndIntelligenceTest extends TestCase
{
    /**
     * @return array{from: string, to: string}
     */
    private function dateRange(): array
    {
        return [
            'from' => now()->subDays(7)->toDateString(),
            'to'   => now()->addDay()->toDateString(),
        ];
    }

    public function test_reporting_pulse_and_legacy_dictionary_do_not_server_error(): void
    {
        $tenant = $this->createTenant('owner');
        $auth = fn () => $this->actingAsUser($tenant['user']);
        $q = http_build_query($this->dateRange());

        $auth()->getJson('/api/v1/reports/kpi-dictionary')
            ->assertOk()
            ->assertJsonStructure(['data', 'trace_id']);

        $auth()->getJson('/api/v1/reporting/v1/company/pulse-summary?'.$q)
            ->assertOk()
            ->assertJsonPath('report.read_only', true)
            ->assertJsonStructure(['data' => ['summary'], 'trace_id']);
    }

    public function test_phase2_intelligence_overview_returns_structured_payload_when_enabled(): void
    {
        Config::set('intelligent.internal_dashboard.enabled', true);
        Config::set('intelligent.phase2.enabled', true);
        Config::set('intelligent.phase2.features.overview', true);

        $tenant = $this->createTenant('owner');

        $res = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/internal/intelligence/overview');

        $res->assertOk()
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonStructure(['data', 'meta', 'trace_id']);

        $this->assertIsArray($res->json('data'));
    }
}
