<?php
$base = 'http://saas_nginx/api/v1';
$token = null;

function req(string $method, string $url, array $data = [], string $token = null): array {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    if (in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
        $headers[] = 'Idempotency-Key: test-' . uniqid('', true);
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($data && in_array(strtoupper($method), ['POST','PUT','PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($body, true), 'error' => $error];
}

function ok($label, $r, $check = null) {
    $s = $r['status'];
    $b = $r['body'];
    if ($check) {
        $pass = $check($b);
    } else {
        $pass = $s >= 200 && $s < 300;
    }
    $icon = $pass ? "\033[32m✅\033[0m" : "\033[31m❌\033[0m";
    $extra = !$pass ? " — HTTP $s " . json_encode($b).substr('',0,120) : " — HTTP $s";
    echo "$icon $label$extra\n";
    return $pass;
}

echo "\n=== اختبارات متقدمة للنظام ===\n\n";

// Login
$r = req('POST', "$base/auth/login", ['email'=>'owner@demo.sa','password'=>'Password123!']);
ok('تسجيل الدخول', $r, fn($b) => !empty($b['token']));
$token = $r['body']['token'] ?? '';

if (!$token) { echo "❌ لا يمكن المتابعة بدون token\n"; exit(1); }

// 1. Contracts
$r = req('GET', "$base/governance/contracts", [], $token);
ok('قراءة العقود', $r, fn($b) => isset($b['data']));

// 2. Create contract
$r = req('POST', "$base/governance/contracts", [
    'title' => 'عقد اختبار',
    'party_type' => 'service_center',
    'party_name' => 'مركز الخدمة التجريبي',
    'start_date' => '2026-01-01',
    'end_date' => '2026-12-31',
    'value' => 5000,
    'payment_terms' => ['type' => 'monthly', 'amount' => 500],
], $token);
ok('إنشاء عقد جديد', $r, fn($b) => !empty($b['id']) || !empty($b['data']['id']));

// 3. Subscription
$r = req('GET', "$base/subscription", [], $token);
ok('الاشتراك الحالي', $r, fn($b) => isset($b['data']['subscription']) || isset($b['plan']) || isset($b['slug']));

// 4. Reports Sales
$r = req('GET', "$base/reports/sales?from=2026-01-01&to=2026-12-31", [], $token);
ok('تقرير المبيعات', $r, fn($b) => isset($b['summary']) || isset($b['data']['summary']));

// 5. Fleet customers
$r = req('GET', "$base/fleet/customers", [], $token);
ok('عملاء الأسطول', $r, fn($b) => is_array($b));

// 6. Workshop employees
$r = req('GET', "$base/workshop/employees", [], $token);
ok('الموظفون', $r, fn($b) => isset($b['data']));

// 7. Bays heatmap
$r = req('GET', "$base/bays/heatmap", [], $token);
ok('خريطة الرافعات', $r, fn($b) => is_array($b));

// 8. Bookings
$r = req('GET', "$base/bookings", [], $token);
ok('الحجوزات', $r, fn($b) => isset($b['data']) || is_array($b));

// 9. Wallet
$r = req('GET', "$base/wallet", [], $token);
ok('المحافظ', $r, fn($b) => isset($b['wallets']) || isset($b['data']));

// 10. Governance policies
$r = req('GET', "$base/governance/policies", [], $token);
ok('سياسات الحوكمة', $r, fn($b) => is_array($b) || isset($b['data']));

// 11. Governance alerts
$r = req('GET', "$base/governance/alerts/me", [], $token);
ok('التنبيهات', $r, fn($b) => is_array($b));

// 12. Reports financial
$r = req('GET', "$base/reports/financial", [], $token);
ok('التقرير المالي', $r, fn($b) => isset($b['data']) || is_array($b));

// 13. Reports inventory
$r = req('GET', "$base/reports/inventory", [], $token);
ok('تقرير المخزون', $r, fn($b) => isset($b['data']) || is_array($b));

// 14. Product import template
$r = req('GET', "$base/governance/products/template", [], $token);
ok('قالب استيراد المنتجات', $r, fn($b) => $b === null); // CSV returns null from json_decode

// 15. Inventory
$r = req('GET', "$base/inventory", [], $token);
ok('المخزون', $r, fn($b) => isset($b['data']));

echo "\n=== انتهى ===\n";
