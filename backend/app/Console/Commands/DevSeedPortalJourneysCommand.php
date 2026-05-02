<?php

namespace App\Console\Commands;

use Database\Seeders\DemoIntegratedPortalJourneySeeder;
use Illuminate\Console\Command;

/**
 * يكمّل الرحلة التشغيلية للبوابات عند وجود حسابات الديمو (بعد workshop:seed-demo).
 */
final class DevSeedPortalJourneysCommand extends Command
{
    protected $signature = 'dev:seed-portal-journeys';

    protected $description = 'Seed integrated portal journeys (catalog, prepaid wallets, work orders) for Demo Auto Center — requires workshop:seed-demo';

    public function handle(): int
    {
        if (! $this->laravel->environment('local')) {
            $this->warn('dev:seed-portal-journeys skipped (APP_ENV is not local).');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => DemoIntegratedPortalJourneySeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
