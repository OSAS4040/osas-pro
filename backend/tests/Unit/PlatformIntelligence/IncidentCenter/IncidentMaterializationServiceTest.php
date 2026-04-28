<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\IncidentCenter;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentMaterializationConflictException;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentMaterializationService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IncidentMaterializationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_materializes_candidate_to_incident_contract_shape(): void
    {
        $user = $this->createStandalonePlatformOperator('mat-inc@platform.test', ['platform_role' => 'platform_admin']);
        $now = new DateTimeImmutable('2026-04-14T12:00:00Z');
        $candidate = new PlatformIncidentCandidateContract(
            incident_key: 'icand_test_materialize_1',
            incident_type: 'candidate.single_signal',
            title: 'T',
            summary: 'S',
            why_summary: 'W',
            severity: PlatformIntelligenceSeverity::Low,
            confidence: 0.7,
            source_signals: ['sig_a'],
            affected_scope: 'tenant:1',
            affected_entities: [],
            affected_companies: [1],
            first_seen_at: $now,
            last_seen_at: $now,
            recommended_actions: ['راقب'],
            grouping_reason: 'g',
            dedupe_fingerprint: 'fp',
        );

        $svc = new IncidentMaterializationService;
        $contract = $svc->materialize($candidate, $user);

        $this->assertSame('icand_test_materialize_1', $contract->incident_key);
        $this->assertSame('W', $contract->why_summary);
        $this->assertTrue($contract->status->value === 'open');

        $this->assertDatabaseHas('platform_incidents', ['incident_key' => 'icand_test_materialize_1']);
    }

    public function test_duplicate_materialize_conflict(): void
    {
        $user = $this->createStandalonePlatformOperator('mat-dup@platform.test', ['platform_role' => 'platform_admin']);
        $now = new DateTimeImmutable('2026-04-14T12:00:00Z');
        $candidate = new PlatformIncidentCandidateContract(
            incident_key: 'icand_dup',
            incident_type: 'candidate.single_signal',
            title: 'T',
            summary: 'S',
            why_summary: 'W',
            severity: PlatformIntelligenceSeverity::Low,
            confidence: 0.7,
            source_signals: ['sig_a'],
            affected_scope: 'tenant:1',
            affected_entities: [],
            affected_companies: [1],
            first_seen_at: $now,
            last_seen_at: $now,
            recommended_actions: [],
            grouping_reason: 'g',
            dedupe_fingerprint: 'fp',
        );

        $svc = new IncidentMaterializationService;
        $svc->materialize($candidate, $user);
        $this->expectException(IncidentMaterializationConflictException::class);
        $svc->materialize($candidate, $user);
    }
}
