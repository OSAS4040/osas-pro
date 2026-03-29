<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('zatca-test')->plainTextToken;

$ch = curl_init('http://saas_nginx/api/v1/zatca/status');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
    CURLOPT_HTTPHEADER=>["Authorization: Bearer $token","Accept: application/json"]]);
$body = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
echo "ZATCA Status: $status\n$body\n\n";

$ch2 = curl_init('http://saas_nginx/api/v1/notifications');
curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
    CURLOPT_HTTPHEADER=>["Authorization: Bearer $token","Accept: application/json"]]);
$body2 = curl_exec($ch2); $s2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE); curl_close($ch2);
echo "Notifications: $s2\n$body2\n\n";

$ch3 = curl_init('http://saas_nginx/api/v1/customer-portal/dashboard');
curl_setopt_array($ch3, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
    CURLOPT_HTTPHEADER=>["Authorization: Bearer $token","Accept: application/json"]]);
$body3 = curl_exec($ch3); $s3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE); curl_close($ch3);
echo "Customer Portal: $s3\n$body3\n";
