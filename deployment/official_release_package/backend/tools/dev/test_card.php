<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('card-test')->plainTextToken;
$v = App\Models\Vehicle::first();
if(!$v) { echo "No vehicles\n"; exit; }
$ch = curl_init("http://saas_nginx/api/v1/vehicles/{$v->id}/digital-card");
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Accept: application/json","Authorization: Bearer $token"]]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
echo "Status: $code\n";
$data = json_decode($body, true);
if ($code === 200) {
    $d = $data['data'];
    echo "Vehicle: {$d['make']} {$d['model']}\n";
    echo "Work Orders Count: {$d['work_orders_count']}\n";
    echo "Wallet Balance: {$d['wallet_balance']}\n";
    echo "Loyalty Points: {$d['loyalty_points']}\n";
    echo "Total Spent: {$d['total_spent']}\n";
    echo "Recent WOs: " . count($data['work_orders'] ?? []) . "\n";
    echo "Transactions: " . count($data['transactions'] ?? []) . "\n";
    echo "\n✅ البطاقة الرقمية تعمل بنجاح!\n";
} else {
    echo substr($body, 0, 300) . "\n";
}
