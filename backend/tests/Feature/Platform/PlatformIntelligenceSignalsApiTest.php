<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md — ذكاء / منصة
 */
#[Group('phase6')]
final class PlatformIntelligenceSignalsApiTest extends TestCase
{
    public function test_signals_endpoint_requires_intelligence_read_permission(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['no-intel@platform.test', 'no-signals@platform.test']);
        $user = $this->createStandalonePlatformOperator('no-intel@platform.test', [
            'platform_role' => 'auditor',
        ]);
        /** @var \App\Models\User $fresh */
        $fresh = $user->fresh();
        $this->actingAsUser($fresh)
            ->getJson('/api/v1/platform/intelligence/signals')
            ->assertSuccessful()
            ->assertJsonStructure(['data', 'trace_id']);

        $blocked = $this->createStandalonePlatformOperator('no-signals@platform.test', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);
        $this->actingAsUser($blocked->fresh())
            ->getJson('/api/v1/platform/intelligence/signals')
            ->assertForbidden();
    }

    public function test_each_signal_matches_platform_signal_contract_shape(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['sig-api@platform.test']);
        $this->createStandalonePlatformOperator('sig-api@platform.test', [
            'platform_role' => 'platform_admin',
        ]);

        $res = $this->actingAsUser(\App\Models\User::where('email', 'sig-api@platform.test')->firstOrFail())
            ->getJson('/api/v1/platform/intelligence/signals');

        $res->assertSuccessful()
            ->assertJsonPath('meta.scoring_rules_version', '1.0.0')
            ->assertJsonPath('meta.signal_order', 'severity_desc,confidence_desc,last_seen_desc,signal_key_asc_tiebreak');
        $rows = $res->json('data');
        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertIsArray($row);
            foreach ([
                'signal_key', 'signal_type', 'title', 'summary', 'why_summary', 'severity', 'confidence',
                'source', 'affected_scope', 'affected_entities', 'affected_companies', 'first_seen_at', 'last_seen_at',
                'recommended_next_step', 'correlation_keys',
            ] as $k) {
                $this->assertArrayHasKey($k, $row, 'missing '.$k);
            }
        }
    }
}
