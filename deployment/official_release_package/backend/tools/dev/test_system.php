<?php
/**
 * اختبار شامل للنظام
 */
$base = 'http://saas_nginx/api/v1';
$pass = 0; $fail = 0; $fails = [];

function req(string $method, string $url, array $body = [], array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json','Accept: application/json'], $headers),
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code' => $code, 'body' => json_decode($raw, true) ?? [], 'raw' => $raw];
}
function ok(string $l): void { global $pass; $pass++; echo "\033[32m✅ PASS\033[0m  $l\n"; }
function fail(string $l, string $r=''): void {
    global $fail, $fails; $fail++;
    $s = strlen($r) > 150 ? substr($r,0,150).'…' : $r;
    $fails[] = "  ❌ $l — $s";
    echo "\033[31m❌ FAIL\033[0m  $l" . ($s ? " — $s" : '') . "\n";
}

echo "════════════════════════════════════\n";
echo "   اختبار شامل للنظام\n";
echo "════════════════════════════════════\n\n";

// 1. Health
$r = req('GET', "$base/health");
$r['code'] === 200 ? ok('Health Check') : fail('Health Check', $r['raw']);

// 2. Login
$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'Password123!']);
if ($r['code'] === 200) {
    ok('تسجيل الدخول');
    $token = $r['body']['token'] ?? $r['body']['data']['token'] ?? '';
    $auth  = ["Authorization: Bearer $token"];
} else {
    fail('تسجيل الدخول', $r['raw']); exit(1);
}

// 3. Customers
$r = req('GET', "$base/customers", [], $auth);
$count = $r['body']['meta']['total'] ?? count($r['body']['data'] ?? []);
$r['code'] === 200 && $count > 0 ? ok("العملاء ($count)") : fail('العملاء', $r['raw']);

// 4. Vehicles
$r = req('GET', "$base/vehicles", [], $auth);
$count = $r['body']['meta']['total'] ?? count($r['body']['data'] ?? []);
$r['code'] === 200 && $count > 0 ? ok("المركبات ($count)") : fail('المركبات', $r['raw']);

// 5. Products
$r = req('GET', "$base/products", [], $auth);
$count = $r['body']['meta']['total'] ?? count($r['body']['data'] ?? []);
$r['code'] === 200 && $count > 0 ? ok("المنتجات ($count)") : fail('المنتجات', $r['raw']);

// 6. Work Orders
$r = req('GET', "$base/work-orders", [], $auth);
$count = $r['body']['meta']['total'] ?? count($r['body']['data'] ?? []);
$r['code'] === 200 && $count > 0 ? ok("أوامر العمل ($count)") : fail('أوامر العمل', $r['raw']);

// 7. Invoices
$r = req('GET', "$base/invoices", [], $auth);
$count = $r['body']['meta']['total'] ?? count($r['body']['data'] ?? []);
$r['code'] === 200 && $count > 0 ? ok("الفواتير ($count)") : fail('الفواتير', $r['raw']);

// 8. Subscription
$r = req('GET', "$base/subscription", [], $auth);
$planSlug = $r['body']['data']['plan']['slug'] ?? $r['body']['plan'] ?? 'unknown';
$r['code'] === 200 ? ok("الاشتراك: $planSlug") : fail('الاشتراك', $r['raw']);

// 9. Reports (with required dates)
$from = date('Y-m-01'); $to = date('Y-m-d');
$r = req('GET', "$base/reports/sales?period=month&from=$from&to=$to", [], $auth);
$r['code'] === 200 ? ok('تقرير المبيعات') : fail('تقرير المبيعات', $r['raw']);

// 10. Wallets (summary for company)
$r = req('GET', "$base/wallets/1/summary", [], $auth);
$r['code'] === 200 ? ok('ملخص المحفظة') : fail('ملخص المحفظة', $r['raw']);

// 11. Company Settings
$r = req('GET', "$base/companies/1", [], $auth);
$r['code'] === 200 ? ok('إعدادات الشركة') : fail('إعدادات الشركة', $r['raw']);

// 12. Logout
$r = req('POST', "$base/auth/logout", [], $auth);
$r['code'] === 200 ? ok('تسجيل الخروج') : fail('تسجيل الخروج', $r['raw']);

echo "\n════════════════════════════════════\n";
echo "   ملخص النتائج\n";
echo "════════════════════════════════════\n";
echo "المجموع : " . ($pass + $fail) . " اختبار\n";
echo "\033[32mناجح    : $pass\033[0m\n";
echo ($fail > 0 ? "\033[31m" : '') . "فاشل    : $fail" . ($fail > 0 ? "\033[0m" : '') . "\n";
if ($fails) { echo "\nالاختبارات الفاشلة:\n"; foreach ($fails as $f) echo "$f\n"; }
echo "\n";
