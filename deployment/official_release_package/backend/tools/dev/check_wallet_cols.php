<?php
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cols = \Illuminate\Support\Facades\Schema::getColumnListing('customer_wallets');
echo "customer_wallets columns: " . implode(', ', $cols) . "\n";
