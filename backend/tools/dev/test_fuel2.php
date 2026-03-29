<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('fuel-test2')->plainTextToken;
// Simulate exactly what test_final does
function api($m,$url,$data=null,$tok=null){
    $ch=curl_init($url); 
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CUSTOMREQUEST=>$m,
        CURLOPT_HTTPHEADER=>array_filter(['Content-Type: application/json','Accept: application/json',$tok?"Authorization: Bearer $tok":null])]);
    if($data) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));
    $body=curl_exec($ch); $s=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return['status'=>$s,'body'=>json_decode($body,true)];
}
$base = 'http://saas_nginx/api/v1';
$r = api('GET',"$base/governance/fuel",null,$token);
echo "Status: {$r['status']}\n";
echo json_encode($r['body']??'null')."\n";
