<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Trace;

enum PlatformIntelligenceTraceEventType: string
{
    case PolicyEvaluated = 'platform_intelligence.policy_evaluated';
    case SignalObserved = 'platform_intelligence.signal_observed';
    case SignalDetected = 'platform_intelligence.signal_detected';
    case SignalScored = 'platform_intelligence.signal_scored';
    case SignalGrouped = 'platform_intelligence.signal_grouped';
    case SignalDeduped = 'platform_intelligence.signal_deduped';
    case SignalExplained = 'platform_intelligence.signal_explained';
    case SignalRecommended = 'platform_intelligence.signal_recommended';
    case CandidateDerived = 'platform_intelligence.candidate_derived';
    case CandidateGrouped = 'platform_intelligence.candidate_grouped';
    case CandidateSuppressed = 'platform_intelligence.candidate_suppressed';
    case CandidateScored = 'platform_intelligence.candidate_scored';
    case CandidateExplained = 'platform_intelligence.candidate_explained';
    case IncidentMaterialized = 'platform_intelligence.incident_materialized';
    case IncidentAcknowledged = 'platform_intelligence.incident_acknowledged';
    case IncidentOwnerAssigned = 'platform_intelligence.incident_owner_assigned';
    case IncidentOwnerReassigned = 'platform_intelligence.incident_reassigned';
    case IncidentEscalated = 'platform_intelligence.incident_escalated';
    case IncidentMovedToMonitoring = 'platform_intelligence.incident_moved_to_monitoring';
    case IncidentMovedToUnderReview = 'platform_intelligence.incident_moved_to_under_review';
    case IncidentResolved = 'platform_intelligence.incident_resolved';
    case IncidentClosed = 'platform_intelligence.incident_closed';
    case IncidentNoteAppended = 'platform_intelligence.incident_note_appended';
    /** @deprecated Prefer granular incident_* cases; retained for backward compatibility in parsers. */
    case IncidentLifecycle = 'platform_intelligence.incident_lifecycle';
    case DecisionRecorded = 'platform_intelligence.decision_recorded';
    case WorkflowStarted = 'platform_intelligence.workflow_started';
    case WorkflowCompleted = 'platform_intelligence.workflow_completed';
    case WorkflowFailed = 'platform_intelligence.workflow_failed';
    case CorrelationBuilt = 'platform_intelligence.correlation_built';
    case CommandSurfaceRendered = 'platform_intelligence.command_surface_rendered';
    case CommandItemRanked = 'platform_intelligence.command_item_ranked';
    case IncidentContextLinked = 'platform_intelligence.incident_context_linked';
    case DecisionContextLinked = 'platform_intelligence.decision_context_linked';
    case WorkflowContextLinked = 'platform_intelligence.workflow_context_linked';
    case ControlledActionCreated = 'platform_intelligence.controlled_action_created';
    case ControlledActionAssigned = 'platform_intelligence.controlled_action_assigned';
    case ControlledActionScheduled = 'platform_intelligence.controlled_action_scheduled';
    case ControlledActionCompleted = 'platform_intelligence.controlled_action_completed';
    case ControlledActionCanceled = 'platform_intelligence.controlled_action_canceled';
    case ControlledActionReopened = 'platform_intelligence.controlled_action_reopened';
}
