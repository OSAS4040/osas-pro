<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Platform\PlatformNotificationCenterService;
use PHPUnit\Framework\TestCase;

final class PlatformNotificationCenterServiceTest extends TestCase
{
    public function test_priority_weight_ordering_is_stable(): void
    {
        $this->assertGreaterThan(
            PlatformNotificationCenterService::priorityWeight('medium'),
            PlatformNotificationCenterService::priorityWeight('high'),
        );
        $this->assertGreaterThan(
            PlatformNotificationCenterService::priorityWeight('informational'),
            PlatformNotificationCenterService::priorityWeight('medium'),
        );
        $this->assertGreaterThan(
            PlatformNotificationCenterService::priorityWeight('unknown'),
            PlatformNotificationCenterService::priorityWeight('high'),
        );
    }
}

