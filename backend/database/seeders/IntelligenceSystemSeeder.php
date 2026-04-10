<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Single entry point for demo operational data + intelligence telemetry (no raw require).
 * Order: customers/products/vehicles/work orders/invoices (DemoDataSeeder), then payments + domain_events.
 */
class IntelligenceSystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoDataSeeder::class,
            IntelligenceTelemetrySeeder::class,
        ]);
    }
}
