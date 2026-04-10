<?php

namespace Tests\Feature\Config;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\ConfigSetting;
use App\Models\Subscription;
use App\Models\VerticalProfile;
use Tests\TestCase;

class MultiVerticalGovernanceEnablementTest extends TestCase
{
    public function test_company_assignment_and_reassignment_are_audited(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        $this->seedProfiles();

        $this->patchJson("/api/v1/companies/{$company->id}/vertical-profile", [
            'vertical_profile_code' => 'service_workshop',
        ])->assertOk();

        $this->patchJson("/api/v1/companies/{$company->id}/vertical-profile", [
            'vertical_profile_code' => 'fleet_operations',
            'reason' => 'Move company to fleet governance profile',
        ])->assertOk();

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'vertical_profile_code' => 'fleet_operations']);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'subject_type' => Company::class,
            'subject_id' => $company->id,
            'action' => 'vertical_profile.assigned.company',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'subject_type' => Company::class,
            'subject_id' => $company->id,
            'action' => 'vertical_profile.reassigned.company',
            'user_id' => $user->id,
        ]);
    }

    public function test_branch_assignment_unassignment_and_reason_policy(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        $this->seedProfiles();

        $this->patchJson("/api/v1/branches/{$branch->id}/vertical-profile", [
            'vertical_profile_code' => 'service_workshop',
        ])->assertOk();

        $this->patchJson("/api/v1/branches/{$branch->id}/vertical-profile", [
            'vertical_profile_code' => null,
        ])->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->patchJson("/api/v1/branches/{$branch->id}/vertical-profile", [
            'vertical_profile_code' => null,
            'reason' => 'Inherit from company only',
        ])->assertOk();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'vertical_profile_code' => null]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'subject_type' => Branch::class,
            'subject_id' => $branch->id,
            'action' => 'vertical_profile.unassigned.branch',
            'user_id' => $user->id,
        ]);
    }

    public function test_invalid_assignment_is_rejected(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        VerticalProfile::query()->updateOrCreate(
            ['code' => 'inactive_vertical'],
            ['name' => 'Inactive Vertical', 'is_active' => false]
        );

        $this->patchJson("/api/v1/companies/{$company->id}/vertical-profile", [
            'vertical_profile_code' => 'inactive_vertical',
        ])->assertStatus(422)->assertJsonValidationErrors(['vertical_profile_code']);
    }

    public function test_authorization_failure_for_assignment_and_view(): void
    {
        ['company' => $company, 'branch' => $branch] = $this->createTenant();
        $user = $this->createUser($company, $branch, 'cashier', [
            'role' => UserRole::Cashier,
        ]);
        $this->actingAsUser($user);
        $this->seedProfiles();

        $this->patchJson("/api/v1/companies/{$company->id}/vertical-profile", [
            'vertical_profile_code' => 'service_workshop',
        ])->assertStatus(403);

        $this->getJson("/api/v1/companies/{$company->id}/effective-config")->assertStatus(403);
    }

    public function test_resolved_config_visibility_with_sources_and_audit(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant('manager');
        $this->actingAsUser($user);
        $this->seedProfiles();

        Subscription::query()->where('company_id', $company->id)->update(['plan' => 'professional']);
        $company->update(['vertical_profile_code' => 'service_workshop']);
        $branch->update(['vertical_profile_code' => 'service_workshop']);

        $this->setConfig('system', 'system', 'wallet.enabled', false);
        $this->setConfig('company', (string) $company->id, 'quotes.enabled', true);
        $this->setConfig('branch', (string) $branch->id, 'bookings.enabled', false);

        $response = $this->getJson("/api/v1/branches/{$branch->id}/effective-config");
        $response->assertOk();
        $config = $response->json('data.config');
        $this->assertSame('company_override', $config['quotes.enabled']['source']);
        $this->assertSame('branch_override', $config['bookings.enabled']['source']);
        $this->assertSame('default', $config['wallet.enabled']['source']);
        $this->assertTrue($config['quotes.enabled']['value']);
        $this->assertFalse($config['bookings.enabled']['value']);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'action' => 'vertical_profile.resolution_check.branch',
            'user_id' => $user->id,
        ]);
    }

    private function seedProfiles(): void
    {
        VerticalProfile::query()->updateOrCreate(
            ['code' => 'service_workshop'],
            ['name' => 'Service Workshop', 'is_active' => true]
        );
        VerticalProfile::query()->updateOrCreate(
            ['code' => 'fleet_operations'],
            ['name' => 'Fleet Operations', 'is_active' => true]
        );
    }

    private function setConfig(string $scopeType, string $scopeKey, string $configKey, bool $value): void
    {
        ConfigSetting::query()->updateOrCreate(
            ['scope_type' => $scopeType, 'scope_key' => $scopeKey, 'config_key' => $configKey],
            ['config_value' => $value ? 'true' : 'false', 'value_type' => 'boolean', 'is_active' => true]
        );
    }
}

