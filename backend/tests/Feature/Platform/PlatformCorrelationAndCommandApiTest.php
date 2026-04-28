<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\PlatformIncident;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformCorrelationAndCommandApiTest extends TestCase
{
    public function test_command_surface_requires_incidents_read(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('cc-surf@platform.test', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);
        $user = User::where('email', 'cc-surf@platform.test')->firstOrFail();

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/command-surface')
            ->assertForbidden();
    }

    public function test_command_surface_returns_sections_for_platform_admin(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('cc-ops@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'cc-ops@platform.test')->firstOrFail();

        PlatformIncident::query()->create([
            'incident_key' => 'icand_cc_open',
            'incident_type' => 'candidate.single_signal',
            'title' => 'T',
            'summary' => 'S',
            'why_summary' => 'W',
            'severity' => 'high',
            'confidence' => 0.9,
            'status' => 'open',
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'escalated',
            'affected_scope' => 'tenant:1',
            'affected_entities' => [],
            'affected_companies' => [1],
            'source_signals' => ['sig_cc_1'],
            'recommended_actions' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'acknowledged_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'last_status_change_at' => now(),
            'resolve_reason' => null,
            'close_reason' => null,
            'operator_notes' => null,
        ]);

        $res = $this->actingAsUser($user)->getJson('/api/v1/platform/intelligence/command-surface');
        $res->assertOk();
        $res->assertJsonStructure([
            'summary',
            'open_high_severity_incidents',
            'recently_escalated_incidents',
            'meta',
        ]);
        $this->assertGreaterThanOrEqual(1, count($res->json('open_high_severity_incidents')));
    }

    public function test_incident_correlation_requires_read_and_returns_data(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('cc-cor@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'cc-cor@platform.test')->firstOrFail();

        PlatformIncident::query()->create([
            'incident_key' => 'icand_cc_cor',
            'incident_type' => 'candidate.single_signal',
            'title' => 'T',
            'summary' => 'S',
            'why_summary' => 'W',
            'severity' => 'medium',
            'confidence' => 0.5,
            'status' => 'open',
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'none',
            'affected_scope' => 'tenant:1',
            'affected_entities' => [],
            'affected_companies' => [1],
            'source_signals' => [],
            'recommended_actions' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'acknowledged_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'last_status_change_at' => now(),
            'resolve_reason' => null,
            'close_reason' => null,
            'operator_notes' => null,
        ]);

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/incidents/icand_cc_cor/correlation')
            ->assertOk()
            ->assertJsonPath('data.incident.incident_key', 'icand_cc_cor');
    }

    public function test_correlation_404_for_unknown_incident(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('cc-404@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'cc-404@platform.test')->firstOrFail();

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/incidents/missing_xyz/correlation')
            ->assertNotFound();
    }
}
