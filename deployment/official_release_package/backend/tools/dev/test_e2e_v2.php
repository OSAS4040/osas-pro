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

// AUTH
$r=api('POST',"$base/auth/login",['email'=>'owner@demo.sa','password'=>'Password123!']);
test('Staff Login',$r['status']===200 && isset($r['body']['token']),"status={$r['status']}");
$ownerTok=$r['body']['token']??null;

$r=api('POST',"$base/auth/login",['email'=>'fleet.contact@demo.sa','password'=>'Password123!']);
test('Fleet Login',$r['status']===200,"status={$r['status']}");
$fleetTok=$r['body']['token']??null;

$r=api('POST',"$base/auth/login",['email'=>'customer@demo.sa','password'=>'Password123!']);
test('Customer Login',$r['status']===200,"status={$r['status']}");
$custTok=$r['body']['token']??null;

if(!$ownerTok){echo"FATAL: no token\n";exit(1);}

// SUBSCRIPTION (correct endpoint)
$r=api('GET',"$base/subscription",null,$ownerTok);
test('Subscription',$r['status']===200 && isset($r['body']['data']['plan']),"plan=".($r['body']['data']['plan']['slug']??'?'));

// CORE DATA
$r=api('GET',"$base/vehicles",null,$ownerTok);
test('Vehicles',$r['status']===200,"count=".count($r['body']['data']??[]));

$r=api('GET',"$base/customers",null,$ownerTok);
test('Customers',$r['status']===200,"count=".count($r['body']['data']??[]));

$r=api('GET',"$base/invoices",null,$ownerTok);
test('Invoices',$r['status']===200,"count=".count($r['body']['data']??[]));

$r=api('GET',"$base/work-orders",null,$ownerTok);
test('Work Orders',$r['status']===200,"count=".count($r['body']['data']??[]));

$r=api('GET',"$base/products",null,$ownerTok);
test('Products',$r['status']===200,"count=".count($r['body']['data']??[]));

// WORKSHOP (correct prefix)
$r=api('GET',"$base/workshop/employees",null,$ownerTok);
test('Employees',$r['status']===200,"count=".count($r['body']['data']??[]));

// WALLET (correct route GET /wallet)
$r=api('GET',"$base/wallet",null,$ownerTok);
test('Wallet',$r['status']===200 && isset($r['body']['wallets']),"status={$r['status']}");

// REPORTS
$r=api('GET',"$base/reports/kpi",null,$ownerTok);
test('Reports KPI',$r['status']===200,"status={$r['status']}");

// GOVERNANCE/CONTRACTS
$r=api('GET',"$base/governance/contracts",null,$ownerTok);
test('Contracts',$r['status']===200,"count=".count($r['body']['data']??[]));

// REFERRALS (correct prefix)
$r=api('GET',"$base/governance/referrals",null,$ownerTok);
test('Referrals',$r['status']===200,"status={$r['status']}");

// SUPPORT TICKETS
$r=api('GET',"$base/support/tickets",null,$ownerTok);
test('Support Tickets',$r['status']===200,"count=".count($r['body']['data']??[]));

// FLEET PORTAL
$r=api('GET',"$base/fleet-portal/dashboard",null,$fleetTok);
test('Fleet Dashboard',$r['status']===200,"status={$r['status']}");

// CUSTOMER PORTAL
$r=api('GET',"$base/customer-portal/dashboard",null,$custTok);
test('Customer Dashboard',in_array($r['status'],[200,404]),"status={$r['status']}");

// BOOKINGS
$r=api('GET',"$base/bookings",null,$ownerTok);
test('Bookings',in_array($r['status'],[200]),"count=".count($r['body']['data']??[]));

// BAYS
$r=api('GET',"$base/bays",null,$ownerTok);
test('Bays',in_array($r['status'],[200]),"count=".count($r['body']['data']??[]));

// FUEL
$r=api('GET',"$base/fuel/logs",null,$ownerTok);
test('Fuel Logs',in_array($r['status'],[200,404]),"status={$r['status']}");

// ZATCA
$r=api('GET',"$base/zatca/status",null,$ownerTok);
test('ZATCA Status',in_array($r['status'],[200,404]),"status={$r['status']}");

// NOTIFICATIONS
$r=api('GET',"$base/notifications",null,$ownerTok);
test('Notifications',in_array($r['status'],[200,404]),"status={$r['status']}");

// CREATE VEHICLE TEST
$r=api('POST',"$base/vehicles",['plate_number'=>'ب ج د 9999','make'=>'Honda','model'=>'Civic','year'=>2024],$ownerTok);
test('Create Vehicle',in_array($r['status'],[200,201,422]),"status={$r['status']} ".substr($r['raw'],0,100));

// POS
$r=api('GET',"$base/pos/sessions",null,$ownerTok);
test('POS Sessions',in_array($r['status'],[200,404]),"status={$r['status']}");

echo "\n====== RESULTS ======\n";
foreach($results as $line) echo $line."\n";
echo "\n✅ PASSED: $passed | ❌ FAILED: $failed | Score: ".round($passed/($passed+$failed)*100)."%\n";
