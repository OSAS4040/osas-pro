<?php
$base = 'http://saas_nginx/api/v1';
$passed = 0; $failed = 0; $results = [];

function test($name, $ok, $detail='') {
    global $passed,$failed,$results;
    if($ok){ $results[]="✅ $name" . ($detail?" [$detail]":''); $passed++; }
    else    { $results[]="❌ $name" . ($detail?" [$detail]":''); $failed++; }
}
function api($m,$url,$data=null,$tok=null){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CUSTOMREQUEST=>$m,
        CURLOPT_HTTPHEADER=>array_filter(['Content-Type: application/json','Accept: application/json',$tok?"Authorization: Bearer $tok":null])]);
    if($data) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));
    $body=curl_exec($ch); $s=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return['status'=>$s,'body'=>json_decode($body,true),'raw'=>$body];
}

// Auth
$r=api('POST',"$base/auth/login",['email'=>'owner@demo.sa','password'=>'Password123!']);
test('Staff Login',$r['status']===200);
$ot=$r['body']['token']??null;

$r=api('POST',"$base/auth/login",['email'=>'fleet.contact@demo.sa','password'=>'Password123!']);
test('Fleet Login',$r['status']===200);
$ft=$r['body']['token']??null;

$r=api('POST',"$base/auth/login",['email'=>'customer@demo.sa','password'=>'Password123!']);
test('Customer Login',$r['status']===200);

if(!$ot){die("FATAL: no token\n");}

// Core
$r=api('GET',"$base/subscription",null,$ot);
test('Subscription',$r['status']===200,"plan=".($r['body']['data']['plan']['slug']??'?'));
test('Vehicles',api('GET',"$base/vehicles",null,$ot)['status']===200);
test('Customers',api('GET',"$base/customers",null,$ot)['status']===200);
test('Invoices',api('GET',"$base/invoices",null,$ot)['status']===200);
test('Work Orders',api('GET',"$base/work-orders",null,$ot)['status']===200);
test('Products',api('GET',"$base/products",null,$ot)['status']===200);
test('Employees',api('GET',"$base/workshop/employees",null,$ot)['status']===200);
test('Wallet',api('GET',"$base/wallet",null,$ot)['status']===200);
test('Reports KPI',api('GET',"$base/reports/kpi",null,$ot)['status']===200);
test('Contracts',api('GET',"$base/governance/contracts",null,$ot)['status']===200);
test('Referrals',api('GET',"$base/governance/referrals",null,$ot)['status']===200);
test('Support Tickets',api('GET',"$base/support/tickets",null,$ot)['status']===200);
test('Fleet Dashboard',api('GET',"$base/fleet-portal/dashboard",null,$ft)['status']===200);
test('Customer Portal',api('GET',"$base/customer-portal/dashboard",null,$ot)['status']===200);
test('Bookings',api('GET',"$base/bookings",null,$ot)['status']===200);
test('Bays/Lifts',api('GET',"$base/bays",null,$ot)['status']===200);
test('Fuel',api('GET',$base."/governance/fuel",null,$ot)['status']===200);
test('ZATCA Status',api('GET',"$base/zatca/status",null,$ot)['status']===200);
test('Notifications',api('GET',"$base/notifications",null,$ot)['status']===200);
test('Governance',api('GET',"$base/governance/policies",null,$ot)['status']===200);
test('Suppliers',api('GET',"$base/suppliers",null,$ot)['status']===200);
test('Purchases',api('GET',"$base/purchases",null,$ot)['status']===200);
test('Services',api('GET',"$base/services",null,$ot)['status']===200);
test('Plans',api('GET',"$base/plans",null,$ot)['status']===200);

// Create operations
$plate='ص ط ي '.rand(1000,9999);
$r=api('POST',"$base/vehicles",['plate_number'=>$plate,'make'=>'Toyota','model'=>'Camry','year'=>2023],$ot);
test('Create Vehicle',in_array($r['status'],[200,201]),"status={$r['status']}");

echo "\n====== FINAL E2E RESULTS ======\n";
foreach($results as $l) echo $l."\n";
echo "\n✅ PASSED: $passed | ❌ FAILED: $failed | Score: ".round($passed/($passed+$failed)*100)."%\n";
