<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$user = App\Models\User::where('email','owner@demo.sa')->first();
$token = $user->createToken('plugin-test')->plainTextToken;

function api($m,$url,$data=null,$tok=null){
    $ch=curl_init($url); 
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CUSTOMREQUEST=>$m,
        CURLOPT_HTTPHEADER=>array_filter(['Content-Type: application/json','Accept: application/json',$tok?"Authorization: Bearer $tok":null])]);
    if($data) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));
    $body=curl_exec($ch); $s=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return['status'=>$s,'body'=>json_decode($body,true)];
}

$base = 'http://saas_nginx/api/v1';
$passed=0; $failed=0;

$r = api('GET',"$base/plugins",null,$token);
$count = count($r['body']['data'] ?? []);
if($r['status']===200 && $count > 0) { echo "✅ GET /plugins [$count plugins]\n"; $passed++; }
else { echo "❌ GET /plugins [{$r['status']}]\n"; $failed++; }

$r = api('POST',"$base/plugins/ai_advanced_diagnostics/install",null,$token);
if(in_array($r['status'],[200,201])) { echo "✅ Install plugin\n"; $passed++; }
else { echo "❌ Install plugin [{$r['status']}]: " . json_encode($r['body']) . "\n"; $failed++; }

$r = api('POST',"$base/plugins/ai_advanced_diagnostics/execute",['context'=>['make'=>'Toyota','model'=>'Camry','symptoms'=>['noise']]],$token);
if($r['status']===200 && isset($r['body']['data']['diagnostics'])) { echo "✅ Execute plugin AI diagnostics\n"; $passed++; }
else { echo "❌ Execute plugin [{$r['status']}]\n"; $failed++; }

$r = api('GET',"$base/plugins/tenant",null,$token);
if($r['status']===200) { echo "✅ GET /plugins/tenant [count=".count($r['body']['data']??[])."]\n"; $passed++; }
else { echo "❌ GET /plugins/tenant [{$r['status']}]\n"; $failed++; }

$r = api('DELETE',"$base/plugins/ai_advanced_diagnostics/uninstall",null,$token);
if(in_array($r['status'],[200,204])) { echo "✅ Uninstall plugin\n"; $passed++; }
else { echo "❌ Uninstall plugin [{$r['status']}]\n"; $failed++; }

echo "\n✅ PASSED: $passed | ❌ FAILED: $failed\n";
