<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence;

use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use PHPUnit\Framework\TestCase;

final class PlatformIntelligenceEnumsTest extends TestCase
{
    public function test_severity_values_are_stable(): void
    {
        $this->assertSame(
            ['info', 'low', 'medium', 'high', 'critical'],
            PlatformIntelligenceSeverity::values(),
        );
    }

    public function test_incident_status_values_are_stable(): void
    {
        $this->assertSame(
            ['open', 'acknowledged', 'under_review', 'escalated', 'monitoring', 'resolved', 'closed'],
            PlatformIncidentStatus::values(),
        );
    }

    public function test_ownership_escalation_decision_signal_enums_stable(): void
    {
        $this->assertSame(['unassigned', 'assigned', 'reassigned'], PlatformIncidentOwnershipState::values());
        $this->assertSame(['none', 'pending', 'escalated', 'contained'], PlatformIncidentEscalationState::values());
        $this->assertSame(
            ['observation', 'escalation', 'false_positive', 'monitor', 'closure', 'action_approved'],
            PlatformDecisionType::values(),
        );
        $this->assertSame(
            ['finance', 'operations', 'adoption', 'compliance', 'integrations', 'governance', 'intelligence', 'system'],
            PlatformSignalSourceType::values(),
        );
        $this->assertSame(
            ['metric_threshold', 'trend', 'anomaly', 'rule', 'manual', 'correlation', 'composite'],
            PlatformSignalType::values(),
        );
    }
}
