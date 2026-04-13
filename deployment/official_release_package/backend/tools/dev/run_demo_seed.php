<?php
// Run via: docker exec saas_app php /var/www/run_demo_seed.php

chdir('/var/www');
require '/var/www/vendor/autoload.php';

$app = require_once '/var/www/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
Artisan::call('db:seed', ['--class' => 'DemoDataSeeder', '--force' => true]);
echo Artisan::output();
echo "Done!\n";
