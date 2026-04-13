<?php
$base = 'http://localhost/api/v1';
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
    curl_close($ch);
    return ['code'=>$code, 'body'=>json_decode($body,true), 'raw'=>$body];
}

function test($label, $result, $expectedCode, &$pass, &$fail, &$errors) {
    $ok = $result['code'] === $expectedCode;
    $status = $ok ? '✅ PASS' : '❌ FAIL';
    echo "$status  $label — HTTP {$result['code']}\n";
    if ($ok) $pass++; else { $fail++; $errors[] = "$label: HTTP {$result['code']} — ".substr($result['raw'],0,200); }
    return $ok;
}

echo "\n========================================\n";
echo "   اختبار شامل نهائي — OSAS System\n";
echo "========================================\n\n";

// 1. Auth
echo "── 1. المصادقة ──\n";
$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'password']);
test('تسجيل دخول Owner', $r, 200, $pass, $fail, $errors);
$token = $r['body']['token'] ?? '';

$rf = req('POST', "$base/auth/login", ['email'=>'fleet.contact@demo.sa','password'=>'password']);
test('تسجيل دخول Fleet Contact', $rf, 200, $pass, $fail, $errors);
$fleetToken = $rf['body']['token'] ?? '';

$rc = req('POST', "$base/auth/login", ['email'=>'customer@demo.sa','password'=>'password']);
test('تسجيل دخول Customer', $rc, 200, $pass, $fail, $errors);
$customerToken = $rc['body']['token'] ?? '';

// 2. Core Data
echo "\n── 2. البيانات الأساسية ──\n";
test('قراءة العملاء',     req('GET', "$base/customers", null, $token), 200, $pass, $fail, $errors);
test('قراءة المنتجات',    req('GET', "$base/products",  null, $token), 200, $pass, $fail, $errors);
test('قراءة المركبات',    req('GET', "$base/vehicles",  null, $token), 200, $pass, $fail, $errors);
test('قراءة الموردين',    req('GET', "$base/suppliers", null, $token), 200, $pass, $fail, $errors);
test('قراءة أوامر العمل', req('GET', "$base/work-orders", null, $token), 200, $pass, $fail, $errors);
test('قراءة الفواتير',    req('GET', "$base/invoices",  null, $token), 200, $pass, $fail, $errors);
test('قراءة المخزون',     req('GET', "$base/inventory", null, $token), 200, $pass, $fail, $errors);
test('قراءة المشتريات',   req('GET', "$base/purchases", null, $token), 200, $pass, $fail, $errors);
test('قراءة عروض الأسعار', req('GET', "$base/quotes",  null, $token), 200, $pass, $fail, $errors);

// 3. Workshop
echo "\n── 3. الورشة ──\n";
test('قراءة الموظفين',    req('GET', "$base/workshop/employees", null, $token), 200, $pass, $fail, $errors);
test('قراءة المهام',      req('GET', "$base/workshop/tasks",     null, $token), 200, $pass, $fail, $errors);
test('قراءة العمولات',    req('GET', "$base/workshop/commissions", null, $token), 200, $pass, $fail, $errors);

// 4. Finance
echo "\n── 4. المالية ──\n";
test('قراءة دفتر الأستاذ', req('GET', "$base/ledger",             null, $token), 200, $pass, $fail, $errors);
test('قراءة المحفظة',      req('GET', "$base/wallet",             null, $token), 200, $pass, $fail, $errors);

// 5. Reports
echo "\n── 5. التقارير ──\n";
$from = date('Y-01-01'); $to = date('Y-m-d');
test('تقرير KPI',            req('GET', "$base/reports/kpi?from=$from&to=$to",                null, $token), 200, $pass, $fail, $errors);
test('تقرير المبيعات',       req('GET', "$base/reports/sales?from=$from&to=$to",              null, $token), 200, $pass, $fail, $errors);
test('تقرير المتأخرات',      req('GET', "$base/reports/overdue-receivables",                  null, $token), 200, $pass, $fail, $errors);
test('تقرير الضريبة',        req('GET', "$base/reports/vat?from=$from&to=$to",               null, $token), 200, $pass, $fail, $errors);
test('تقرير المخزون',        req('GET', "$base/reports/inventory",                           null, $token), 200, $pass, $fail, $errors);

// 6. Fleet Portal
echo "\n── 6. بوابة الأسطول ──\n";
if ($fleetToken) {
    test('Fleet Dashboard',    req('GET', "$base/fleet-portal/dashboard",  null, $fleetToken), 200, $pass, $fail, $errors);
    test('Fleet Vehicles',     req('GET', "$base/fleet-portal/vehicles",   null, $fleetToken), 200, $pass, $fail, $errors);
    test('Fleet Orders',       req('GET', "$base/fleet-portal/work-orders", null, $fleetToken), 200, $pass, $fail, $errors);
    test('Fleet Wallet',       req('GET', "$base/fleet-portal/wallet",     null, $fleetToken), 200, $pass, $fail, $errors);
} else {
    echo "⚠️  تخطي — fleet.contact@demo.sa غير موجود\n";
}

// 7. Bays & Bookings
echo "\n── 7. الرافعات والحجوزات ──\n";
test('قراءة الرافعات',    req('GET', "$base/bays",     null, $token), 200, $pass, $fail, $errors);
test('قراءة الحجوزات',   req('GET', "$base/bookings", null, $token), 200, $pass, $fail, $errors);
test('الخريطة الحرارية', req('GET', "$base/bays/heatmap", null, $token), 200, $pass, $fail, $errors);

// 8. Governance
echo "\n── 8. الحوكمة ──\n";
test('السياسات',        req('GET', "$base/governance/policies",   null, $token), 200, $pass, $fail, $errors);
test('سجل التدقيق',    req('GET', "$base/governance/audit-logs", null, $token), 200, $pass, $fail, $errors);
test('العقود',          req('GET', "$base/contracts",             null, $token), 200, $pass, $fail, $errors);

// 9. Create Operations Test
echo "\n── 9. عمليات الإنشاء ──\n";
$newVehicle = req('POST', "$base/vehicles", [
    'plate_number'=>'TEST '.rand(100,999), 'make'=>'Toyota', 'model'=>'Camry',
    'year'=>2023, 'color'=>'أبيض', 'fuel_type'=>'gasoline'
], $token);
test('إنشاء مركبة', $newVehicle, 201, $pass, $fail, $errors);

$newQuote = req('POST', "$base/quotes", [
    'issue_date'=>date('Y-m-d'), 'expiry_date'=>date('Y-m-d', strtotime('+30 days')),
    'notes'=>'اختبار', 'items'=>[['name'=>'خدمة تجريبية','quantity'=>1,'unit_price'=>100]]
], $token);
test('إنشاء عرض سعر', $newQuote, 201, $pass, $fail, $errors);

// 10. Logout
echo "\n── 10. تسجيل الخروج ──\n";
test('تسجيل الخروج', req('POST', "$base/auth/logout", null, $token), 200, $pass, $fail, $errors);

// Summary
$total = $pass + $fail;
echo "\n========================================\n";
echo "   ملخص النتائج\n";
echo "========================================\n";
echo "المجموع : $total\nناجح    : $pass\nفاشل    : $fail\n";
if ($errors) {
    echo "\nالأخطاء:\n";
    foreach($errors as $e) echo "  ❌ $e\n";
}
echo "\n";
