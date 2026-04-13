<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('fuel-test')->plainTextToken;
$ch = curl_init("http://saas_nginx/api/v1/governance/fuel");
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Accept: application/json","Authorization: Bearer $token"]]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
echo "Status: $code\n";
echo substr($body,0,200)."\n";
