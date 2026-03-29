<?php
$base = 'http://172.19.0.8/api/v1';
$pass = 0; $fail = 0; $errors = [];

function req($method, $url, $data=null, $token=null) {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer '.$token;
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER    => $headers,
        CURLOPT_RETURNTRANSFER=> 1,
        CURLOPT_TIMEOUT       => 10,
    ]);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code'=>$code, 'body'=>json_decode($body,true), 'raw'=>$body, 'err'=>$err];
}

function test($label, $result, $expectedCode, &$pass, &$fail, &$errors) {
    $ok = $result['code'] === $expectedCode;
    $status = $ok ? 'PASS' : 'FAIL';
    echo "[$status]  $label -- HTTP {$result['code']}\n";
    if (!$ok && $result['err']) echo "       curl_error: {$result['err']}\n";
    if ($ok) $pass++; else { $fail++; $errors[] = "$label: HTTP {$result['code']} -- ".substr($result['raw'],0,200); }
    return $ok;
}

echo "\n========================================\n";
echo "   E2E Final Test -- OSAS System\n";
echo "========================================\n\n";

// 1. Auth
echo "-- 1. Auth --\n";
$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'password']);
test('Login Owner', $r, 200, $pass, $fail, $errors);
$token = $r['body']['token'] ?? '';

$rf = req('POST', "$base/auth/login", ['email'=>'fleet.contact@demo.sa','password'=>'password']);
test('Login Fleet Contact', $rf, 200, $pass, $fail, $errors);
$fleetToken = $rf['body']['token'] ?? '';

$rc = req('POST', "$base/auth/login", ['email'=>'customer@demo.sa','password'=>'password']);
test('Login Customer', $rc, 200, $pass, $fail, $errors);
$customerToken = $rc['body']['token'] ?? '';

// 2. Core Data
echo "\n-- 2. Core Data --\n";
test('GET customers',    req('GET', "$base/customers",   null, $token), 200, $pass, $fail, $errors);
test('GET products',     req('GET', "$base/products",    null, $token), 200, $pass, $fail, $errors);
test('GET vehicles',     req('GET', "$base/vehicles",    null, $token), 200, $pass, $fail, $errors);
test('GET suppliers',    req('GET', "$base/suppliers",   null, $token), 200, $pass, $fail, $errors);
test('GET work-orders',  req('GET', "$base/work-orders", null, $token), 200, $pass, $fail, $errors);
test('GET invoices',     req('GET', "$base/invoices",    null, $token), 200, $pass, $fail, $errors);
test('GET inventory',    req('GET', "$base/inventory",   null, $token), 200, $pass, $fail, $errors);
test('GET purchases',    req('GET', "$base/purchases",   null, $token), 200, $pass, $fail, $errors);
test('GET quotes',       req('GET', "$base/quotes",      null, $token), 200, $pass, $fail, $errors);

// 3. Workshop
echo "\n-- 3. Workshop --\n";
test('GET workshop/employees',   req('GET', "$base/workshop/employees",   null, $token), 200, $pass, $fail, $errors);
test('GET workshop/tasks',       req('GET', "$base/workshop/tasks",       null, $token), 200, $pass, $fail, $errors);
test('GET workshop/commissions', req('GET', "$base/workshop/commissions", null, $token), 200, $pass, $fail, $errors);

// 4. Finance
echo "\n-- 4. Finance --\n";
test('GET ledger', req('GET', "$base/ledger", null, $token), 200, $pass, $fail, $errors);
test('GET wallet', req('GET', "$base/wallet", null, $token), 200, $pass, $fail, $errors);

// 5. Reports
echo "\n-- 5. Reports --\n";
$from = date('Y-01-01'); $to = date('Y-m-d');
test('GET reports/kpi',                  req('GET', "$base/reports/kpi?from=$from&to=$to",   null, $token), 200, $pass, $fail, $errors);
test('GET reports/sales',                req('GET', "$base/reports/sales?from=$from&to=$to", null, $token), 200, $pass, $fail, $errors);
test('GET reports/overdue-receivables',  req('GET', "$base/reports/overdue-receivables",     null, $token), 200, $pass, $fail, $errors);
test('GET reports/vat',                  req('GET', "$base/reports/vat?from=$from&to=$to",   null, $token), 200, $pass, $fail, $errors);
test('GET reports/inventory',            req('GET', "$base/reports/inventory",               null, $token), 200, $pass, $fail, $errors);

// 6. Fleet Portal
echo "\n-- 6. Fleet Portal --\n";
if ($fleetToken) {
    test('GET fleet-portal/dashboard',  req('GET', "$base/fleet-portal/dashboard",  null, $fleetToken), 200, $pass, $fail, $errors);
    test('GET fleet-portal/vehicles',   req('GET', "$base/fleet-portal/vehicles",   null, $fleetToken), 200, $pass, $fail, $errors);
    test('GET fleet-portal/work-orders',req('GET', "$base/fleet-portal/work-orders",null, $fleetToken), 200, $pass, $fail, $errors);
    test('GET fleet-portal/wallet',     req('GET', "$base/fleet-portal/wallet",     null, $fleetToken), 200, $pass, $fail, $errors);
} else {
    echo "[SKIP] fleet.contact@demo.sa not found\n";
}

// 7. Bays & Bookings
echo "\n-- 7. Bays & Bookings --\n";
test('GET bays',         req('GET', "$base/bays",         null, $token), 200, $pass, $fail, $errors);
test('GET bookings',     req('GET', "$base/bookings",     null, $token), 200, $pass, $fail, $errors);
test('GET bays/heatmap', req('GET', "$base/bays/heatmap", null, $token), 200, $pass, $fail, $errors);

// 8. Governance
echo "\n-- 8. Governance --\n";
test('GET governance/policies',   req('GET', "$base/governance/policies",   null, $token), 200, $pass, $fail, $errors);
test('GET governance/audit-logs', req('GET', "$base/governance/audit-logs", null, $token), 200, $pass, $fail, $errors);
test('GET contracts',             req('GET', "$base/contracts",             null, $token), 200, $pass, $fail, $errors);

// 9. Create Operations
echo "\n-- 9. Create Operations --\n";
$newVehicle = req('POST', "$base/vehicles", [
    'plate_number'=>'TEST '.rand(100,999), 'make'=>'Toyota', 'model'=>'Camry',
    'year'=>2023, 'color'=>'White', 'fuel_type'=>'gasoline'
], $token);
test('POST vehicles', $newVehicle, 201, $pass, $fail, $errors);

$newQuote = req('POST', "$base/quotes", [
    'issue_date'=>date('Y-m-d'), 'expiry_date'=>date('Y-m-d', strtotime('+30 days')),
    'notes'=>'test', 'items'=>[['name'=>'Test Service','quantity'=>1,'unit_price'=>100]]
], $token);
test('POST quotes', $newQuote, 201, $pass, $fail, $errors);

// 10. Logout
echo "\n-- 10. Logout --\n";
test('POST auth/logout', req('POST', "$base/auth/logout", null, $token), 200, $pass, $fail, $errors);

// Summary
$total = $pass + $fail;
echo "\n========================================\n";
echo "   RESULTS SUMMARY\n";
echo "========================================\n";
echo "Total  : $total\nPassed : $pass\nFailed : $fail\n";
if ($errors) {
    echo "\nFailed tests:\n";
    foreach($errors as $e) echo "  [FAIL] $e\n";
}
echo "\n";
