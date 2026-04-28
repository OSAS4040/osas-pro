<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Correlation;

use App\Models\User;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEvent;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use DateTimeImmutable;

final class CorrelationTraceEmitter
{
    public function __construct(
        private readonly ?PlatformIntelligenceTraceRecorderInterface $trace = null,
    ) {}

    public function correlationBuilt(User $actor, string $incidentKey, array $summaryCounts): void
    {
        $this->emit(PlatformIntelligenceTraceEventType::CorrelationBuilt, $actor, $incidentKey, 'correlation_built', $summaryCounts);
    }

    public function commandSurfaceRendered(User $actor, array $meta): void
    {
        $this->emit(PlatformIntelligenceTraceEventType::CommandSurfaceRendered, $actor, 'command_surface', 'command_surface_rendered', $meta);
    }

    public function incidentContextLinked(User $actor, string $incidentKey, int $linkCount): void
    {
        $this->emit(PlatformIntelligenceTraceEventType::IncidentContextLinked, $actor, $incidentKey, 'incident_context_linked', ['link_count' => $linkCount]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function emit(
        PlatformIntelligenceTraceEventType $type,
        User $actor,
        string $linkedEntityKey,
        string $reason,
        array $context,
    ): void {
        $recorder = $this->trace ?? new NullPlatformIntelligenceTraceRecorder;
        $recorder->record(new PlatformIntelligenceTraceEvent(
            event_type: $type,
            actor: 'user:'.$actor->id,
            timestamp: new DateTimeImmutable('now'),
            source: 'platform_correlation_command',
            reason: $reason,
            correlation_id: null,
            trace_id: app()->bound('trace_id') ? (is_string(app('trace_id')) ? app('trace_id') : null) : null,
            linked_entity_key: $linkedEntityKey,
            context: $context,
        ));
    }
}
