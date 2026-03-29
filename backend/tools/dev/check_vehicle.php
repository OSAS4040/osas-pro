<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$v = App\Models\Vehicle::with(['customer','workOrders'])->first();
if(!$v) { echo "No vehicles found\n"; exit; }
echo "Vehicle ID: {$v->id}\n";
echo "Has wallet_balance attr: " . (array_key_exists('wallet_balance', $v->getAttributes()) ? 'yes' : 'no') . "\n";
echo "Work orders count: " . $v->workOrders->count() . "\n";
echo "Keys: " . implode(', ', array_keys($v->toArray())) . "\n";
