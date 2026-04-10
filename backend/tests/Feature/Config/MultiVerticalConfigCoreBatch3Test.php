<?php

namespace Tests\Feature\Config;

use App\Models\ConfigSetting;
use App\Models\VerticalProfile;
use Tests\TestCase;

class MultiVerticalConfigCoreBatch3Test extends TestCase
{
    public function test_vertical_fallback_applies_when_no_company_or_branch_override(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);
        $this->seedContext();

        $company->update(['vertical_profile_code' => 'service_workshop']);
        $this->setConfig('vertical', 'service_workshop', 'bookings.enabled', false);

        $response = $this->getJson("/api/v1/branches/{$branch->id}/effective-config");
        $response->assertOk();
        $config = $response->json('data.config');
        $this->assertFalse($config['bookings.enabled']['value']);
    }

    public function test_company_override_wins_over_vertical(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);
        $this->seedContext();

        $company->update(['vertical_profile_code' => 'service_workshop']);
        $this->setConfig('vertical', 'service_workshop', 'quotes.enabled', false);
        $this->setConfig('company', (string) $company->id, 'quotes.enabled', true);

        $response = $this->getJson("/api/v1/companies/{$company->id}/effective-config");
        $response->assertOk();
        $config = $response->json('data.config');
        $this->assertTrue($config['quotes.enabled']['value']);
    }

    public function test_branch_override_wins_over_company(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);
        $this->seedContext();

        $company->update(['vertical_profile_code' => 'service_workshop']);
        $this->setConfig('vertical', 'service_workshop', 'bookings.enabled', true);
        $this->setConfig('company', (string) $company->id, 'bookings.enabled', true);
        $this->setConfig('branch', (string) $branch->id, 'bookings.enabled', false);

        $response = $this->getJson("/api/v1/branches/{$branch->id}/effective-config");
        $response->assertOk();
        $config = $response->json('data.config');
        $this->assertFalse($config['bookings.enabled']['value']);
    }

    public function test_profile_assignment_changes_live_behavior(): void
    {
        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->actingAsUser($user);
        $this->seedContext();

        $this->setConfig('vertical', 'service_workshop', 'bookings.enabled', false);
        $this->setConfig('system', 'system', 'bookings.enabled', true);

        $this->patchJson("/api/v1/companies/{$company->id}/vertical-profile", [
            'vertical_profile_code' => 'service_workshop',
        ])->assertOk();

        $response = $this->postJson('/api/v1/bookings', []);
        $response->assertStatus(403)->assertJsonFragment(['message' => 'Bookings are disabled by configuration.']);
    }

    private function seedContext(): void
    {
        VerticalProfile::query()->updateOrCreate(
            ['code' => 'service_workshop'],
            ['name' => 'Service Workshop', 'is_active' => true]
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

