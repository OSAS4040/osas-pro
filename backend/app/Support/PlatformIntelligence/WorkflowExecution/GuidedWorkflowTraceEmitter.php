<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\WorkflowExecution;

use App\Models\User;
use App\Support\PlatformIntelligence\GuidedWorkflows\GuidedWorkflowKey;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEvent;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use DateTimeImmutable;

final class GuidedWorkflowTraceEmitter
{
    public function __construct(
        private readonly ?PlatformIntelligenceTraceRecorderInterface $trace = null,
    ) {}

    public function started(User $actor, string $incidentKey, GuidedWorkflowKey $key, array $context = []): void
    {
        $this->emit(PlatformIntelligenceTraceEventType::WorkflowStarted, $actor, $incidentKey, $key, 'workflow_started', $context);
    }

    public function completed(User $actor, string $incidentKey, GuidedWorkflowKey $key, array $context = []): void
    {
        $this->emit(PlatformIntelligenceTraceEventType::WorkflowCompleted, $actor, $incidentKey, $key, 'workflow_completed', $context);
    }

    public function failed(User $actor, string $incidentKey, GuidedWorkflowKey $key, string $reason, array $context = []): void
    {
        $this->emit(PlatformIntelligenceTraceEventType::WorkflowFailed, $actor, $incidentKey, $key, $reason, $context);
    }

    private function emit(
        PlatformIntelligenceTraceEventType $type,
        User $actor,
        string $incidentKey,
        GuidedWorkflowKey $key,
        string $reason,
        array $context,
    ): void {
        $recorder = $this->trace ?? new NullPlatformIntelligenceTraceRecorder;
        $recorder->record(new PlatformIntelligenceTraceEvent(
            event_type: $type,
            actor: 'user:'.$actor->id,
            timestamp: new DateTimeImmutable('now'),
            source: 'platform_guided_workflows',
            reason: $reason,
            correlation_id: null,
            trace_id: app()->bound('trace_id') ? (is_string(app('trace_id')) ? app('trace_id') : null) : null,
            linked_entity_key: $incidentKey,
            context: array_merge([
                'workflow_key' => $key->value,
            ], $context),
        ));
    }
}
