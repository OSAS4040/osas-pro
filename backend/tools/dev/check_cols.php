<?php

use Illuminate\Support\Facades\Schema;

require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo implode(', ', Schema::getColumnListing('work_orders'));
