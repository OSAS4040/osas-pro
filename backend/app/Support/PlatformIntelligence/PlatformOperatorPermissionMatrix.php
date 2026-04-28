<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence;

/**
 * Canonical mapping: capability → platform IAM permission key.
 *
 * @see config/platform_permissions.php
 */
final class PlatformOperatorPermissionMatrix
{
    public const PERMISSION_SIGNALS_READ = 'platform.intelligence.signals.read';

    public const PERMISSION_CANDIDATES_READ = 'platform.intelligence.candidates.read';

    public const PERMISSION_INCIDENTS_READ = 'platform.intelligence.incidents.read';

    public const PERMISSION_DECISIONS_READ = 'platform.intelligence.decisions.read';

    public const PERMISSION_INCIDENTS_ACKNOWLEDGE = 'platform.intelligence.incidents.acknowledge';

    public const PERMISSION_INCIDENTS_ASSIGN_OWNER = 'platform.intelligence.incidents.assign_owner';

    public const PERMISSION_INCIDENTS_ESCALATE = 'platform.intelligence.incidents.escalate';

    public const PERMISSION_INCIDENTS_RESOLVE = 'platform.intelligence.incidents.resolve';

    public const PERMISSION_INCIDENTS_CLOSE = 'platform.intelligence.incidents.close';

    public const PERMISSION_DECISIONS_WRITE = 'platform.intelligence.decisions.write';

    public const PERMISSION_GUIDED_WORKFLOWS_EXECUTE = 'platform.intelligence.guided_workflows.execute';

    public static function permissionFor(PlatformIntelligenceCapability $capability): string
    {
        return match ($capability) {
            PlatformIntelligenceCapability::ViewSignals => self::PERMISSION_SIGNALS_READ,
            PlatformIntelligenceCapability::ViewIncidentCandidates => self::PERMISSION_CANDIDATES_READ,
            PlatformIntelligenceCapability::ViewIncidents => self::PERMISSION_INCIDENTS_READ,
            PlatformIntelligenceCapability::ViewDecisionLog => self::PERMISSION_DECISIONS_READ,
            PlatformIntelligenceCapability::AcknowledgeIncident => self::PERMISSION_INCIDENTS_ACKNOWLEDGE,
            PlatformIntelligenceCapability::AssignIncidentOwner => self::PERMISSION_INCIDENTS_ASSIGN_OWNER,
            PlatformIntelligenceCapability::EscalateIncident => self::PERMISSION_INCIDENTS_ESCALATE,
            PlatformIntelligenceCapability::ResolveIncident => self::PERMISSION_INCIDENTS_RESOLVE,
            PlatformIntelligenceCapability::CloseIncident => self::PERMISSION_INCIDENTS_CLOSE,
            PlatformIntelligenceCapability::AddDecisionEntry => self::PERMISSION_DECISIONS_WRITE,
            PlatformIntelligenceCapability::ExecuteGuidedWorkflows => self::PERMISSION_GUIDED_WORKFLOWS_EXECUTE,
        };
    }
}
