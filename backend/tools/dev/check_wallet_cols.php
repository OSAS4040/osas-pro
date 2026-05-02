<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$cols = Schema::getColumnListing('customer_wallets');
echo 'customer_wallets columns: '.implode(', ', $cols)."\n";
