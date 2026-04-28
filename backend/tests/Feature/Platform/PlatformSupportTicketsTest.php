<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformSupportTicketsTest extends TestCase
{
    public function test_platform_operator_can_list_all_tenant_tickets(): void
    {
        Config::set('saas.platform_admin_emails', ['ops@platform.example']);

        $tenant = $this->createTenant('owner');
        SupportTicket::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            // ticket_number column is varchar(20); uniqid() suffix makes TKT-TEST-* too long
            'ticket_number' => 'TKT-'.strtoupper(bin2hex(random_bytes(5))),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'created_by' => $tenant['user']->id,
            'subject' => 'اختبار منصة',
            'description' => 'وصف',
            'category' => 'general',
            'priority' => 'medium',
            'status' => 'open',
            'channel' => 'portal',
        ]);

        $platform = $this->createStandalonePlatformOperator('ops@platform.example', [
            'platform_role' => 'platform_admin',
        ]);

        $this->actingAsUser($platform)
            ->getJson('/api/v1/platform/support/tickets?per_page=5')
            ->assertSuccessful()
            ->assertJsonPath('data.data.0.subject', 'اختبار منصة');
    }

    public function test_platform_operator_without_support_permission_gets_403(): void
    {
        Config::set('saas.platform_admin_emails', ['limited@platform.example']);

        $this->createTenant('owner');

        $noGrants = $this->createStandalonePlatformOperator('limited@platform.example', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);

        $this->actingAsUser($noGrants)
            ->getJson('/api/v1/platform/support/tickets')
            ->assertForbidden()
            ->assertJsonPath('code', 'PLATFORM_PERMISSION_DENIED');
    }
}
