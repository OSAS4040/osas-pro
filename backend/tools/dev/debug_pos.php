<?php
$base = 'http://saas_nginx/api/v1';
function req($m,$u,$b=[],$h=[]){$ch=curl_init($u);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($m),CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$h),CURLOPT_TIMEOUT=>15]);if($b)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($b));$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return['code'=>$c,'body'=>json_decode($r,true)??[],'raw'=>$r];}
$r=req('POST',"$base/auth/login",['email'=>'owner@demo.sa','password'=>'Password123!']);
$token=$r['body']['token']??$r['body']['data']['token']??'';
$auth=["Authorization: Bearer $token"];

// Get any service product
$r=req('GET',"$base/products",[],$auth);
$items=$r['body']['data']['data']??$r['body']['data']??[];
$product=null;
foreach($items as $p){if(($p['product_type']??'')!=='physical'){$product=$p;break;}}
echo "Product: {$product['id']} {$product['name']} track={$product['track_inventory']}\n";

$r=req('GET',"$base/customers",[],$auth);
$cust=($r['body']['data']['data']??$r['body']['data']??[])[0]??null;
echo "Customer: {$cust['id']} {$cust['name']}\n";

// POS
$posData=['customer_id'=>$cust['id'],'payment'=>['method'=>'cash','amount'=>(float)$product['sale_price']],
'items'=>[['product_id'=>$product['id'],'name'=>$product['name'],'quantity'=>1,'unit_price'=>(float)$product['sale_price'],'tax_rate'=>15]]];
$r=req('POST',"$base/pos/sale",$posData,array_merge($auth,["Idempotency-Key: pos-debug-".time()]));
echo "POS HTTP: {$r['code']}\n";
echo $r['raw']."\n";
