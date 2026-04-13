<?php
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $ctrl = new \App\Http\Controllers\Api\V1\GovernanceController();
    echo "GovernanceController: OK\n";
} catch (\Throwable $e) {
    echo "GovernanceController ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

try {
    $svc = new \App\Services\PolicyEngine();
    echo "PolicyEngine: OK\n";
} catch (\Throwable $e) {
    echo "PolicyEngine ERROR: " . $e->getMessage() . "\n";
}

try {
    $m = new \App\Models\PolicyRule();
    echo "PolicyRule: OK\n";
} catch (\Throwable $e) {
    echo "PolicyRule ERROR: " . $e->getMessage() . "\n";
}
