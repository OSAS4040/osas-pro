<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence;

use App\Services\Platform\PlatformPermissionService;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionPermissionMatrix;
use App\Support\PlatformIntelligence\PlatformIntelligenceCapability;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class PlatformIntelligencePermissionIntegrationTest extends TestCase
{
    public function test_auditor_read_only_for_intelligence_capabilities(): void
    {
        Config::set('platform.admin_enabled', true);

        $user = $this->createStandalonePlatformOperator('audit-intel@test.sa', [
            'platform_role' => 'auditor',
        ]);

        $svc = app(PlatformPermissionService::class);
        $this->assertTrue($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::ViewSignals));
        $this->assertTrue($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::ViewIncidentCandidates));
        $this->assertTrue($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::ViewIncidents));
        $this->assertFalse($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::EscalateIncident));
        $this->assertFalse($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::CloseIncident));
        $this->assertFalse($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::ExecuteGuidedWorkflows));
        $this->assertTrue($svc->hasPermission($user, ControlledActionPermissionMatrix::VIEW));
        $this->assertFalse($svc->hasPermission($user, ControlledActionPermissionMatrix::CREATE_FOLLOW_UP));
    }

    public function test_support_agent_cannot_escalate_but_can_acknowledge(): void
    {
        Config::set('platform.admin_enabled', true);

        $user = $this->createStandalonePlatformOperator('support-intel@test.sa', [
            'platform_role' => 'support_agent',
        ]);

        $svc = app(PlatformPermissionService::class);
        $this->assertTrue($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::AcknowledgeIncident));
        $this->assertFalse($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::EscalateIncident));
        $this->assertTrue($svc->intelligenceCapabilityGranted($user, PlatformIntelligenceCapability::ExecuteGuidedWorkflows));
    }

    public function test_operations_admin_has_full_incident_lifecycle_permissions(): void
    {
        Config::set('platform.admin_enabled', true);

        $user = $this->createStandalonePlatformOperator('ops-intel@test.sa', [
            'platform_role' => 'operations_admin',
        ]);

        $svc = app(PlatformPermissionService::class);
        foreach (PlatformIntelligenceCapability::all() as $cap) {
            $this->assertTrue($svc->intelligenceCapabilityGranted($user, $cap), $cap->value);
        }
    }

    public function test_auditor_platform_grants_exclude_escalation(): void
    {
        Config::set('platform.admin_enabled', true);

        $user = $this->createStandalonePlatformOperator('merge-intel@test.sa', [
            'platform_role' => 'auditor',
        ]);

        $grants = app(\App\Support\PlatformIntelligence\PlatformRolePermissionResolver::class)
            ->platformPermissionGrantsForUser($user);

        $this->assertContains('platform.intelligence.signals.read', $grants);
        $this->assertContains('platform.intelligence.candidates.read', $grants);
        $this->assertNotContains('platform.intelligence.incidents.escalate', $grants);
    }
}
