<?php
// Direct seed script - no artisan needed
define('LARAVEL_START', microtime(true));

$_SERVER['argv'] = ['artisan'];
$_SERVER['argc'] = 1;

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Now run the seeder
$seeder = new Database\Seeders\DemoDataSeeder();
$seeder->setContainer($app)->setCommand(new class {
    public function info($msg) { echo "INFO: $msg\n"; }
    public function error($msg) { echo "ERROR: $msg\n"; }
})->run();

echo "\nSeeding complete!\n";
