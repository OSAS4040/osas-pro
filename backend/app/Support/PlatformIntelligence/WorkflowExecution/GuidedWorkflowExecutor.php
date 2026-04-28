<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\WorkflowExecution;

use App\Models\PlatformIncident;
use App\Models\User;
use App\Services\Platform\PlatformPermissionService;
use App\Support\PlatformIntelligence\DecisionLog\DecisionRecordingService;
use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\GuidedWorkflows\GuidedWorkflowExecutorException;
use App\Support\PlatformIntelligence\GuidedWorkflows\GuidedWorkflowKey;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use App\Support\PlatformIntelligence\IncidentLifecycle\IncidentLifecycleService;
use App\Support\PlatformIntelligence\PlatformOperatorPermissionMatrix;
use Illuminate\Support\Facades\DB;

/**
 * Human-guided orchestration only — composes existing lifecycle + decision services.
 */
final class GuidedWorkflowExecutor
{
    public function __construct(
        private readonly PlatformPermissionService $permissions,
        private readonly IncidentLifecycleService $lifecycle,
        private readonly DecisionRecordingService $decisions,
        private readonly IncidentRepository $incidents,
        private readonly GuidedWorkflowTraceEmitter $workflowTrace,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(User $user, PlatformIncident $incident): array
    {
        $out = [];
        foreach (GuidedWorkflowKey::cases() as $key) {
            $out[] = $this->describeWorkflow($key, $user, $incident);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeWorkflow(GuidedWorkflowKey $key, User $user, PlatformIncident $incident): array
    {
        $meta = $this->meta($key);
        $avail = $this->availability($key, $user, $incident);

        return [
            'workflow_key' => $key->value,
            'workflow_type' => $key->value,
            'label' => $meta['label'],
            'description' => $meta['description'],
            'preview' => $meta['preview'],
            'available' => $avail['available'],
            'unavailable_reason' => $avail['reason'],
            'requires_owner_ref' => $meta['requires_owner_ref'],
            'requires_rationale' => $meta['requires_rationale'],
            'requires_decision_summary' => $meta['requires_decision_summary'],
            'requires_expected_outcome' => $meta['requires_expected_outcome'],
        ];
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    public function availability(GuidedWorkflowKey $key, User $user, PlatformIncident $incident): array
    {
        try {
            $this->assertPermissions($key, $user);
            $this->assertStructuralPreconditions($key, $incident);

            return ['available' => true, 'reason' => null];
        } catch (GuidedWorkflowExecutorException $e) {
            return ['available' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * @param  array{
     *     confirm: bool,
     *     owner_ref?: string|null,
     *     rationale?: string|null,
     *     decision_summary?: string|null,
     *     expected_outcome?: string|null,
     *     follow_up_required?: bool|null,
     *     close_reason?: string|null,
     *     escalate_reason?: string|null,
     * }  $payload
     * @return array<string, mixed>
     */
    public function execute(GuidedWorkflowKey $key, string $incidentKey, User $actor, array $payload): array
    {
        if (! ($payload['confirm'] ?? false)) {
            throw new GuidedWorkflowExecutorException('confirmation_required', 422);
        }

        $row = $this->incidents->findByIncidentKey($incidentKey);
        if ($row === null) {
            throw new GuidedWorkflowExecutorException('incident_not_found', 404);
        }

        $this->assertPermissions($key, $actor);
        $this->assertStructuralPreconditions($key, $row);
        $this->assertPayloadPreconditions($key, $payload);

        $this->workflowTrace->started($actor, $incidentKey, $key, []);

        try {
            $result = DB::transaction(function () use ($key, $incidentKey, $actor, $payload, $row): array {
                return match ($key) {
                    GuidedWorkflowKey::AcknowledgeAssign => $this->runAcknowledgeAssign($incidentKey, $actor, $payload),
                    GuidedWorkflowKey::UnderReviewDecision => $this->runUnderReviewDecision($incidentKey, $actor, $payload),
                    GuidedWorkflowKey::EscalateDecision => $this->runEscalateDecision($incidentKey, $actor, $payload),
                    GuidedWorkflowKey::MonitorTransition => $this->runMonitorTransition($incidentKey, $actor),
                    GuidedWorkflowKey::MonitorWithDecision => $this->runMonitorWithDecision($incidentKey, $actor, $payload),
                    GuidedWorkflowKey::ResolveClosure => $this->runResolveClosure($incidentKey, $actor, $payload),
                    GuidedWorkflowKey::CloseFinal => $this->runCloseFinal($incidentKey, $actor, $payload),
                    GuidedWorkflowKey::FalsePositive => $this->runFalsePositive($incidentKey, $actor, $payload),
                };
            });

            $this->workflowTrace->completed($actor, $incidentKey, $key, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->workflowTrace->failed($actor, $incidentKey, $key, $e->getMessage(), []);
            throw $e;
        }
    }

    private function assertStructuralPreconditions(GuidedWorkflowKey $key, PlatformIncident $incident): void
    {
        $st = PlatformIncidentStatus::from($incident->status);

        match ($key) {
            GuidedWorkflowKey::AcknowledgeAssign => $this->preAckAssign($st),
            GuidedWorkflowKey::UnderReviewDecision => $this->preUnderReview($st),
            GuidedWorkflowKey::EscalateDecision => $this->preEscalate($st),
            GuidedWorkflowKey::MonitorTransition,
            GuidedWorkflowKey::MonitorWithDecision => $this->preMonitor($st),
            GuidedWorkflowKey::ResolveClosure,
            GuidedWorkflowKey::FalsePositive => $this->preResolve($st),
            GuidedWorkflowKey::CloseFinal => $this->preClose($st),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertPayloadPreconditions(GuidedWorkflowKey $key, array $payload): void
    {
        if ($key === GuidedWorkflowKey::AcknowledgeAssign) {
            $ownerRef = trim((string) ($payload['owner_ref'] ?? ''));
            if ($ownerRef === '') {
                throw new GuidedWorkflowExecutorException('owner_ref_required', 422);
            }
        }

        if ($key === GuidedWorkflowKey::UnderReviewDecision || $key === GuidedWorkflowKey::EscalateDecision
            || $key === GuidedWorkflowKey::MonitorWithDecision || $key === GuidedWorkflowKey::ResolveClosure
            || $key === GuidedWorkflowKey::CloseFinal || $key === GuidedWorkflowKey::FalsePositive) {
            $this->requireText($payload['decision_summary'] ?? null, 'decision_summary_required', 3);
            $this->requireText($payload['rationale'] ?? null, 'rationale_required', 3);
        }

        if ($key === GuidedWorkflowKey::MonitorWithDecision) {
            $this->requireText($payload['expected_outcome'] ?? null, 'expected_outcome_required', 3);
        }

        if ($key === GuidedWorkflowKey::CloseFinal) {
            $this->requireText($payload['close_reason'] ?? null, 'close_reason_required', 3);
        }
    }

    private function preAckAssign(PlatformIncidentStatus $st): void
    {
        if (! in_array($st, [PlatformIncidentStatus::Open, PlatformIncidentStatus::Acknowledged], true)) {
            throw new GuidedWorkflowExecutorException('precondition_status_not_open_or_acknowledged', 422);
        }
    }

    private function preUnderReview(PlatformIncidentStatus $st): void
    {
        if ($st !== PlatformIncidentStatus::Acknowledged) {
            throw new GuidedWorkflowExecutorException('precondition_acknowledged_only', 422);
        }
    }

    private function preEscalate(PlatformIncidentStatus $st): void
    {
        if ($st !== PlatformIncidentStatus::UnderReview) {
            throw new GuidedWorkflowExecutorException('precondition_under_review_only', 422);
        }
    }

    private function preMonitor(PlatformIncidentStatus $st): void
    {
        if ($st !== PlatformIncidentStatus::UnderReview && $st !== PlatformIncidentStatus::Escalated) {
            throw new GuidedWorkflowExecutorException('precondition_monitor_source_invalid', 422);
        }
    }

    private function preResolve(PlatformIncidentStatus $st): void
    {
        if ($st !== PlatformIncidentStatus::Monitoring && $st !== PlatformIncidentStatus::Escalated) {
            throw new GuidedWorkflowExecutorException('precondition_resolve_source_invalid', 422);
        }
    }

    private function preClose(PlatformIncidentStatus $st): void
    {
        if ($st !== PlatformIncidentStatus::Resolved) {
            throw new GuidedWorkflowExecutorException('precondition_resolved_only', 422);
        }
    }

    private function requireText(?string $value, string $code, int $minLen): void
    {
        $t = trim((string) $value);
        if (strlen($t) < $minLen) {
            throw new GuidedWorkflowExecutorException($code, 422);
        }
    }

    private function assertPermissions(GuidedWorkflowKey $key, User $user): void
    {
        foreach ($this->requiredPermissions($key) as $perm) {
            if (! $this->permissions->hasPermission($user, $perm)) {
                throw new GuidedWorkflowExecutorException('missing_permission:'.$perm, 403);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function requiredPermissions(GuidedWorkflowKey $key): array
    {
        return match ($key) {
            GuidedWorkflowKey::AcknowledgeAssign => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_ACKNOWLEDGE,
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_ASSIGN_OWNER,
            ],
            GuidedWorkflowKey::UnderReviewDecision => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_ACKNOWLEDGE,
                PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_WRITE,
            ],
            GuidedWorkflowKey::EscalateDecision => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_ESCALATE,
                PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_WRITE,
            ],
            GuidedWorkflowKey::MonitorTransition => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_ACKNOWLEDGE,
            ],
            GuidedWorkflowKey::MonitorWithDecision => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_ACKNOWLEDGE,
                PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_WRITE,
            ],
            GuidedWorkflowKey::ResolveClosure => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_RESOLVE,
                PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_WRITE,
            ],
            GuidedWorkflowKey::CloseFinal => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_CLOSE,
                PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_WRITE,
            ],
            GuidedWorkflowKey::FalsePositive => [
                PlatformOperatorPermissionMatrix::PERMISSION_INCIDENTS_RESOLVE,
                PlatformOperatorPermissionMatrix::PERMISSION_DECISIONS_WRITE,
            ],
        };
    }

    /**
     * @return array<string, string|bool>
     */
    private function meta(GuidedWorkflowKey $key): array
    {
        return match ($key) {
            GuidedWorkflowKey::AcknowledgeAssign => [
                'label' => 'إقرار وتعيين مالك',
                'description' => 'إقرار الاستلام إن كان الحادث مفتوحًا، ثم تعيين مالك.',
                'preview' => 'acknowledge (if open) → assign_owner',
                'requires_owner_ref' => true,
                'requires_rationale' => false,
                'requires_decision_summary' => false,
                'requires_expected_outcome' => false,
            ],
            GuidedWorkflowKey::UnderReviewDecision => [
                'label' => 'تحت المراجعة + قرار',
                'description' => 'نقل الحادث إلى تحت المراجعة ثم تسجيل قرار ملاحظة.',
                'preview' => 'move_under_review → decision(observation)',
                'requires_owner_ref' => false,
                'requires_rationale' => true,
                'requires_decision_summary' => true,
                'requires_expected_outcome' => false,
            ],
            GuidedWorkflowKey::EscalateDecision => [
                'label' => 'تصعيد + قرار',
                'description' => 'تصعيد الحادث ثم تسجيل قرار تصعيد مؤسسي.',
                'preview' => 'escalate → decision(escalation)',
                'requires_owner_ref' => false,
                'requires_rationale' => true,
                'requires_decision_summary' => true,
                'requires_expected_outcome' => false,
            ],
            GuidedWorkflowKey::MonitorTransition => [
                'label' => 'الانتقال للمراقبة',
                'description' => 'نقل الحالة إلى مراقبة دون قرار مؤسسي.',
                'preview' => 'move_monitoring',
                'requires_owner_ref' => false,
                'requires_rationale' => false,
                'requires_decision_summary' => false,
                'requires_expected_outcome' => false,
            ],
            GuidedWorkflowKey::MonitorWithDecision => [
                'label' => 'مراقبة + قرار ومتابعة',
                'description' => 'نقل إلى مراقبة مع تسجيل قرار مراقبة ونتيجة متوقعة.',
                'preview' => 'move_monitoring → decision(monitor)',
                'requires_owner_ref' => false,
                'requires_rationale' => true,
                'requires_decision_summary' => true,
                'requires_expected_outcome' => true,
            ],
            GuidedWorkflowKey::ResolveClosure => [
                'label' => 'حل + قرار إغلاق معنوي',
                'description' => 'حل الحادث ثم تسجيل قرار إغلاق/ملخص مؤسسي.',
                'preview' => 'resolve → decision(closure)',
                'requires_owner_ref' => false,
                'requires_rationale' => true,
                'requires_decision_summary' => true,
                'requires_expected_outcome' => false,
            ],
            GuidedWorkflowKey::CloseFinal => [
                'label' => 'إغلاق نهائي + قرار',
                'description' => 'إغلاق الحادث بعد الحل مع تسجيل قرار إغلاق مؤسسي.',
                'preview' => 'close → decision(closure)',
                'requires_owner_ref' => false,
                'requires_rationale' => true,
                'requires_decision_summary' => true,
                'requires_expected_outcome' => false,
            ],
            GuidedWorkflowKey::FalsePositive => [
                'label' => 'إيجابية خاطئة',
                'description' => 'تسجيل قرار إيجابية خاطئة ثم حل الحادث بنفس المبرر.',
                'preview' => 'decision(false_positive) → resolve',
                'requires_owner_ref' => false,
                'requires_rationale' => true,
                'requires_decision_summary' => true,
                'requires_expected_outcome' => false,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runAcknowledgeAssign(string $incidentKey, User $actor, array $payload): array
    {
        $row = $this->incidents->findByIncidentKey($incidentKey);
        if ($row === null) {
            throw new GuidedWorkflowExecutorException('incident_not_found', 404);
        }
        $st = PlatformIncidentStatus::from($row->status);
        if ($st === PlatformIncidentStatus::Open) {
            $this->lifecycle->acknowledge($incidentKey, $actor);
        }
        $ownerRef = trim((string) $payload['owner_ref']);
        $this->lifecycle->assignOwner($incidentKey, $actor, $ownerRef);

        return [
            'workflow_key' => GuidedWorkflowKey::AcknowledgeAssign->value,
            'steps' => ['acknowledge_if_needed', 'assign_owner'],
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runUnderReviewDecision(string $incidentKey, User $actor, array $payload): array
    {
        $this->lifecycle->moveUnderReview($incidentKey, $actor);
        $d = $this->decisions->record($incidentKey, $actor, [
            'decision_type' => PlatformDecisionType::Observation->value,
            'decision_summary' => trim((string) $payload['decision_summary']),
            'rationale' => trim((string) $payload['rationale']),
            'expected_outcome' => trim((string) ($payload['expected_outcome'] ?? '')),
            'follow_up_required' => (bool) ($payload['follow_up_required'] ?? false),
        ]);

        return [
            'workflow_key' => GuidedWorkflowKey::UnderReviewDecision->value,
            'decision_id' => $d->decision_id,
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runEscalateDecision(string $incidentKey, User $actor, array $payload): array
    {
        $escReason = trim((string) ($payload['escalate_reason'] ?? $payload['rationale'] ?? ''));
        $this->lifecycle->escalate($incidentKey, $actor, $escReason !== '' ? $escReason : null);
        $d = $this->decisions->record($incidentKey, $actor, [
            'decision_type' => PlatformDecisionType::Escalation->value,
            'decision_summary' => trim((string) $payload['decision_summary']),
            'rationale' => trim((string) $payload['rationale']),
            'follow_up_required' => true,
        ]);

        return [
            'workflow_key' => GuidedWorkflowKey::EscalateDecision->value,
            'decision_id' => $d->decision_id,
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runMonitorTransition(string $incidentKey, User $actor): array
    {
        $this->lifecycle->moveMonitoring($incidentKey, $actor);

        return [
            'workflow_key' => GuidedWorkflowKey::MonitorTransition->value,
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runMonitorWithDecision(string $incidentKey, User $actor, array $payload): array
    {
        $this->lifecycle->moveMonitoring($incidentKey, $actor);
        $d = $this->decisions->record($incidentKey, $actor, [
            'decision_type' => PlatformDecisionType::Monitor->value,
            'decision_summary' => trim((string) $payload['decision_summary']),
            'rationale' => trim((string) $payload['rationale']),
            'expected_outcome' => trim((string) $payload['expected_outcome']),
            'follow_up_required' => (bool) ($payload['follow_up_required'] ?? false),
        ]);

        return [
            'workflow_key' => GuidedWorkflowKey::MonitorWithDecision->value,
            'decision_id' => $d->decision_id,
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runResolveClosure(string $incidentKey, User $actor, array $payload): array
    {
        $r = trim((string) $payload['rationale']);
        $this->lifecycle->resolve($incidentKey, $actor, $r);
        $d = $this->decisions->record($incidentKey, $actor, [
            'decision_type' => PlatformDecisionType::Closure->value,
            'decision_summary' => trim((string) $payload['decision_summary']),
            'rationale' => $r,
            'follow_up_required' => (bool) ($payload['follow_up_required'] ?? false),
        ]);

        return [
            'workflow_key' => GuidedWorkflowKey::ResolveClosure->value,
            'decision_id' => $d->decision_id,
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runCloseFinal(string $incidentKey, User $actor, array $payload): array
    {
        $closeReason = trim((string) $payload['close_reason']);
        $this->lifecycle->close($incidentKey, $actor, $closeReason);
        $d = $this->decisions->record($incidentKey, $actor, [
            'decision_type' => PlatformDecisionType::Closure->value,
            'decision_summary' => trim((string) $payload['decision_summary']),
            'rationale' => trim((string) $payload['rationale']),
            'expected_outcome' => 'closed',
            'follow_up_required' => false,
        ]);

        return [
            'workflow_key' => GuidedWorkflowKey::CloseFinal->value,
            'decision_id' => $d->decision_id,
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runFalsePositive(string $incidentKey, User $actor, array $payload): array
    {
        $r = trim((string) $payload['rationale']);
        $d = $this->decisions->record($incidentKey, $actor, [
            'decision_type' => PlatformDecisionType::FalsePositive->value,
            'decision_summary' => trim((string) $payload['decision_summary']),
            'rationale' => $r,
            'follow_up_required' => false,
        ]);
        $this->lifecycle->resolve($incidentKey, $actor, $r);

        return [
            'workflow_key' => GuidedWorkflowKey::FalsePositive->value,
            'decision_id' => $d->decision_id,
            'final_status' => $this->incidents->findByIncidentKey($incidentKey)?->status,
        ];
    }
}
