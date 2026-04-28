<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\IncidentLifecycle;

use App\Models\PlatformIncidentLifecycleEvent;
use App\Models\User;
use App\Support\PlatformIntelligence\Trace\NullPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEvent;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use DateTimeImmutable;

/**
 * Persisted operational timeline + optional in-request intelligence trace fan-out.
 */
final class IncidentOperationalTraceWriter
{
    public function __construct(
        private readonly ?PlatformIntelligenceTraceRecorderInterface $trace = null,
    ) {
    }

    public function recorder(): PlatformIntelligenceTraceRecorderInterface
    {
        return $this->trace ?? new NullPlatformIntelligenceTraceRecorder;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $incidentKey,
        User $actor,
        string $eventType,
        ?string $priorStatus,
        ?string $nextStatus,
        ?string $priorEscalation,
        ?string $nextEscalation,
        ?string $priorOwner,
        ?string $nextOwner,
        ?string $reason,
        array $context = [],
    ): void {
        PlatformIncidentLifecycleEvent::query()->create([
            'incident_key' => $incidentKey,
            'actor_user_id' => $actor->id,
            'event_type' => $eventType,
            'prior_status' => $priorStatus,
            'next_status' => $nextStatus,
            'prior_escalation_state' => $priorEscalation,
            'next_escalation_state' => $nextEscalation,
            'prior_owner' => $priorOwner,
            'next_owner' => $nextOwner,
            'reason' => $reason,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);

        $enumType = $this->mapToEnum($eventType);
        if ($enumType !== null) {
            $this->recorder()->record(new PlatformIntelligenceTraceEvent(
                event_type: $enumType,
                actor: 'user:'.$actor->id,
                timestamp: new DateTimeImmutable('now'),
                source: 'platform_incident_center',
                reason: $eventType,
                correlation_id: null,
                trace_id: app()->bound('trace_id') ? (is_string(app('trace_id')) ? app('trace_id') : null) : null,
                linked_entity_key: $incidentKey,
                context: array_merge([
                    'prior_status' => $priorStatus,
                    'next_status' => $nextStatus,
                    'prior_escalation_state' => $priorEscalation,
                    'next_escalation_state' => $nextEscalation,
                    'reason' => $reason,
                ], $context),
            ));
        }
    }

    private function mapToEnum(string $eventType): ?PlatformIntelligenceTraceEventType
    {
        return match ($eventType) {
            'incident_materialized' => PlatformIntelligenceTraceEventType::IncidentMaterialized,
            'incident_acknowledged' => PlatformIntelligenceTraceEventType::IncidentAcknowledged,
            'incident_owner_assigned' => PlatformIntelligenceTraceEventType::IncidentOwnerAssigned,
            'incident_reassigned' => PlatformIntelligenceTraceEventType::IncidentOwnerReassigned,
            'incident_escalated' => PlatformIntelligenceTraceEventType::IncidentEscalated,
            'incident_moved_to_monitoring' => PlatformIntelligenceTraceEventType::IncidentMovedToMonitoring,
            'incident_moved_to_under_review' => PlatformIntelligenceTraceEventType::IncidentMovedToUnderReview,
            'incident_resolved' => PlatformIntelligenceTraceEventType::IncidentResolved,
            'incident_closed' => PlatformIntelligenceTraceEventType::IncidentClosed,
            'incident_note_appended' => PlatformIntelligenceTraceEventType::IncidentNoteAppended,
            default => null,
        };
    }
}
