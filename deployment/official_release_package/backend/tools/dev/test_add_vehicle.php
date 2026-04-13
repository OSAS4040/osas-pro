<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('vehicle-create-test')->plainTextToken;
$plate = 'ت ع م ' . rand(1000,9999);
$ch = curl_init("http://saas_nginx/api/v1/vehicles");
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode(['plate_number'=>$plate,'make'=>'Hyundai','model'=>'Sonata','year'=>2024]),
    CURLOPT_HTTPHEADER=>["Accept: application/json","Content-Type: application/json","Authorization: Bearer $token"]
]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
echo "Status: $code\n";
if($code===201){echo "✅ Vehicle created: $plate\n";}
else{echo "❌ ".substr($body,0,200)."\n";}
