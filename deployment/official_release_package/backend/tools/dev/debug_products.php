<?php
$base = 'http://saas_nginx/api/v1';
function req($m,$u,$b=[],$h=[]){$ch=curl_init($u);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($m),CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$h),CURLOPT_TIMEOUT=>15]);if($b)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($b));$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return['code'=>$c,'body'=>json_decode($r,true)??[],'raw'=>$r];}
$r=req('POST',"$base/auth/login",['email'=>'owner@demo.sa','password'=>'Password123!']);
$token=$r['body']['token']??$r['body']['data']['token']??'';
$auth=["Authorization: Bearer $token"];

echo "=== Products raw response ===\n";
$r=req('GET',"$base/products",[],$auth);
echo "HTTP: {$r['code']}\n";
echo "total: " . ($r['body']['meta']['total'] ?? 'N/A') . "\n";
echo "count in data: " . count($r['body']['data']??[]) . "\n";
echo substr($r['raw'],0,400) . "\n\n";

echo "=== Products active only ===\n";
$r=req('GET',"$base/products?is_active=1",[],$auth);
echo "HTTP: {$r['code']}, count: " . count($r['body']['data']??[]) . "\n\n";

echo "=== Users/me ===\n";
$r=req('GET',"$base/auth/user",[],$auth);
echo "branch_id: " . ($r['body']['data']['branch_id']??$r['body']['branch_id']??'none') . "\n";
echo "company_id: " . ($r['body']['data']['company_id']??$r['body']['company_id']??'none') . "\n";
