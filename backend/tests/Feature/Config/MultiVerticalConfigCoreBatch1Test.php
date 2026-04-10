<?php

namespace Tests\Feature\Config;

use App\Models\ConfigSetting;
use App\Services\Config\ConfigResolverService;
use Tests\TestCase;

class MultiVerticalConfigCoreBatch1Test extends TestCase
{
    public function test_resolves_config_by_scope_precedence(): void
    {
        ConfigSetting::query()->insert([
            [
                'scope_type' => 'system',
                'scope_key' => 'system',
                'config_key' => 'bookings.enabled',
                'config_value' => 'false',
                'value_type' => 'boolean',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope_type' => 'plan',
                'scope_key' => 'professional',
                'config_key' => 'bookings.enabled',
                'config_value' => 'true',
                'value_type' => 'boolean',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope_type' => 'vertical',
                'scope_key' => 'service_workshop',
                'config_key' => 'bookings.enabled',
                'config_value' => 'false',
                'value_type' => 'boolean',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope_type' => 'company',
                'scope_key' => '10',
                'config_key' => 'bookings.enabled',
                'config_value' => 'true',
                'value_type' => 'boolean',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'scope_type' => 'branch',
                'scope_key' => '100',
                'config_key' => 'bookings.enabled',
                'config_value' => 'false',
                'value_type' => 'boolean',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $resolved = app(ConfigResolverService::class)->resolve('bookings.enabled', [
            'plan' => 'professional',
            'vertical' => 'service_workshop',
            'company_id' => 10,
            'branch_id' => 100,
        ]);

        $this->assertFalse((bool) $resolved);
    }

    public function test_vertical_profiles_seeded(): void
    {
        $this->seed(\Database\Seeders\VerticalProfilesSeeder::class);
        $this->assertDatabaseHas('vertical_profiles', ['code' => 'service_workshop']);
        $this->assertDatabaseHas('vertical_profiles', ['code' => 'fleet_operations']);
        $this->assertDatabaseHas('vertical_profiles', ['code' => 'retail_pos']);
    }
}
