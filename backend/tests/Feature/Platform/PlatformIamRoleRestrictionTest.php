<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformIamRoleRestrictionTest extends TestCase
{
    public function test_auditor_cannot_access_ops_summary(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', []);
        Config::set('saas.platform_admin_phones', []);

        $user = User::withoutGlobalScopes()->create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'org_unit_id'        => null,
            'customer_id'        => null,
            'name'               => 'Auditor',
            'email'              => 'auditor-only@internal.platform.sa',
            'password'           => 'Password123!',
            'phone'              => null,
            'role'               => UserRole::Owner,
            'status'             => UserStatus::Active,
            'is_active'          => true,
            'is_platform_user'   => true,
            'platform_role'      => 'auditor',
            'account_type'       => null,
            'registration_stage' => 'phone_verified',
        ]);

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/ops-summary')
            ->assertForbidden()
            ->assertJsonPath('code', 'PLATFORM_PERMISSION_DENIED');
    }
}
