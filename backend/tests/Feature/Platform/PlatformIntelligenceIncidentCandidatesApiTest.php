<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformIntelligenceIncidentCandidatesApiTest extends TestCase
{
    public function test_incident_candidates_requires_candidates_read_permission(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['no-cand@platform.test']);

        $blocked = $this->createStandalonePlatformOperator('no-cand@platform.test', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);

        $this->actingAsUser($blocked->fresh())
            ->getJson('/api/v1/platform/intelligence/incident-candidates')
            ->assertForbidden();
    }

    public function test_incident_candidates_returns_contract_shape(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['cand-api@platform.test']);
        $this->createStandalonePlatformOperator('cand-api@platform.test', [
            'platform_role' => 'platform_admin',
        ]);

        $res = $this->actingAsUser(\App\Models\User::where('email', 'cand-api@platform.test')->firstOrFail())
            ->getJson('/api/v1/platform/intelligence/incident-candidates');

        $res->assertSuccessful()
            ->assertJsonPath('meta.candidate_rules_version', '1.0.0')
            ->assertJsonPath('meta.candidate_order', 'severity_desc,confidence_desc,last_seen_desc,incident_key_asc_tiebreak');

        $rows = $res->json('data');
        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertIsArray($row);
            foreach ([
                'incident_key', 'incident_type', 'title', 'summary', 'why_summary', 'severity', 'confidence',
                'source_signals', 'affected_scope', 'affected_entities', 'affected_companies', 'first_seen_at',
                'last_seen_at', 'recommended_actions', 'grouping_reason', 'dedupe_fingerprint',
            ] as $k) {
                $this->assertArrayHasKey($k, $row, 'missing '.$k);
            }
        }
    }
}
