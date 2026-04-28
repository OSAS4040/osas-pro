<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression: platform IAM grants merged into login + /auth/me permission snapshots.
 *
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformAuthPermissionsMergeTest extends TestCase
{
    public function test_login_response_includes_intelligence_permissions_for_platform_auditor(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['intel-auditor-merge@platform.test']);
        Config::set('saas.platform_admin_phones', []);

        $this->createStandalonePlatformOperator('intel-auditor-merge@platform.test', [
            'platform_role' => 'auditor',
        ]);

        $res = $this->postJson('/api/v1/auth/login', [
            'email'    => 'intel-auditor-merge@platform.test',
            'password' => 'Password123!',
        ]);

        $res->assertSuccessful();
        $perms = $res->json('permissions');
        $this->assertIsArray($perms);
        $this->assertContains('platform.intelligence.signals.read', $perms);
        $this->assertContains('platform.intelligence.candidates.read', $perms);
        $this->assertContains('platform.intelligence.decisions.read', $perms);
        $this->assertContains('platform.intelligence.controlled_actions.view', $perms);
        $this->assertNotContains('platform.intelligence.incidents.escalate', $perms);
        $this->assertNotContains('platform.intelligence.controlled_actions.create_follow_up', $perms);
    }

    public function test_me_includes_merged_platform_permissions_for_platform_operator(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['intel-me-merge@platform.test']);
        Config::set('saas.platform_admin_phones', []);

        $this->createStandalonePlatformOperator('intel-me-merge@platform.test', [
            'platform_role' => 'operations_admin',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email'    => 'intel-me-merge@platform.test',
            'password' => 'Password123!',
        ])->assertSuccessful()->json('token');

        $me = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/me');
        $me->assertSuccessful();
        $perms = $me->json('permissions');
        $this->assertIsArray($perms);
        $this->assertContains('platform.intelligence.incidents.escalate', $perms);
        $this->assertContains('platform.intelligence.guided_workflows.execute', $perms);
        $this->assertContains('platform.intelligence.controlled_actions.create_follow_up', $perms);
    }

    public function test_tenant_owner_login_does_not_receive_platform_intelligence_keys(): void
    {
        Config::set('platform.admin_enabled', true);
        $tenant = $this->createTenant('owner');

        $res = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $res->assertSuccessful();
        $perms = $res->json('permissions');
        $this->assertIsArray($perms);
        foreach ($perms as $p) {
            $this->assertIsString($p);
            $this->assertFalse(
                str_starts_with($p, 'platform.intelligence.'),
                'tenant snapshot must not merge platform intelligence keys',
            );
        }
    }
}
