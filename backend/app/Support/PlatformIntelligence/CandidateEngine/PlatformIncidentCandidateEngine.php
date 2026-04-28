<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateEngine;

use App\Support\PlatformIntelligence\CandidateCorrelation\CandidateGroupingService;
use App\Support\PlatformIntelligence\CandidateCorrelation\ClusterCorrelationAnalyzer;
use App\Support\PlatformIntelligence\CandidateExplainability\IncidentCandidateExplainabilityComposer;
use App\Support\PlatformIntelligence\CandidateScoring\CandidateConfidenceRollup;
use App\Support\PlatformIntelligence\CandidateScoring\CandidateSeverityRollup;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEvent;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Official candidate pipeline: input {@see PlatformSignalContract} only — output {@see PlatformIncidentCandidateContract} only.
 */
final class PlatformIncidentCandidateEngine
{
    public function __construct(
        private readonly CandidateEligibilityEvaluator $eligibility = new CandidateEligibilityEvaluator,
        private readonly CandidateGroupingService $grouping = new CandidateGroupingService,
        private readonly ClusterCorrelationAnalyzer $correlation = new ClusterCorrelationAnalyzer,
        private readonly CandidateIdentityBuilder $identity = new CandidateIdentityBuilder,
        private readonly CandidateSeverityRollup $severityRollup = new CandidateSeverityRollup,
        private readonly CandidateConfidenceRollup $confidenceRollup = new CandidateConfidenceRollup,
        private readonly IncidentCandidateExplainabilityComposer $explain = new IncidentCandidateExplainabilityComposer,
        private readonly IncidentCandidateRecommendedActionsPolicy $actions = new IncidentCandidateRecommendedActionsPolicy,
        private readonly CandidateSuppressionService $suppression = new CandidateSuppressionService,
    ) {}

    /**
     * @param  list<PlatformSignalContract>  $signals
     * @return list<PlatformIncidentCandidateContract>
     */
    public function buildFromSignals(array $signals, ?PlatformIntelligenceTraceRecorderInterface $trace = null): array
    {
        $trace ??= new NullPlatformIntelligenceTraceRecorder();
        $correlationId = (string) Str::uuid();
        $traceId = null;
        if (app()->bound('trace_id')) {
            $tv = app('trace_id');
            $traceId = is_string($tv) ? $tv : null;
        }

        $eligible = [];
        $rejected = 0;
        foreach ($signals as $s) {
            if (! $s instanceof PlatformSignalContract) {
                continue;
            }
            if ($this->eligibility->isEligible($s)) {
                $eligible[] = $s;
            } else {
                $rejected++;
            }
        }

        $this->record($trace, PlatformIntelligenceTraceEventType::CandidateDerived, $correlationId, $traceId, [
            'eligible' => count($eligible),
            'rejected' => $rejected,
        ]);

        $clusters = $this->grouping->partition($eligible);
        $this->record($trace, PlatformIntelligenceTraceEventType::CandidateGrouped, $correlationId, $traceId, [
            'clusters' => count($clusters),
        ]);

        $raw = [];
        foreach ($clusters as $cluster) {
            $raw[] = $this->materializeCluster($cluster);
        }

        $this->record($trace, PlatformIntelligenceTraceEventType::CandidateScored, $correlationId, $traceId, [
            'before_suppression' => count($raw),
        ]);

        $after = $this->suppression->suppress($raw);
        $this->record($trace, PlatformIntelligenceTraceEventType::CandidateSuppressed, $correlationId, $traceId, [
            'removed' => count($raw) - count($after),
            'after' => count($after),
        ]);

        $ordered = CandidateResponseOrdering::sortStable($after);
        $this->record($trace, PlatformIntelligenceTraceEventType::CandidateExplained, $correlationId, $traceId, [
            'final_count' => count($ordered),
        ]);

        return $ordered;
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function materializeCluster(array $cluster): PlatformIncidentCandidateContract
    {
        $sharedCk = $this->correlation->sharedCorrelationKeyCount($cluster) > 0;
        $severity = $this->severityRollup->rollup($cluster);
        $sharedCount = $this->correlation->sharedCorrelationKeyCount($cluster);
        $confidence = $this->confidenceRollup->rollup($cluster, $sharedCount);
        $incidentKey = $this->identity->incidentKey($cluster);
        $incidentType = $this->identity->incidentType($cluster, $sharedCk);
        $fingerprint = $this->identity->dedupeFingerprint($severity, $cluster);

        $sevR = $this->severityRollup->rationaleAr($severity, $cluster);
        $confR = $this->confidenceRollup->rationaleAr($cluster, $confidence, $sharedCount);

        $expl = $this->explain->compose($cluster, $severity, $confidence, $sharedCk, $sevR, $confR);

        $firstSeen = $this->minTime($cluster, static fn (PlatformSignalContract $s) => $s->first_seen_at);
        $lastSeen = $this->maxTime($cluster, static fn (PlatformSignalContract $s) => $s->last_seen_at);

        $entities = $this->mergeSortedUniqueStrings($cluster, static fn (PlatformSignalContract $s) => $s->affected_entities);
        $companies = $this->mergeSortedUniqueCompanies($cluster);

        $sourceKeys = $this->identity->sortedSignalKeys($cluster);

        $scope = $this->primaryScope($cluster);

        $guidance = $this->actions->forCandidate(
            $severity,
            $confidence,
            count($cluster),
            count($companies),
        );

        return new PlatformIncidentCandidateContract(
            incident_key: $incidentKey,
            incident_type: $incidentType,
            title: $expl['title'],
            summary: $expl['summary'],
            why_summary: $expl['why_summary'],
            severity: $severity,
            confidence: $confidence,
            source_signals: $sourceKeys,
            affected_scope: $scope,
            affected_entities: $entities,
            affected_companies: $companies,
            first_seen_at: $firstSeen,
            last_seen_at: $lastSeen,
            recommended_actions: $guidance,
            grouping_reason: $expl['grouping_reason'],
            dedupe_fingerprint: $fingerprint,
        );
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function primaryScope(array $cluster): string
    {
        $scopes = [];
        foreach ($cluster as $s) {
            $scopes[$s->affected_scope] = true;
        }
        $list = array_keys($scopes);
        sort($list, SORT_STRING);

        return $list[0] ?? 'unknown_scope';
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     * @param  callable(PlatformSignalContract): DateTimeImmutable  $pick
     */
    private function minTime(array $cluster, callable $pick): DateTimeImmutable
    {
        $best = null;
        foreach ($cluster as $s) {
            $t = $pick($s);
            if ($best === null || $t < $best) {
                $best = $t;
            }
        }

        return $best ?? new DateTimeImmutable('@0');
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     * @param  callable(PlatformSignalContract): DateTimeImmutable  $pick
     */
    private function maxTime(array $cluster, callable $pick): DateTimeImmutable
    {
        $best = null;
        foreach ($cluster as $s) {
            $t = $pick($s);
            if ($best === null || $t > $best) {
                $best = $t;
            }
        }

        return $best ?? new DateTimeImmutable('@0');
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     * @param  callable(PlatformSignalContract): list<string>  $extract
     * @return list<string>
     */
    private function mergeSortedUniqueStrings(array $cluster, callable $extract): array
    {
        $set = [];
        foreach ($cluster as $s) {
            foreach ($extract($s) as $e) {
                $t = trim((string) $e);
                if ($t !== '') {
                    $set[$t] = true;
                }
            }
        }
        $keys = array_keys($set);
        sort($keys, SORT_STRING);

        return array_values($keys);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     * @return list<int|string>
     */
    private function mergeSortedUniqueCompanies(array $cluster): array
    {
        $map = [];
        foreach ($cluster as $s) {
            foreach ($s->affected_companies as $id) {
                $map[(string) $id] = $id;
            }
        }
        $keys = array_keys($map);
        sort($keys, SORT_STRING);

        $out = [];
        foreach ($keys as $k) {
            $out[] = $map[$k];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function record(
        PlatformIntelligenceTraceRecorderInterface $trace,
        PlatformIntelligenceTraceEventType $type,
        string $correlationId,
        ?string $traceId,
        array $context,
    ): void {
        $trace->record(new PlatformIntelligenceTraceEvent(
            event_type: $type,
            actor: 'pipeline',
            timestamp: new DateTimeImmutable('now'),
            source: 'platform_incident_candidate_engine',
            reason: 'candidate_pipeline',
            correlation_id: $correlationId,
            trace_id: $traceId,
            linked_entity_key: 'engine:candidate_pipeline',
            context: $context,
        ));
    }
}
