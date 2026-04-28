<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Correlation\SignalCorrelationService;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Scoring\ConfidenceScorer;
use App\Support\PlatformIntelligence\Scoring\SeverityScorer;
use App\Support\PlatformIntelligence\SignalEngine\Collect\OverviewSnapshotCollector;
use App\Support\PlatformIntelligence\SignalEngine\Dedupe\SignalDedupeService;
use App\Support\PlatformIntelligence\SignalEngine\Detect\OverviewBasedSignalDetector;
use App\Support\PlatformIntelligence\SignalEngine\Draft\SignalDraft;
use App\Support\PlatformIntelligence\SignalEngine\Explainability\SignalExplainabilityComposer;
use App\Support\PlatformIntelligence\SignalEngine\Normalize\OverviewSnapshotNormalizer;
use App\Support\PlatformIntelligence\SignalEngine\Recommendation\RecommendedNextStepPolicy;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEvent;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Official signal pipeline: collect → normalize → detect → correlate → score → assemble → dedupe → explain.
 * Output type: {@see PlatformSignalContract} only.
 */
final class PlatformSignalEngine
{
    public function __construct(
        private readonly OverviewSnapshotCollector $collector,
        private readonly OverviewBasedSignalDetector $detector,
        private readonly SignalCorrelationService $correlation,
        private readonly SeverityScorer $severityScorer,
        private readonly ConfidenceScorer $confidenceScorer,
        private readonly SignalDedupeService $dedupe,
        private readonly SignalExplainabilityComposer $explainability,
        private readonly RecommendedNextStepPolicy $nextStepPolicy,
    ) {}

    /**
     * @return list<PlatformSignalContract>
     */
    public function build(?PlatformIntelligenceTraceRecorderInterface $trace = null): array
    {
        $trace ??= new NullPlatformIntelligenceTraceRecorder();
        $correlationId = (string) Str::uuid();
        $traceId = null;
        if (app()->bound('trace_id')) {
            $tv = app('trace_id');
            $traceId = is_string($tv) ? $tv : null;
        }

        $overview = $this->collector->collect();
        $norm = new OverviewSnapshotNormalizer($overview);
        $generatedAt = $norm->generatedAt();

        $drafts = $this->detector->detect($norm);
        $drafts = $this->correlation->apply($drafts);
        $this->trace($trace, PlatformIntelligenceTraceEventType::SignalDetected, 'pipeline', 'detect+correlate', $correlationId, $traceId, 'engine:pipeline', ['draft_count' => count($drafts)]);

        $contracts = [];
        foreach ($drafts as $draft) {
            $severity = $this->severityScorer->score($draft);
            $confidence = $this->confidenceScorer->score($draft, $norm);
            $contracts[] = $this->toContract($draft, $severity, $confidence, $generatedAt, $traceId, $correlationId);
        }

        $contracts = $this->dedupe->dedupe($contracts);
        $this->trace($trace, PlatformIntelligenceTraceEventType::SignalDeduped, 'pipeline', 'dedupe', $correlationId, $traceId, 'engine:pipeline', ['after_dedupe' => count($contracts)]);

        $explained = [];
        foreach ($contracts as $c) {
            $step = $this->nextStepPolicy->forSignalKey($c->signal_key);
            $withStep = new PlatformSignalContract(
                $c->signal_key,
                $c->signal_type,
                $c->title,
                $c->summary,
                $c->why_summary,
                $c->severity,
                $c->confidence,
                $c->source,
                $c->source_ref,
                $c->affected_scope,
                $c->affected_entities,
                $c->affected_companies,
                $c->first_seen_at,
                $c->last_seen_at,
                $step,
                $c->correlation_keys,
                $c->trace_id,
                $c->correlation_id,
            );
            $explained[] = $this->explainability->compose($withStep);
        }

        $this->trace($trace, PlatformIntelligenceTraceEventType::SignalExplained, 'pipeline', 'explain+recommend', $correlationId, $traceId, 'engine:pipeline', ['final_count' => count($explained)]);

        return SignalResponseOrdering::sortStable($explained);
    }

    private function toContract(
        SignalDraft $d,
        PlatformIntelligenceSeverity $severity,
        float $confidence,
        DateTimeImmutable $generatedAt,
        ?string $traceId,
        string $correlationId,
    ): PlatformSignalContract {
        $companies = array_values(array_map(static fn (int $id) => $id, $d->affected_company_ids));

        return new PlatformSignalContract(
            signal_key: $d->draft_key,
            signal_type: $d->signal_type,
            title: $d->title,
            summary: $d->summary_stub,
            why_summary: $d->why_stub,
            severity: $severity,
            confidence: $confidence,
            source: $d->source,
            source_ref: $d->source_ref,
            affected_scope: $d->affected_scope,
            affected_entities: $d->affected_entities,
            affected_companies: $companies,
            first_seen_at: $generatedAt,
            last_seen_at: $generatedAt,
            recommended_next_step: '',
            correlation_keys: $d->correlation_keys,
            trace_id: $traceId,
            correlation_id: $correlationId,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function trace(
        PlatformIntelligenceTraceRecorderInterface $trace,
        PlatformIntelligenceTraceEventType $type,
        string $actor,
        string $reason,
        string $correlationId,
        ?string $traceId,
        string $entityKey,
        array $context,
    ): void {
        $trace->record(new PlatformIntelligenceTraceEvent(
            event_type: $type,
            actor: $actor,
            timestamp: new DateTimeImmutable('now'),
            source: 'platform_signal_engine',
            reason: $reason,
            correlation_id: $correlationId,
            trace_id: $traceId,
            linked_entity_key: $entityKey,
            context: $context,
        ));
    }
}
