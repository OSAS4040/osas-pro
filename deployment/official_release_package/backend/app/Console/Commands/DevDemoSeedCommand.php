<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Runs demo seeders only in local development.
 * Staging/production stacks keep migrate via dev-bootstrap but skip seeding.
 */
class DevDemoSeedCommand extends Command
{
    protected $signature = 'dev:demo-seed';

    protected $description = 'When APP_ENV=local, run workshop:seed-demo (idempotent). Skipped otherwise.';

    public function handle(): int
    {
        if (! $this->laravel->environment('local')) {
            $this->info('dev:demo-seed skipped (APP_ENV is not local).');

            return self::SUCCESS;
        }

        return (int) $this->call('workshop:seed-demo');
    }
}
