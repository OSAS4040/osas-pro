<?php
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';

// Check route registration
$router = $app->make('router');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$routes = collect($router->getRoutes())->filter(function($r) {
    return str_contains($r->uri(), 'governance');
})->map(fn($r) => $r->methods()[0] . ' ' . $r->uri());

echo "=== Governance Routes ===\n";
foreach ($routes as $r) echo $r . "\n";
echo "\nTotal governance routes: " . count($routes) . "\n";

// Try to instantiate ContractController
try {
    $c = $app->make(\App\Http\Controllers\Api\V1\ContractController::class);
    echo "\nContractController: OK\n";
} catch (\Throwable $e) {
    echo "\nContractController Error: " . $e->getMessage() . "\n";
}

// Try to instantiate ImportController
try {
    $c = $app->make(\App\Http\Controllers\Api\V1\ImportController::class);
    echo "ImportController: OK\n";
} catch (\Throwable $e) {
    echo "ImportController Error: " . $e->getMessage() . "\n";
}
