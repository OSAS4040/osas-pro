<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence;

/**
 * Operator capability keys for intelligence operations — mapped 1:1 to IAM permission strings.
 */
enum PlatformIntelligenceCapability: string
{
    case ViewSignals = 'view_signals';
    case ViewIncidentCandidates = 'view_incident_candidates';
    case ViewIncidents = 'view_incidents';
    case ViewDecisionLog = 'view_decision_log';
    case AcknowledgeIncident = 'acknowledge_incident';
    case AssignIncidentOwner = 'assign_incident_owner';
    case EscalateIncident = 'escalate_incident';
    case ResolveIncident = 'resolve_incident';
    case CloseIncident = 'close_incident';
    case AddDecisionEntry = 'add_decision_entry';

    case ExecuteGuidedWorkflows = 'execute_guided_workflows';

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
