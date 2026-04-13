<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class PlatformAdminAccessIsolationTest extends TestCase
{
    public function test_tenant_owner_with_allowlisted_email_gets_403_on_admin_companies(): void
    {
        Config::set('saas.platform_admin_emails', ['ops@platform.example']);

        $tenant = $this->createTenant('owner');
        $tenant['user']->update(['email' => 'ops@platform.example']);

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/admin/companies')
            ->assertForbidden()
            ->assertJsonPath('code', 'PLATFORM_ACCESS_ONLY');
    }

    public function test_standalone_platform_operator_can_list_admin_companies(): void
    {
        Config::set('saas.platform_admin_emails', ['ops@platform.example']);

        $user = $this->createStandalonePlatformOperator('ops@platform.example');

        $this->actingAsUser($user)
            ->getJson('/api/v1/admin/companies')
            ->assertSuccessful()
            ->assertJsonStructure(['data', 'pagination']);
    }
}
