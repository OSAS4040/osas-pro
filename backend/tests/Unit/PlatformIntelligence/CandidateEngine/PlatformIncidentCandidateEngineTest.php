<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\CandidateEngine;

use App\Support\PlatformIntelligence\CandidateEngine\PlatformIncidentCandidateEngine;
use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use App\Support\PlatformIntelligence\Trace\InMemoryPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlatformIncidentCandidateEngineTest extends TestCase
{
    private DateTimeImmutable $ts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ts = new DateTimeImmutable('2026-04-01T10:00:00Z');
    }

    public function test_eligibility_rejects_low_confidence(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $s = $this->makeSignal('s1', PlatformIntelligenceSeverity::Low, 0.37, ['ck'], [1]);
        $out = $engine->buildFromSignals([$s]);
        $this->assertSame([], $out);
    }

    public function test_eligibility_rejects_info_without_correlation_keys(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $s = $this->makeSignal('s1', PlatformIntelligenceSeverity::Info, 0.9, [], [1]);
        $out = $engine->buildFromSignals([$s]);
        $this->assertSame([], $out);
    }

    public function test_grouping_merges_shared_correlation_keys(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $a = $this->makeSignal('sig_a', PlatformIntelligenceSeverity::Low, 0.6, ['tenant:5'], [5], 'scope.tenant');
        $b = $this->makeSignal('sig_b', PlatformIntelligenceSeverity::Medium, 0.62, ['tenant:5'], [6], 'scope.other');
        $out = $engine->buildFromSignals([$a, $b]);
        $this->assertCount(1, $out);
        $this->assertCount(2, $out[0]->source_signals);
        $keys = [...$out[0]->source_signals];
        sort($keys, SORT_STRING);
        $this->assertSame(['sig_a', 'sig_b'], $keys);
    }

    public function test_incident_key_stable_under_signal_reorder(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $a = $this->makeSignal('m_sig', PlatformIntelligenceSeverity::Low, 0.61, ['ck-z'], [9], 'z.scope');
        $b = $this->makeSignal('a_sig', PlatformIntelligenceSeverity::Low, 0.62, ['ck-z'], [8], 'a.scope');
        $k1 = $engine->buildFromSignals([$a, $b])[0]->incident_key;
        $k2 = $engine->buildFromSignals([$b, $a])[0]->incident_key;
        $this->assertSame($k1, $k2);
    }

    public function test_separate_clusters_when_no_merge_rules_hit(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $a = $this->makeSignal('s1', PlatformIntelligenceSeverity::Low, 0.7, ['a-only'], [1], 'scope-1', PlatformSignalType::Rule);
        $b = $this->makeSignal('s2', PlatformIntelligenceSeverity::Low, 0.7, ['b-only'], [2], 'scope-2', PlatformSignalType::Trend);
        $out = $engine->buildFromSignals([$a, $b]);
        $this->assertCount(2, $out);
    }

    public function test_severity_rollups_from_max_and_bump_bounded(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $signals = [];
        for ($i = 0; $i < 6; $i++) {
            $signals[] = $this->makeSignal('sig_'.$i, PlatformIntelligenceSeverity::Medium, 0.75, ['shared'], [$i + 1], 'same.scope', PlatformSignalType::Rule);
        }
        $out = $engine->buildFromSignals($signals);
        $this->assertCount(1, $out);
        $this->assertContains($out[0]->severity, [PlatformIntelligenceSeverity::Medium, PlatformIntelligenceSeverity::High], 'severity rollup must not jump everything to critical');
    }

    public function test_suppression_drops_info_singleton_without_companies(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $infoOnly = $this->makeSignal('info_e', PlatformIntelligenceSeverity::Info, 0.55, ['ck1'], [], 'global', PlatformSignalType::Rule, ['entity-a']);
        $solid = $this->makeSignal('solid', PlatformIntelligenceSeverity::Low, 0.8, ['ck2'], [99], 'tenant:99');
        $out = $engine->buildFromSignals([$infoOnly, $solid]);
        $this->assertCount(1, $out);
        $this->assertSame(['solid'], $out[0]->source_signals);
    }

    public function test_explainability_fields_are_substantive(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $s = $this->makeSignal('solo', PlatformIntelligenceSeverity::Low, 0.77, ['x'], [3], 'tenant:3');
        $out = $engine->buildFromSignals([$s]);
        $this->assertCount(1, $out);
        $this->assertNotSame('', trim($out[0]->grouping_reason));
        $this->assertStringContainsString('لماذا', $out[0]->why_summary);
        $this->assertStringContainsString('solo', $out[0]->summary);
    }

    public function test_deterministic_ordering_by_severity_then_key(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $low = $this->makeSignal('low_k', PlatformIntelligenceSeverity::Low, 0.9, ['l1'], [1], 's1');
        $high = $this->makeSignal('high_k', PlatformIntelligenceSeverity::High, 0.5, ['h1'], [2], 's2');
        $out = $engine->buildFromSignals([$low, $high]);
        $this->assertSame(PlatformIntelligenceSeverity::High, $out[0]->severity);
    }

    public function test_trace_emits_structural_candidate_events(): void
    {
        $engine = new PlatformIncidentCandidateEngine;
        $trace = new InMemoryPlatformIntelligenceTraceRecorder;
        $s = $this->makeSignal('t1', PlatformIntelligenceSeverity::Medium, 0.8, ['c'], [1], 'sc');
        $engine->buildFromSignals([$s], $trace);
        $types = array_map(static fn ($e) => $e->event_type, $trace->all());
        $this->assertContains(PlatformIntelligenceTraceEventType::CandidateDerived, $types);
        $this->assertContains(PlatformIntelligenceTraceEventType::CandidateGrouped, $types);
        $this->assertContains(PlatformIntelligenceTraceEventType::CandidateScored, $types);
        $this->assertContains(PlatformIntelligenceTraceEventType::CandidateSuppressed, $types);
        $this->assertContains(PlatformIntelligenceTraceEventType::CandidateExplained, $types);
    }

    /**
     * @param  list<string>  $correlation
     * @param  list<int>  $companies
     * @param  list<string>  $entities
     */
    private function makeSignal(
        string $key,
        PlatformIntelligenceSeverity $sev,
        float $conf,
        array $correlation,
        array $companies,
        string $scope = 'tenant:1',
        PlatformSignalType $type = PlatformSignalType::Rule,
        array $entities = [],
    ): PlatformSignalContract {
        return new PlatformSignalContract(
            signal_key: $key,
            signal_type: $type,
            title: 'عنوان '.$key,
            summary: 'ملخص '.$key,
            why_summary: 'لماذا '.$key,
            severity: $sev,
            confidence: $conf,
            source: PlatformSignalSourceType::Operations,
            source_ref: null,
            affected_scope: $scope,
            affected_entities: $entities,
            affected_companies: $companies,
            first_seen_at: $this->ts,
            last_seen_at: $this->ts,
            recommended_next_step: 'مراقبة',
            correlation_keys: $correlation,
            trace_id: null,
            correlation_id: 'cid-test',
        );
    }
}
