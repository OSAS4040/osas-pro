<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $seedDemoData = app()->environment(['local', 'testing'])
            || filter_var((string) env('APP_DEMO_DATA_ENABLED', ''), FILTER_VALIDATE_BOOLEAN);

        $this->call([
            PlanSeeder::class,
            VerticalProfilesSeeder::class,
            ConfigSettingsSeeder::class,
            RolePermissionSeeder::class,
            PluginsCatalogSeeder::class,
        ]);

        if (! $seedDemoData) {
            $this->command?->warn('Demo seeders skipped. Set APP_DEMO_DATA_ENABLED=true to run them explicitly.');

            return;
        }

        $this->call([
            DemoCompanySeeder::class,
            DemoDataSeeder::class,
            DefaultAdminSeeder::class,
            DemoPlatformAdminSeeder::class,
            IntelligenceSystemSeeder::class,
            PlatformShowcaseTenantsSeeder::class,
            DemoOperationsSeeder::class,
            DemoEndToEndScenarioSeeder::class,
            IntelligenceTelemetrySeeder::class,
        ]);
    }
}
