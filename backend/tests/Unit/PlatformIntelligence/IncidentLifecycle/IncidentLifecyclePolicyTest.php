<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\IncidentLifecycle;

use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\IncidentLifecycle\IncidentLifecyclePolicy;
use App\Support\PlatformIntelligence\IncidentLifecycle\IncidentLifecycleException;
use PHPUnit\Framework\TestCase;

final class IncidentLifecyclePolicyTest extends TestCase
{
    private IncidentLifecyclePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new IncidentLifecyclePolicy;
    }

    public function test_acknowledge_only_from_open(): void
    {
        $this->policy->assertAcknowledge(PlatformIncidentStatus::Open);
        $this->expectException(IncidentLifecycleException::class);
        $this->policy->assertAcknowledge(PlatformIncidentStatus::Acknowledged);
    }

    public function test_close_only_from_resolved(): void
    {
        $this->policy->assertClose(PlatformIncidentStatus::Resolved);
        $this->expectException(IncidentLifecycleException::class);
        $this->policy->assertClose(PlatformIncidentStatus::Open);
    }

    public function test_resolve_only_from_monitoring_or_escalated(): void
    {
        $this->policy->assertResolve(PlatformIncidentStatus::Monitoring);
        $this->policy->assertResolve(PlatformIncidentStatus::Escalated);
        $this->expectException(IncidentLifecycleException::class);
        $this->policy->assertResolve(PlatformIncidentStatus::Open);
    }
}
