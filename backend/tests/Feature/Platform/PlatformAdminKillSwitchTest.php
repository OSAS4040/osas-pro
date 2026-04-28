<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformAdminKillSwitchTest extends TestCase
{
    public function test_platform_routes_return_404_when_admin_disabled(): void
    {
        Config::set('platform.admin_enabled', false);

        $user = $this->createStandalonePlatformOperator('ops@kill.switch');

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/ops-summary')
            ->assertNotFound();
    }
}
