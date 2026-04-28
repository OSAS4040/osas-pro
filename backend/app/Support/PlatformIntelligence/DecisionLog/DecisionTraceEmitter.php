<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\DecisionLog;

use App\Models\User;
use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEvent;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use DateTimeImmutable;

/**
 * Emits intelligence-domain trace for decision recording — distinct from incident lifecycle rows.
 */
final class DecisionTraceEmitter
{
    public function __construct(
        private readonly ?PlatformIntelligenceTraceRecorderInterface $trace = null,
    ) {}

    public function emitDecisionRecorded(
        User $actor,
        string $incidentKey,
        string $decisionId,
        PlatformDecisionType $decisionType,
    ): void {
        $recorder = $this->trace ?? new NullPlatformIntelligenceTraceRecorder;

        $recorder->record(new PlatformIntelligenceTraceEvent(
            event_type: PlatformIntelligenceTraceEventType::DecisionRecorded,
            actor: 'user:'.$actor->id,
            timestamp: new DateTimeImmutable('now'),
            source: 'platform_decision_log',
            reason: 'decision_logged',
            correlation_id: null,
            trace_id: app()->bound('trace_id') ? (is_string(app('trace_id')) ? app('trace_id') : null) : null,
            linked_entity_key: $incidentKey,
            context: [
                'decision_id' => $decisionId,
                'decision_type' => $decisionType->value,
            ],
        ));
    }
}
