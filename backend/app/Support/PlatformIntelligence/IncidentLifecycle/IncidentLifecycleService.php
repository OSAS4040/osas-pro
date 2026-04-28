<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\IncidentLifecycle;

use App\Models\PlatformIncident;
use App\Models\User;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use Illuminate\Support\Facades\DB;

final class IncidentLifecycleService
{
    public function __construct(
        private readonly IncidentRepository $repository = new IncidentRepository,
        private readonly IncidentLifecyclePolicy $policy = new IncidentLifecyclePolicy,
        private readonly IncidentOperationalTraceWriter $traceWriter = new IncidentOperationalTraceWriter,
    ) {}

    public function acknowledge(string $incidentKey, User $actor): void
    {
        DB::transaction(function () use ($incidentKey, $actor): void {
            $row = $this->lockRow($incidentKey);
            $this->policy->assertAcknowledge($row->toContract()->status);
            $prior = $row->status;
            $row->status = PlatformIncidentStatus::Acknowledged->value;
            $row->acknowledged_at = now();
            $row->last_status_change_at = now();
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                'incident_acknowledged',
                $prior,
                $row->status,
                $row->escalation_state,
                $row->escalation_state,
                $row->owner,
                $row->owner,
                null,
            );
        });
    }

    public function moveUnderReview(string $incidentKey, User $actor): void
    {
        DB::transaction(function () use ($incidentKey, $actor): void {
            $row = $this->lockRow($incidentKey);
            $this->policy->assertMoveUnderReview($row->toContract()->status);
            $prior = $row->status;
            $row->status = PlatformIncidentStatus::UnderReview->value;
            $row->last_status_change_at = now();
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                'incident_moved_to_under_review',
                $prior,
                $row->status,
                $row->escalation_state,
                $row->escalation_state,
                $row->owner,
                $row->owner,
                null,
            );
        });
    }

    public function escalate(string $incidentKey, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($incidentKey, $actor, $reason): void {
            $row = $this->lockRow($incidentKey);
            $this->policy->assertEscalate($row->toContract()->status);
            $prior = $row->status;
            $priorEsc = $row->escalation_state;
            $row->status = PlatformIncidentStatus::Escalated->value;
            $row->escalation_state = $this->policy->escalationForEscalatedStatus()->value;
            $row->last_status_change_at = now();
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                'incident_escalated',
                $prior,
                $row->status,
                $priorEsc,
                $row->escalation_state,
                $row->owner,
                $row->owner,
                $reason,
            );
        });
    }

    public function moveMonitoring(string $incidentKey, User $actor): void
    {
        DB::transaction(function () use ($incidentKey, $actor): void {
            $row = $this->lockRow($incidentKey);
            $contract = $row->toContract();
            $this->policy->assertMoveMonitoring($contract->status);
            $prior = $row->status;
            $priorEsc = $row->escalation_state;
            $nextEsc = $contract->status === PlatformIncidentStatus::Escalated
                ? $this->policy->escalationWhenEnteringMonitoringFromEscalated()
                : $this->policy->escalationWhenEnteringMonitoringFromUnderReview($contract->escalation_state);
            $row->status = PlatformIncidentStatus::Monitoring->value;
            $row->escalation_state = $nextEsc->value;
            $row->last_status_change_at = now();
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                'incident_moved_to_monitoring',
                $prior,
                $row->status,
                $priorEsc,
                $row->escalation_state,
                $row->owner,
                $row->owner,
                null,
            );
        });
    }

    public function resolve(string $incidentKey, User $actor, string $reason): void
    {
        $reason = trim($reason);
        if (strlen($reason) < 3) {
            throw new IncidentLifecycleException('resolve_reason_required');
        }
        DB::transaction(function () use ($incidentKey, $actor, $reason): void {
            $row = $this->lockRow($incidentKey);
            $this->policy->assertResolve($row->toContract()->status);
            $prior = $row->status;
            $row->status = PlatformIncidentStatus::Resolved->value;
            $row->resolved_at = now();
            $row->resolve_reason = $reason;
            $row->last_status_change_at = now();
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                'incident_resolved',
                $prior,
                $row->status,
                $row->escalation_state,
                $row->escalation_state,
                $row->owner,
                $row->owner,
                $reason,
            );
        });
    }

    public function close(string $incidentKey, User $actor, string $reason): void
    {
        $reason = trim($reason);
        if (strlen($reason) < 3) {
            throw new IncidentLifecycleException('close_reason_required');
        }
        DB::transaction(function () use ($incidentKey, $actor, $reason): void {
            $row = $this->lockRow($incidentKey);
            $this->policy->assertClose($row->toContract()->status);
            $prior = $row->status;
            $row->status = PlatformIncidentStatus::Closed->value;
            $row->closed_at = now();
            $row->close_reason = $reason;
            $row->last_status_change_at = now();
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                'incident_closed',
                $prior,
                $row->status,
                $row->escalation_state,
                $row->escalation_state,
                $row->owner,
                $row->owner,
                $reason,
            );
        });
    }

    public function assignOwner(string $incidentKey, User $actor, string $ownerRef): void
    {
        $ownerRef = trim($ownerRef);
        if ($ownerRef === '') {
            throw new IncidentLifecycleException('owner_ref_required');
        }
        DB::transaction(function () use ($incidentKey, $actor, $ownerRef): void {
            $row = $this->lockRow($incidentKey);
            $this->policy->assertOwnerAssignable($row->toContract()->status);
            $priorOwner = $row->owner;
            $wasUnassigned = $row->owner === null || $row->ownership_state === PlatformIncidentOwnershipState::Unassigned->value;
            $row->owner = $ownerRef;
            $row->ownership_state = $wasUnassigned
                ? PlatformIncidentOwnershipState::Assigned->value
                : PlatformIncidentOwnershipState::Reassigned->value;
            $row->last_status_change_at = now();
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                $wasUnassigned ? 'incident_owner_assigned' : 'incident_reassigned',
                $row->status,
                $row->status,
                $row->escalation_state,
                $row->escalation_state,
                $priorOwner,
                $row->owner,
                null,
            );
        });
    }

    public function appendNote(string $incidentKey, User $actor, string $text): void
    {
        $text = trim($text);
        if ($text === '' || strlen($text) > 2000) {
            throw new IncidentLifecycleException('note_invalid');
        }
        DB::transaction(function () use ($incidentKey, $actor, $text): void {
            $row = $this->lockRow($incidentKey);
            if ($row->status === PlatformIncidentStatus::Closed->value) {
                throw new IncidentLifecycleException('note_on_closed_incident');
            }
            $notes = is_array($row->operator_notes) ? $row->operator_notes : [];
            $notes[] = [
                'at' => now()->toIso8601String(),
                'actor_user_id' => $actor->id,
                'text' => $text,
            ];
            $row->operator_notes = $notes;
            $this->repository->saveModel($row);
            $this->traceWriter->record(
                $incidentKey,
                $actor,
                'incident_note_appended',
                $row->status,
                $row->status,
                $row->escalation_state,
                $row->escalation_state,
                $row->owner,
                $row->owner,
                null,
                ['note_length' => strlen($text)],
            );
        });
    }

    private function lockRow(string $incidentKey): PlatformIncident
    {
        $row = PlatformIncident::query()->where('incident_key', $incidentKey)->lockForUpdate()->first();
        if ($row === null) {
            throw new IncidentLifecycleException('incident_not_found');
        }

        return $row;
    }
}
