<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\ControlledActions;

use App\Models\User;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEvent;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use DateTimeImmutable;

/**
 * Analytical traces for controlled actions — not incident lifecycle rows.
 */
final class ControlledActionTraceEmitter
{
    private function recorder(): PlatformIntelligenceTraceRecorderInterface
    {
        if (app()->bound(PlatformIntelligenceTraceRecorderInterface::class)) {
            return app(PlatformIntelligenceTraceRecorderInterface::class);
        }

        return new NullPlatformIntelligenceTraceRecorder;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function emit(
        PlatformIntelligenceTraceEventType $type,
        User $actor,
        string $incidentKey,
        string $actionId,
        string $actionType,
        ?string $priorState,
        ?string $nextState,
        ?string $reason,
        array $context = [],
    ): void {
        $this->recorder()->record(new PlatformIntelligenceTraceEvent(
            event_type: $type,
            actor: 'user:'.$actor->id,
            timestamp: new DateTimeImmutable('now'),
            source: 'platform_controlled_actions',
            reason: $reason ?? $type->value,
            correlation_id: null,
            trace_id: app()->bound('trace_id') ? (is_string(app('trace_id')) ? app('trace_id') : null) : null,
            linked_entity_key: $incidentKey,
            context: array_merge([
                'action_id' => $actionId,
                'action_type' => $actionType,
                'prior_state' => $priorState,
                'next_state' => $nextState,
            ], $context),
        ));
    }
}
