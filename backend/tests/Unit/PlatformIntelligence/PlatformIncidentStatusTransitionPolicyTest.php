<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence;

use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Policy\PlatformIncidentStatusTransitionPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PlatformIncidentStatusTransitionPolicyTest extends TestCase
{
    public function test_allowed_core_path(): void
    {
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Open,
            PlatformIncidentStatus::Acknowledged,
        ));
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Acknowledged,
            PlatformIncidentStatus::UnderReview,
        ));
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::UnderReview,
            PlatformIncidentStatus::Monitoring,
        ));
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Monitoring,
            PlatformIncidentStatus::Resolved,
        ));
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Resolved,
            PlatformIncidentStatus::Closed,
        ));
    }

    public function test_escalation_branches(): void
    {
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::UnderReview,
            PlatformIncidentStatus::Escalated,
        ));
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Escalated,
            PlatformIncidentStatus::Monitoring,
        ));
        $this->assertTrue(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Escalated,
            PlatformIncidentStatus::Resolved,
        ));
    }

    public function test_disallowed_jump_from_open_to_closed(): void
    {
        $this->assertFalse(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Open,
            PlatformIncidentStatus::Closed,
        ));
    }

    public function test_disallowed_acknowledged_to_resolved(): void
    {
        $this->assertFalse(PlatformIncidentStatusTransitionPolicy::isAllowed(
            PlatformIncidentStatus::Acknowledged,
            PlatformIncidentStatus::Resolved,
        ));
    }

    public function test_assert_throws_on_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PlatformIncidentStatusTransitionPolicy::assertAllowed(
            PlatformIncidentStatus::Open,
            PlatformIncidentStatus::Resolved,
        );
    }
}
