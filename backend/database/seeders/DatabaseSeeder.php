<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            VerticalProfilesSeeder::class,
            ConfigSettingsSeeder::class,
            RolePermissionSeeder::class,
            DemoCompanySeeder::class,
            DemoDataSeeder::class,
            DefaultAdminSeeder::class,
            DemoPlatformAdminSeeder::class,
            IntelligenceSystemSeeder::class,
            PlatformShowcaseTenantsSeeder::class,
            DemoOperationsSeeder::class,
            DemoEndToEndScenarioSeeder::class,
            IntelligenceTelemetrySeeder::class,
            PluginsCatalogSeeder::class,
        ]);
    }
}
