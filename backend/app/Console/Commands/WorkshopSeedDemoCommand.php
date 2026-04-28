<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs core demo seeders without resolving them via the container (avoids
 * BindingResolutionException when Composer autoload inside Docker is stale/wrong).
 */
class WorkshopSeedDemoCommand extends Command
{
    protected $signature = 'workshop:seed-demo';

    protected $description = 'Seed DemoCompanySeeder + DefaultAdminSeeder + DemoPlatformAdminSeeder (incl. /platform/login demo)';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('workshop:seed-demo is disabled in production.');

            return self::FAILURE;
        }

        require_once database_path('seeders/DemoCompanySeeder.php');
        require_once database_path('seeders/DefaultAdminSeeder.php');
        require_once database_path('seeders/DemoPlatformAdminSeeder.php');

        Model::unguarded(function () {
            $demo = new \Database\Seeders\DemoCompanySeeder;
            $demo->setContainer($this->laravel)->setCommand($this);
            $demo->__invoke();

            $admin = new \Database\Seeders\DefaultAdminSeeder;
            $admin->setContainer($this->laravel)->setCommand($this);
            $admin->__invoke();

            $platformDemo = new \Database\Seeders\DemoPlatformAdminSeeder;
            $platformDemo->setContainer($this->laravel)->setCommand($this);
            $platformDemo->__invoke();
        });

        $this->info('Demo seeders finished. Staff: owner@demo.sa / password or admin@osas.sa / 12345678 — platform: platform-demo@osas.sa / 12345678 at /platform/login');

        return self::SUCCESS;
    }
}
