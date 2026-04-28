<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Company;
use App\Models\PlatformControlledAction;
use App\Models\PlatformDecisionLogEntry;
use App\Models\PlatformIncident;
use App\Models\RegistrationProfile;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformNotificationCenterApiTest extends TestCase
{
    public function test_notifications_requires_permission(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('notif-no-perm@platform.test', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);
        $user = User::where('email', 'notif-no-perm@platform.test')->firstOrFail();

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/notifications')
            ->assertForbidden();
    }

    public function test_notifications_accessible_with_subscription_manage_without_notifications_read(): void
    {
        Config::set('platform.admin_enabled', true);
        /** @var array<string, list<string>> $roles */
        $roles = (array) config('platform_roles.roles');
        $roles['__subscription_notifications_only__'] = ['platform.subscription.manage'];
        Config::set('platform_roles.roles', $roles);

        $this->createStandalonePlatformOperator('notif-submgr-only@platform.test', [
            'platform_role' => '__subscription_notifications_only__',
        ]);
        $user = User::where('email', 'notif-submgr-only@platform.test')->firstOrFail();

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/notifications')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['total', 'unread_count', 'requires_action_count', 'attention_now'],
                'trace_id',
            ]);
    }

    public function test_notifications_contract_and_deep_links_are_valid(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('notif-admin@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $platform = User::where('email', 'notif-admin@platform.test')->firstOrFail();
        $tenant = $this->createTenant('owner');
        $company = Company::query()->findOrFail($tenant['company']->id);
        $company->forceFill(['financial_model_status' => 'pending_platform_review'])->save();

        RegistrationProfile::query()->updateOrCreate(
            ['user_id' => $tenant['user']->id],
            [
                'status' => 'pending_review',
                'company_activation_status' => 'pending_review',
                'company_name' => 'شركة اختبار',
                'submitted_at' => now()->subMinutes(10),
            ],
        );

        SupportTicket::query()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'TKT-TEST-0001',
            'company_id' => $company->id,
            'created_by' => $tenant['user']->id,
            'subject' => 'تذكرة تحتاج متابعة',
            'description' => 'متابعة',
            'priority' => 'high',
            'status' => 'open',
        ]);

        PlatformIncident::query()->create([
            'incident_key' => 'icand_notif_1',
            'incident_type' => 'candidate.single_signal',
            'title' => 'حادث عالي',
            'summary' => 'summary',
            'why_summary' => 'why',
            'severity' => 'high',
            'confidence' => 0.91,
            'status' => 'open',
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'none',
            'affected_scope' => 'tenant:'.$company->id,
            'affected_entities' => [],
            'affected_companies' => [$company->id],
            'source_signals' => ['sig_1'],
            'recommended_actions' => [],
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
            'last_status_change_at' => now(),
        ]);

        PlatformDecisionLogEntry::query()->create([
            'decision_id' => (string) Str::uuid(),
            'incident_key' => 'icand_notif_1',
            'decision_type' => 'escalate',
            'decision_summary' => 'قرار يحتاج متابعة',
            'rationale' => 'rationale',
            'actor_user_id' => $platform->id,
            'linked_signals' => ['sig_1'],
            'linked_notes' => [],
            'expected_outcome' => 'outcome',
            'evidence_refs' => [],
            'follow_up_required' => true,
        ]);

        PlatformControlledAction::query()->create([
            'action_id' => (string) Str::uuid(),
            'incident_key' => 'icand_notif_1',
            'action_type' => 'follow_up',
            'action_summary' => 'متابعة مجدولة',
            'actor_user_id' => $platform->id,
            'status' => 'scheduled',
            'follow_up_required' => true,
            'scheduled_for' => now()->addHour(),
        ]);

        $res = $this->actingAsUser($platform)->getJson('/api/v1/platform/notifications');
        $res->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'notification_id',
                    'notification_type',
                    'title',
                    'summary',
                    'priority',
                    'status',
                    'created_at',
                    'is_read',
                    'target_type',
                    'target_id',
                    'target_route',
                    'target_params',
                    'cta_label',
                    'group_key',
                    'requires_action',
                ]],
                'meta' => ['total', 'unread_count', 'requires_action_count', 'attention_now'],
            ]);

        $rows = $res->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertNotSame('', (string) ($row['target_route'] ?? ''));
            $this->assertNotSame('', (string) ($row['target_id'] ?? ''));
            $this->assertMatchesRegularExpression('/^\/(platform|admin)\//', (string) ($row['target_route'] ?? ''));
        }
    }

    public function test_role_aware_visibility_excludes_unpermitted_categories(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('notif-fin@platform.test', [
            'platform_role' => 'finance_admin',
        ]);
        $finance = User::where('email', 'notif-fin@platform.test')->firstOrFail();
        $tenant = $this->createTenant('owner');

        RegistrationProfile::query()->updateOrCreate(
            ['user_id' => $tenant['user']->id],
            [
                'status' => 'pending_review',
                'company_activation_status' => 'pending_review',
                'company_name' => 'شركة لا يجب أن تظهر',
                'submitted_at' => now(),
            ],
        );

        $res = $this->actingAsUser($finance)->getJson('/api/v1/platform/notifications');
        $res->assertOk();
        $types = array_map(
            static fn (array $x): string => (string) ($x['notification_type'] ?? ''),
            $res->json('data') ?? []
        );
        $this->assertNotContains('approval', $types, 'finance_admin must not see registration approval notifications');
    }

    public function test_filters_support_requires_action_and_category(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('notif-filter@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $platform = User::where('email', 'notif-filter@platform.test')->firstOrFail();
        $tenant = $this->createTenant('owner');
        $company = Company::query()->findOrFail($tenant['company']->id);

        SupportTicket::query()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'TKT-TEST-0002',
            'company_id' => $company->id,
            'created_by' => $tenant['user']->id,
            'subject' => 'فلتر الدعم',
            'description' => 'desc',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $this->actingAsUser($platform)
            ->getJson('/api/v1/platform/notifications?category=support')
            ->assertOk()
            ->assertJsonPath('data.0.notification_type', 'support');

        $this->actingAsUser($platform)
            ->getJson('/api/v1/platform/notifications?requires_action=true')
            ->assertOk()
            ->assertJsonPath('data.0.requires_action', true);
    }
}

