<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence;

use App\Support\PlatformIntelligence\Contracts\PlatformDecisionLogEntryContract;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PlatformIntelligenceContractsShapeTest extends TestCase
{
    public function test_contract_constructors_expose_required_properties(): void
    {
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');

        $signal = new PlatformSignalContract(
            signal_key: 'sig_1',
            signal_type: PlatformSignalType::Rule,
            title: 't',
            summary: 's',
            why_summary: 'w',
            severity: PlatformIntelligenceSeverity::Low,
            confidence: 0.5,
            source: PlatformSignalSourceType::Operations,
            source_ref: null,
            affected_scope: 'global',
            affected_entities: [],
            affected_companies: [],
            first_seen_at: $now,
            last_seen_at: $now,
            recommended_next_step: 'review',
            correlation_keys: [],
            trace_id: null,
            correlation_id: null,
        );
        $this->assertRequiredProperties(PlatformSignalContract::class, $signal, [
            'signal_key', 'signal_type', 'title', 'summary', 'why_summary', 'severity', 'confidence',
            'source', 'source_ref', 'affected_scope', 'affected_entities', 'affected_companies',
            'first_seen_at', 'last_seen_at', 'recommended_next_step', 'correlation_keys', 'trace_id', 'correlation_id',
        ]);

        $candidate = new PlatformIncidentCandidateContract(
            incident_key: 'cand_1',
            incident_type: 'latency',
            title: 't',
            summary: 's',
            why_summary: 'why',
            severity: PlatformIntelligenceSeverity::Medium,
            confidence: 0.7,
            source_signals: ['sig_1'],
            affected_scope: 'tenant:1',
            affected_entities: [],
            affected_companies: [1],
            first_seen_at: $now,
            last_seen_at: $now,
            recommended_actions: ['triage'],
            grouping_reason: 'corr',
            dedupe_fingerprint: 'fp',
        );
        $this->assertRequiredProperties(PlatformIncidentCandidateContract::class, $candidate, [
            'incident_key', 'incident_type', 'title', 'summary', 'why_summary', 'severity', 'confidence', 'source_signals',
            'affected_scope', 'affected_entities', 'affected_companies', 'first_seen_at', 'last_seen_at',
            'recommended_actions', 'grouping_reason', 'dedupe_fingerprint',
        ]);

        $incident = new PlatformIncidentContract(
            incident_key: 'inc_1',
            incident_type: 'latency',
            title: 't',
            summary: 's',
            why_summary: 'because',
            severity: PlatformIntelligenceSeverity::High,
            confidence: 0.9,
            status: PlatformIncidentStatus::Open,
            owner: null,
            ownership_state: PlatformIncidentOwnershipState::Unassigned,
            escalation_state: PlatformIncidentEscalationState::None,
            affected_scope: 'tenant:1',
            affected_entities: [],
            affected_companies: [1],
            source_signals: ['sig_1'],
            recommended_actions: [],
            first_seen_at: $now,
            last_seen_at: $now,
            acknowledged_at: null,
            resolved_at: null,
            closed_at: null,
            last_status_change_at: null,
            resolve_reason: null,
            close_reason: null,
        );
        $this->assertRequiredProperties(PlatformIncidentContract::class, $incident, [
            'incident_key', 'incident_type', 'title', 'summary', 'why_summary', 'severity', 'confidence', 'status', 'owner',
            'ownership_state', 'escalation_state', 'affected_scope', 'affected_entities', 'affected_companies',
            'source_signals', 'recommended_actions', 'first_seen_at', 'last_seen_at', 'acknowledged_at',
            'resolved_at', 'closed_at', 'last_status_change_at', 'resolve_reason', 'close_reason',
        ]);

        $decision = new PlatformDecisionLogEntryContract(
            decision_id: 'dec_1',
            incident_key: 'inc_1',
            decision_type: PlatformDecisionType::Observation,
            decision_summary: 'seen',
            rationale: 'because',
            actor: 'user:1',
            created_at: $now,
            linked_signals: ['sig_1'],
            linked_notes: [],
            expected_outcome: 'watch',
            evidence_refs: [],
            follow_up_required: false,
        );
        $this->assertRequiredProperties(PlatformDecisionLogEntryContract::class, $decision, [
            'decision_id', 'incident_key', 'decision_type', 'decision_summary', 'rationale', 'actor', 'created_at',
            'linked_signals', 'linked_notes', 'expected_outcome', 'evidence_refs', 'follow_up_required',
        ]);
    }

    /**
     * @param  list<string>  $names
     */
    private function assertRequiredProperties(string $class, object $instance, array $names): void
    {
        $ref = new ReflectionClass($class);
        foreach ($names as $n) {
            $this->assertTrue($ref->hasProperty($n), sprintf('Missing property %s::%s', $class, $n));
            $prop = $ref->getProperty($n);
            $prop->setAccessible(true);
            $this->assertTrue($prop->isInitialized($instance), sprintf('Uninitialized %s::%s', $class, $n));
        }
    }
}
