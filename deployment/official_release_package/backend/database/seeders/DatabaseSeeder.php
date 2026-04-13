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
            DefaultAdminSeeder::class,
            IntelligenceSystemSeeder::class,
            DemoOperationsSeeder::class,
            PluginsCatalogSeeder::class,
        ]);
    }
}
