<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\IncidentCenter;

use App\Models\PlatformIncident;
use App\Models\User;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\IncidentLifecycle\IncidentOperationalTraceWriter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Sole entry for turning an official candidate contract into a persisted {@see PlatformIncidentContract}.
 */
final class IncidentMaterializationService
{
    public function __construct(
        private readonly IncidentRepository $repository = new IncidentRepository,
        private readonly IncidentOperationalTraceWriter $traceWriter = new IncidentOperationalTraceWriter,
    ) {}

    /**
     * @throws IncidentMaterializationConflictException when incident_key already exists
     */
    public function materialize(PlatformIncidentCandidateContract $candidate, User $actor): PlatformIncidentContract
    {
        if ($this->repository->findByIncidentKey($candidate->incident_key) !== null) {
            throw new IncidentMaterializationConflictException('incident_already_materialized');
        }

        $now = Carbon::now();

        $row = new PlatformIncident([
            'incident_key' => $candidate->incident_key,
            'incident_type' => $candidate->incident_type,
            'title' => $candidate->title,
            'summary' => $candidate->summary,
            'why_summary' => $candidate->why_summary,
            'severity' => $candidate->severity->value,
            'confidence' => $candidate->confidence,
            'status' => PlatformIncidentStatus::Open->value,
            'owner' => null,
            'ownership_state' => PlatformIncidentOwnershipState::Unassigned->value,
            'escalation_state' => PlatformIncidentEscalationState::None->value,
            'affected_scope' => $candidate->affected_scope,
            'affected_entities' => array_values($candidate->affected_entities),
            'affected_companies' => array_values($candidate->affected_companies),
            'source_signals' => array_values($candidate->source_signals),
            'recommended_actions' => array_values($candidate->recommended_actions),
            'first_seen_at' => Carbon::instance($candidate->first_seen_at),
            'last_seen_at' => Carbon::instance($candidate->last_seen_at),
            'acknowledged_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'last_status_change_at' => $now,
            'resolve_reason' => null,
            'close_reason' => null,
            'operator_notes' => null,
        ]);

        try {
            $this->repository->createModel($row);
        } catch (QueryException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'unique') || (string) $e->getCode() === '23505') {
                throw new IncidentMaterializationConflictException('incident_already_materialized', previous: $e);
            }
            throw $e;
        }

        $this->traceWriter->record(
            incidentKey: $candidate->incident_key,
            actor: $actor,
            eventType: 'incident_materialized',
            priorStatus: null,
            nextStatus: PlatformIncidentStatus::Open->value,
            priorEscalation: null,
            nextEscalation: PlatformIncidentEscalationState::None->value,
            priorOwner: null,
            nextOwner: null,
            reason: null,
            context: ['source_signals_count' => count($candidate->source_signals)],
        );

        return $row->fresh()->toContract();
    }

    /**
     * Hydrate contract from DB without lifecycle side-effects.
     */
    public function contractForKey(string $incidentKey): ?PlatformIncidentContract
    {
        $m = $this->repository->findByIncidentKey($incidentKey);

        return $m?->toContract();
    }
}
