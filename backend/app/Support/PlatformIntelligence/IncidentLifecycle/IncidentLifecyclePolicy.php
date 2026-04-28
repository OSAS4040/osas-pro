<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\IncidentLifecycle;

use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;

/**
 * Allowed status transitions only — no silent jumps.
 */
final class IncidentLifecyclePolicy
{
    public function assertAcknowledge(PlatformIncidentStatus $current): void
    {
        if ($current !== PlatformIncidentStatus::Open) {
            throw new IncidentLifecycleException('acknowledge_only_from_open');
        }
    }

    public function assertMoveUnderReview(PlatformIncidentStatus $current): void
    {
        if ($current !== PlatformIncidentStatus::Acknowledged) {
            throw new IncidentLifecycleException('under_review_only_from_acknowledged');
        }
    }

    public function assertEscalate(PlatformIncidentStatus $current): void
    {
        if ($current !== PlatformIncidentStatus::UnderReview) {
            throw new IncidentLifecycleException('escalate_only_from_under_review');
        }
    }

    public function assertMoveMonitoring(PlatformIncidentStatus $current): void
    {
        if ($current !== PlatformIncidentStatus::UnderReview && $current !== PlatformIncidentStatus::Escalated) {
            throw new IncidentLifecycleException('monitoring_only_from_under_review_or_escalated');
        }
    }

    public function assertResolve(PlatformIncidentStatus $current): void
    {
        if ($current !== PlatformIncidentStatus::Monitoring && $current !== PlatformIncidentStatus::Escalated) {
            throw new IncidentLifecycleException('resolve_only_from_monitoring_or_escalated');
        }
    }

    public function assertClose(PlatformIncidentStatus $current): void
    {
        if ($current !== PlatformIncidentStatus::Resolved) {
            throw new IncidentLifecycleException('close_only_from_resolved');
        }
    }

    public function assertOwnerAssignable(PlatformIncidentStatus $current): void
    {
        if (in_array($current, [PlatformIncidentStatus::Resolved, PlatformIncidentStatus::Closed], true)) {
            throw new IncidentLifecycleException('owner_not_assignable_on_terminal_status');
        }
    }

    /**
     * Escalation state when entering {@see PlatformIncidentStatus::Escalated}.
     */
    public function escalationForEscalatedStatus(): PlatformIncidentEscalationState
    {
        return PlatformIncidentEscalationState::Escalated;
    }

    /**
     * Escalation state when entering monitoring from escalated.
     */
    public function escalationWhenEnteringMonitoringFromEscalated(): PlatformIncidentEscalationState
    {
        return PlatformIncidentEscalationState::Contained;
    }

    /**
     * Escalation state when entering monitoring from under_review.
     */
    public function escalationWhenEnteringMonitoringFromUnderReview(PlatformIncidentEscalationState $current): PlatformIncidentEscalationState
    {
        return $current;
    }
}
