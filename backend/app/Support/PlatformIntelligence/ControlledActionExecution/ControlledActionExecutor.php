<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\ControlledActionExecution;

use App\Models\PlatformControlledAction;
use App\Models\PlatformIncident;
use App\Models\User;
use App\Services\Platform\PlatformPermissionService;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionArtifactType;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionPermissionMatrix;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionStatus;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionTraceEmitter;
use App\Support\PlatformIntelligence\Contracts\PlatformControlledActionContract;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\ControlledActions\PlatformControlledActionContractSerializer;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Single execution entry for allowlisted controlled actions — no incident lifecycle writes.
 */
final class ControlledActionExecutor
{
    public function __construct(
        private readonly PlatformPermissionService $permissions,
        private readonly IncidentRepository $incidents,
        private readonly ControlledActionTraceEmitter $trace,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createFollowUp(User $actor, string $incidentKey, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::CREATE_FOLLOW_UP);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $incident = $this->requireIncident($incidentKey);
        $this->rejectIfIncidentClosed($incident->status, 'create_follow_up');

        $summary = $this->stringBetween($input, 'action_summary', 1, 512);
        $idempotencyKey = $this->optionalString($input, 'idempotency_key', 128);
        if ($idempotencyKey !== null) {
            $dup = PlatformControlledAction::query()
                ->where('incident_key', $incidentKey)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($dup !== null) {
                return $dup->toContract();
            }
        }

        $row = DB::transaction(function () use ($actor, $incidentKey, $summary, $idempotencyKey, $input): PlatformControlledAction {
            $m = new PlatformControlledAction;
            $m->action_id = (string) Str::uuid();
            $m->incident_key = $incidentKey;
            $m->action_type = ControlledActionArtifactType::FollowUp->value;
            $m->action_summary = $summary;
            $m->actor_user_id = $actor->id;
            $m->status = ControlledActionStatus::Open->value;
            $m->assigned_owner = null;
            $m->follow_up_required = true;
            $m->scheduled_for = null;
            $m->linked_decision_id = $this->optionalString($input, 'linked_decision_id', 128);
            $m->linked_notes = $this->optionalString($input, 'linked_notes', 8000);
            $m->external_reference = null;
            $m->idempotency_key = $idempotencyKey;
            $m->completion_reason = null;
            $m->canceled_reason = null;
            $m->save();

            return $m;
        });

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionCreated,
            $actor,
            $incidentKey,
            $row->action_id,
            $row->action_type,
            null,
            $row->status,
            'controlled_action_created',
        );

        return $row->toContract();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function requestHumanReview(User $actor, string $incidentKey, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::REQUEST_HUMAN_REVIEW);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $incident = $this->requireIncident($incidentKey);
        $this->rejectIfIncidentClosed($incident->status, 'request_human_review');

        if ($this->hasOpenHumanReview($incidentKey)) {
            throw ValidationException::withMessages([
                'incident_key' => ['open_human_review_already_exists'],
            ]);
        }

        $summary = $this->stringBetween($input, 'action_summary', 1, 512);
        $idempotencyKey = $this->optionalString($input, 'idempotency_key', 128);
        if ($idempotencyKey !== null) {
            $dup = PlatformControlledAction::query()
                ->where('incident_key', $incidentKey)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($dup !== null) {
                return $dup->toContract();
            }
        }

        $row = DB::transaction(function () use ($actor, $incidentKey, $summary, $idempotencyKey, $input): PlatformControlledAction {
            $m = new PlatformControlledAction;
            $m->action_id = (string) Str::uuid();
            $m->incident_key = $incidentKey;
            $m->action_type = ControlledActionArtifactType::HumanReviewRequest->value;
            $m->action_summary = $summary;
            $m->actor_user_id = $actor->id;
            $m->status = ControlledActionStatus::Open->value;
            $m->assigned_owner = null;
            $m->follow_up_required = true;
            $m->scheduled_for = null;
            $m->linked_decision_id = $this->optionalString($input, 'linked_decision_id', 128);
            $m->linked_notes = $this->optionalString($input, 'linked_notes', 8000);
            $m->external_reference = null;
            $m->idempotency_key = $idempotencyKey;
            $m->completion_reason = null;
            $m->canceled_reason = null;
            $m->save();

            return $m;
        });

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionCreated,
            $actor,
            $incidentKey,
            $row->action_id,
            $row->action_type,
            null,
            $row->status,
            'controlled_action_human_review_requested',
        );

        return $row->toContract();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function linkInternalTaskReference(User $actor, string $incidentKey, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::LINK_TASK_REFERENCE);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $incident = $this->requireIncident($incidentKey);
        $this->rejectIfIncidentClosed($incident->status, 'link_internal_task_reference');

        $ref = $this->stringBetween($input, 'external_reference', 1, 190);
        $this->assertSafeExternalReference($ref);
        $summary = $this->optionalString($input, 'action_summary', 512) ?? 'ربط مرجع مهمة داخلية';
        $idempotencyKey = $this->optionalString($input, 'idempotency_key', 128);
        if ($idempotencyKey !== null) {
            $dup = PlatformControlledAction::query()
                ->where('incident_key', $incidentKey)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($dup !== null) {
                return $dup->toContract();
            }
        }

        $row = DB::transaction(function () use ($actor, $incidentKey, $summary, $ref, $idempotencyKey, $input): PlatformControlledAction {
            $m = new PlatformControlledAction;
            $m->action_id = (string) Str::uuid();
            $m->incident_key = $incidentKey;
            $m->action_type = ControlledActionArtifactType::InternalTaskReference->value;
            $m->action_summary = $summary;
            $m->actor_user_id = $actor->id;
            $m->status = ControlledActionStatus::Open->value;
            $m->assigned_owner = null;
            $m->follow_up_required = false;
            $m->scheduled_for = null;
            $m->linked_decision_id = $this->optionalString($input, 'linked_decision_id', 128);
            $m->linked_notes = $this->optionalString($input, 'linked_notes', 8000);
            $m->external_reference = $ref;
            $m->idempotency_key = $idempotencyKey;
            $m->completion_reason = null;
            $m->canceled_reason = null;
            $m->save();

            return $m;
        });

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionCreated,
            $actor,
            $incidentKey,
            $row->action_id,
            $row->action_type,
            null,
            $row->status,
            'controlled_action_task_reference_linked',
            ['external_reference' => $ref],
        );

        return $row->toContract();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function assignFollowUpOwner(User $actor, string $actionId, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::ASSIGN_OWNER);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $row = $this->loadAction($actionId);
        $this->requireIncident($row->incident_key);

        $owner = $this->stringBetween($input, 'assigned_owner', 2, 190);
        $prior = $row->status;
        $this->assertAssignableState($row);

        $row->assigned_owner = $owner;
        if (in_array($row->status, [ControlledActionStatus::Open->value], true)) {
            $row->status = ControlledActionStatus::Assigned->value;
        }
        $row->save();

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionAssigned,
            $actor,
            $row->incident_key,
            $row->action_id,
            $row->action_type,
            $prior,
            $row->status,
            'controlled_action_assigned',
            ['assigned_owner' => $owner],
        );

        return $row->toContract();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function scheduleFollowUpWindow(User $actor, string $actionId, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::SCHEDULE);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $row = $this->loadAction($actionId);
        $this->requireIncident($row->incident_key);

        if ($row->action_type !== ControlledActionArtifactType::FollowUp->value) {
            throw ValidationException::withMessages(['action_id' => ['schedule_only_for_follow_up']]);
        }

        $when = $this->requiredDate($input, 'scheduled_for');
        $prior = $row->status;
        $this->assertSchedulableState($row);

        $row->scheduled_for = $when;
        $row->status = ControlledActionStatus::Scheduled->value;
        $row->save();

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionScheduled,
            $actor,
            $row->incident_key,
            $row->action_id,
            $row->action_type,
            $prior,
            $row->status,
            'controlled_action_scheduled',
            ['scheduled_for' => $when->toIso8601String()],
        );

        return $row->toContract();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function markFollowUpCompleted(User $actor, string $actionId, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::COMPLETE);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $row = $this->loadAction($actionId);
        $this->requireIncident($row->incident_key);

        if (! in_array($row->action_type, [
            ControlledActionArtifactType::FollowUp->value,
            ControlledActionArtifactType::HumanReviewRequest->value,
        ], true)) {
            throw ValidationException::withMessages(['action_id' => ['complete_only_follow_up_or_human_review']]);
        }

        $prior = $row->status;
        if (! in_array($prior, [
            ControlledActionStatus::Open->value,
            ControlledActionStatus::Assigned->value,
            ControlledActionStatus::Scheduled->value,
        ], true)) {
            throw ValidationException::withMessages(['status' => ['invalid_state_for_complete']]);
        }

        $reason = $this->stringBetween($input, 'completion_reason', 1, 4000);
        $row->status = ControlledActionStatus::Completed->value;
        $row->completion_reason = $reason;
        $row->canceled_reason = null;
        $row->save();

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionCompleted,
            $actor,
            $row->incident_key,
            $row->action_id,
            $row->action_type,
            $prior,
            $row->status,
            'controlled_action_completed',
        );

        return $row->toContract();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function cancelFollowUpWithReason(User $actor, string $actionId, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::CANCEL);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $row = $this->loadAction($actionId);
        $this->requireIncident($row->incident_key);

        $prior = $row->status;
        if ($prior === ControlledActionStatus::Completed->value || $prior === ControlledActionStatus::Canceled->value) {
            throw ValidationException::withMessages(['status' => ['invalid_state_for_cancel']]);
        }

        $reason = $this->stringBetween($input, 'canceled_reason', 1, 4000);
        $row->status = ControlledActionStatus::Canceled->value;
        $row->canceled_reason = $reason;
        $row->save();

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionCanceled,
            $actor,
            $row->incident_key,
            $row->action_id,
            $row->action_type,
            $prior,
            $row->status,
            'controlled_action_canceled',
        );

        return $row->toContract();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function reopenFollowUpIfNeeded(User $actor, string $actionId, array $input): PlatformControlledActionContract
    {
        $this->require($actor, ControlledActionPermissionMatrix::REOPEN);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $row = $this->loadAction($actionId);
        $incident = $this->requireIncident($row->incident_key);

        $prior = $row->status;
        if (! in_array($prior, [ControlledActionStatus::Completed->value, ControlledActionStatus::Canceled->value], true)) {
            throw ValidationException::withMessages(['status' => ['invalid_state_for_reopen']]);
        }

        if ($incident->status === PlatformIncidentStatus::Closed->value) {
            throw ValidationException::withMessages(['incident_key' => ['cannot_reopen_action_on_closed_incident']]);
        }

        $row->status = ControlledActionStatus::Open->value;
        $row->completion_reason = null;
        $row->canceled_reason = null;
        $row->save();

        $this->trace->emit(
            PlatformIntelligenceTraceEventType::ControlledActionReopened,
            $actor,
            $row->incident_key,
            $row->action_id,
            $row->action_type,
            $prior,
            $row->status,
            'controlled_action_reopened',
        );

        return $row->toContract();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForIncident(User $actor, string $incidentKey): array
    {
        $this->require($actor, ControlledActionPermissionMatrix::VIEW);
        $this->require($actor, ControlledActionPermissionMatrix::BASE_INCIDENT_READ);
        $this->requireIncident($incidentKey);

        return PlatformControlledAction::query()
            ->where('incident_key', $incidentKey)
            ->orderByDesc('created_at')
            ->orderBy('action_id')
            ->get()
            ->map(static fn (PlatformControlledAction $m) => PlatformControlledActionContractSerializer::toArray($m->toContract()))
            ->values()
            ->all();
    }

    private function require(User $actor, string $permission): void
    {
        if (! $this->permissions->hasPermission($actor, $permission)) {
            throw new AccessDeniedHttpException('PLATFORM_PERMISSION_DENIED');
        }
    }

    private function requireIncident(string $incidentKey): PlatformIncident
    {
        $row = $this->incidents->findByIncidentKey($incidentKey);
        if ($row === null) {
            throw new NotFoundHttpException('incident_not_found');
        }

        return $row;
    }

    private function loadAction(string $actionId): PlatformControlledAction
    {
        $row = PlatformControlledAction::query()->where('action_id', $actionId)->first();
        if ($row === null) {
            throw new NotFoundHttpException('action_not_found');
        }

        return $row;
    }

    private function rejectIfIncidentClosed(string $status, string $operation): void
    {
        if ($status === PlatformIncidentStatus::Closed->value) {
            throw ValidationException::withMessages([
                'incident_key' => ['incident_closed_for_'.$operation],
            ]);
        }
    }

    private function hasOpenHumanReview(string $incidentKey): bool
    {
        return PlatformControlledAction::query()
            ->where('incident_key', $incidentKey)
            ->where('action_type', ControlledActionArtifactType::HumanReviewRequest->value)
            ->whereIn('status', [
                ControlledActionStatus::Open->value,
                ControlledActionStatus::Assigned->value,
                ControlledActionStatus::Scheduled->value,
                ControlledActionStatus::Blocked->value,
            ])
            ->exists();
    }

    private function assertAssignableState(PlatformControlledAction $row): void
    {
        if (! in_array($row->status, [
            ControlledActionStatus::Open->value,
            ControlledActionStatus::Assigned->value,
            ControlledActionStatus::Scheduled->value,
        ], true)) {
            throw ValidationException::withMessages(['status' => ['invalid_state_for_assign']]);
        }
    }

    private function assertSchedulableState(PlatformControlledAction $row): void
    {
        if (! in_array($row->status, [
            ControlledActionStatus::Open->value,
            ControlledActionStatus::Assigned->value,
        ], true)) {
            throw ValidationException::withMessages(['status' => ['invalid_state_for_schedule']]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function stringBetween(array $input, string $key, int $min, int $max): string
    {
        $v = isset($input[$key]) ? trim((string) $input[$key]) : '';
        if (strlen($v) < $min || strlen($v) > $max) {
            throw ValidationException::withMessages([$key => ['invalid_length']]);
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function optionalString(array $input, string $key, int $max): ?string
    {
        if (! array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }
        $v = trim((string) $input[$key]);
        if ($v === '') {
            return null;
        }
        if (strlen($v) > $max) {
            throw ValidationException::withMessages([$key => ['invalid_length']]);
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredDate(array $input, string $key): Carbon
    {
        $raw = $input[$key] ?? null;
        if ($raw === null || $raw === '') {
            throw ValidationException::withMessages([$key => ['required']]);
        }
        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$key => ['invalid_datetime']]);
        }
    }

    private function assertSafeExternalReference(string $ref): void
    {
        if (! preg_match('/^[A-Za-z0-9:_\\-\\.\\/+#]{1,190}$/', $ref)) {
            throw ValidationException::withMessages(['external_reference' => ['invalid_format']]);
        }
    }
}
