<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\PlatformIncident;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformIncidentCenterApiTest extends TestCase
{
    public function test_incidents_list_requires_read_permission(): void
    {
        Config::set('platform.admin_enabled', true);
        $blocked = $this->createStandalonePlatformOperator('inc-no-perm@platform.test', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);
        $this->actingAsUser($blocked->fresh())
            ->getJson('/api/v1/platform/intelligence/incidents')
            ->assertForbidden();
    }

    public function test_full_lifecycle_happy_path_and_timeline(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['inc-lifecycle@platform.test']);
        $this->createStandalonePlatformOperator('inc-lifecycle@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'inc-lifecycle@platform.test')->firstOrFail();

        PlatformIncident::query()->create([
            'incident_key' => 'icand_lifecycle_demo',
            'incident_type' => 'candidate.single_signal',
            'title' => 'عنوان',
            'summary' => 'ملخص',
            'why_summary' => 'لماذا',
            'severity' => 'low',
            'confidence' => 0.75,
            'status' => 'open',
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'none',
            'affected_scope' => 'tenant:1',
            'affected_entities' => [],
            'affected_companies' => [1],
            'source_signals' => ['sig_x'],
            'recommended_actions' => ['راقب'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'acknowledged_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'last_status_change_at' => now(),
            'resolve_reason' => null,
            'close_reason' => null,
            'operator_notes' => null,
        ]);

        $auth = $this->actingAsUser($user);
        $key = 'icand_lifecycle_demo';

        $auth->postJson("/api/v1/platform/intelligence/incidents/{$key}/acknowledge")->assertOk();
        $auth->postJson("/api/v1/platform/intelligence/incidents/{$key}/move-under-review")->assertOk();
        $auth->postJson("/api/v1/platform/intelligence/incidents/{$key}/escalate", ['reason' => 'needs leadership'])->assertOk();
        $auth->postJson("/api/v1/platform/intelligence/incidents/{$key}/move-monitoring")->assertOk();
        $auth->postJson("/api/v1/platform/intelligence/incidents/{$key}/assign-owner", ['owner_ref' => 'user:'.$user->id])->assertOk();
        $auth->postJson("/api/v1/platform/intelligence/incidents/{$key}/resolve", ['reason' => 'تمت المعالجة التشغيلية'])->assertOk();
        $auth->postJson("/api/v1/platform/intelligence/incidents/{$key}/close", ['reason' => 'إغلاق بعد التحقق'])->assertOk();

        $show = $auth->getJson("/api/v1/platform/intelligence/incidents/{$key}");
        $show->assertSuccessful()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.close_reason', 'إغلاق بعد التحقق');

        $timeline = $show->json('timeline');
        $this->assertIsArray($timeline);
        $this->assertGreaterThanOrEqual(6, count($timeline));
    }

    public function test_invalid_transition_returns_422(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['inc-bad@platform.test']);
        $this->createStandalonePlatformOperator('inc-bad@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'inc-bad@platform.test')->firstOrFail();

        PlatformIncident::query()->create([
            'incident_key' => 'icand_bad',
            'incident_type' => 'candidate.single_signal',
            'title' => 'T',
            'summary' => 'S',
            'why_summary' => 'W',
            'severity' => 'low',
            'confidence' => 0.75,
            'status' => 'acknowledged',
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'none',
            'affected_scope' => 'tenant:1',
            'affected_entities' => [],
            'affected_companies' => [1],
            'source_signals' => ['sig_x'],
            'recommended_actions' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'acknowledged_at' => now(),
            'resolved_at' => null,
            'closed_at' => null,
            'last_status_change_at' => now(),
            'resolve_reason' => null,
            'close_reason' => null,
            'operator_notes' => null,
        ]);

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_bad/acknowledge')
            ->assertStatus(422)
            ->assertJsonPath('message', 'acknowledge_only_from_open');
    }

    public function test_resolve_requires_reason(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['inc-res@platform.test']);
        $this->createStandalonePlatformOperator('inc-res@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'inc-res@platform.test')->firstOrFail();

        PlatformIncident::query()->create([
            'incident_key' => 'icand_res',
            'incident_type' => 'candidate.single_signal',
            'title' => 'T',
            'summary' => 'S',
            'why_summary' => 'W',
            'severity' => 'low',
            'confidence' => 0.75,
            'status' => 'monitoring',
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'none',
            'affected_scope' => 'tenant:1',
            'affected_entities' => [],
            'affected_companies' => [1],
            'source_signals' => ['sig_x'],
            'recommended_actions' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'acknowledged_at' => now(),
            'resolved_at' => null,
            'closed_at' => null,
            'last_status_change_at' => now(),
            'resolve_reason' => null,
            'close_reason' => null,
            'operator_notes' => null,
        ]);

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_res/resolve', ['reason' => 'ab'])
            ->assertStatus(422);
    }
}
