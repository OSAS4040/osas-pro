<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo implode(', ', \Illuminate\Support\Facades\Schema::getColumnListing('invoices'));
