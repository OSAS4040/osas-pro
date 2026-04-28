<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\Correlation;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Correlation\CorrelationRuleEvaluator;
use App\Support\PlatformIntelligence\Enums\CorrelationRelationType;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CorrelationRuleEvaluatorTest extends TestCase
{
    private function baseIncident(): PlatformIncidentContract
    {
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);

        return new PlatformIncidentContract(
            incident_key: 'inc_1',
            incident_type: 't',
            title: 'T',
            summary: 'S',
            why_summary: 'W',
            severity: PlatformIntelligenceSeverity::Medium,
            confidence: 0.5,
            status: PlatformIncidentStatus::Open,
            owner: null,
            ownership_state: PlatformIncidentOwnershipState::Unassigned,
            escalation_state: PlatformIncidentEscalationState::None,
            affected_scope: 'x',
            affected_entities: [],
            affected_companies: [1, 2],
            source_signals: ['sig_a', 'sig_b'],
            recommended_actions: [],
            first_seen_at: $now,
            last_seen_at: $now,
            acknowledged_at: null,
            resolved_at: null,
            closed_at: null,
            last_status_change_at: $now,
            resolve_reason: null,
            close_reason: null,
        );
    }

    private function baseSignal(): PlatformSignalContract
    {
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);

        return new PlatformSignalContract(
            signal_key: 'sig_a',
            signal_type: PlatformSignalType::Rule,
            title: 'st',
            summary: 'ss',
            why_summary: 'sw',
            severity: PlatformIntelligenceSeverity::Low,
            confidence: 0.3,
            source: PlatformSignalSourceType::Operations,
            source_ref: null,
            affected_scope: 'x',
            affected_entities: [],
            affected_companies: [1],
            first_seen_at: $now,
            last_seen_at: $now,
            recommended_next_step: 'n',
            correlation_keys: ['sig_a'],
            trace_id: null,
            correlation_id: null,
        );
    }

    public function test_causal_when_signal_key_on_incident(): void
    {
        $link = CorrelationRuleEvaluator::signalToIncident($this->baseSignal(), $this->baseIncident());
        $this->assertNotNull($link);
        $this->assertSame(CorrelationRelationType::Causal->value, $link['relation_type']);
    }

    public function test_contextual_when_companies_overlap_and_keys_intersect(): void
    {
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);
        $sig = new PlatformSignalContract(
            signal_key: 'sig_other',
            signal_type: PlatformSignalType::Rule,
            title: 'st',
            summary: 'ss',
            why_summary: 'sw',
            severity: PlatformIntelligenceSeverity::Low,
            confidence: 0.3,
            source: PlatformSignalSourceType::Operations,
            source_ref: null,
            affected_scope: 'x',
            affected_entities: [],
            affected_companies: [2],
            first_seen_at: $now,
            last_seen_at: $now,
            recommended_next_step: 'n',
            correlation_keys: ['sig_b'],
            trace_id: null,
            correlation_id: null,
        );
        $link = CorrelationRuleEvaluator::signalToIncident($sig, $this->baseIncident());
        $this->assertNotNull($link);
        $this->assertSame(CorrelationRelationType::Contextual->value, $link['relation_type']);
    }

    public function test_derived_candidate_matches_incident_key(): void
    {
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);
        $cand = new PlatformIncidentCandidateContract(
            incident_key: 'inc_1',
            incident_type: 't',
            title: 'ct',
            summary: 'cs',
            why_summary: 'cw',
            severity: PlatformIntelligenceSeverity::Low,
            confidence: 0.2,
            source_signals: [],
            affected_scope: 'x',
            affected_entities: [],
            affected_companies: [],
            first_seen_at: $now,
            last_seen_at: $now,
            recommended_actions: [],
            grouping_reason: 'g',
            dedupe_fingerprint: 'f',
        );
        $link = CorrelationRuleEvaluator::candidateToIncident($cand, $this->baseIncident());
        $this->assertNotNull($link);
        $this->assertSame(CorrelationRelationType::Derived->value, $link['relation_type']);
    }
}
