<?php
/**
 * اختبار شامل للنظام - عمليات تجريبية كاملة
 */
$base = 'http://172.19.0.8/api/v1';
$token = null;
$pass = 0; $fail = 0; $results = [];

function req(string $method, string $url, array $body = [], ?string $token = null, array $extra_headers = []): array {
    $ch = curl_init($url);
    $headers = array_filter([
        'Content-Type: application/json',
        'Accept: application/json',
        $token ? "Authorization: Bearer $token" : null,
        ...$extra_headers,
    ]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body ? json_encode($body) : null,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($body, true), 'raw' => $body];
}

function test(string $name, callable $fn): void {
    global $pass, $fail, $results;
    try {
        $result = $fn();
        if ($result === true || (is_array($result) && ($result['ok'] ?? false))) {
            $pass++;
            $results[] = "✅ $name";
        } else {
            $fail++;
            $msg = is_array($result) ? ($result['err'] ?? json_encode($result)) : $result;
            $results[] = "❌ $name — $msg";
        }
    } catch (\Throwable $e) {
        $fail++;
        $results[] = "❌ $name — Exception: " . $e->getMessage();
    }
}

echo "========================================\n";
echo "   عملية تجريبية شاملة للنظام\n";
echo "========================================\n\n";

// 1. تسجيل الدخول
echo "── 1. المصادقة ──\n";
test('تسجيل دخول owner', function() use ($base, &$token) {
    $r = req('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
    if ($r['code'] !== 200) return ['err' => "HTTP {$r['code']}: {$r['raw']}"];
    $token = $r['body']['token'] ?? $r['body']['data']['token'] ?? null;
    return $token ? true : ['err' => 'no token'];
});

// 2. لوحة المؤشرات
echo "\n── 2. لوحة المؤشرات ──\n";
test('KPI Dashboard', function() use ($base, &$token) {
    $r = req('GET', "$base/reports/kpi?from=2024-01-01&to=2099-12-31", [], $token);
    return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}"];
});

// 3. المنتجات
echo "\n── 3. المنتجات ──\n";
$productId = null;
test('إنشاء منتج جديد', function() use ($base, &$token, &$productId) {
    $r = req('POST', "$base/products", [
        'name'      => 'زيت المحرك [اختبار ' . date('H:i:s') . ']',
        'sku'       => 'TEST-' . time(),
        'type'      => 'part',
        'sale_price' => 45.00,
        'price'     => 45.00,
        'cost'      => 30.00,
        'tax_rate'  => 15,
        'track_stock' => true,
        'stock_quantity' => 50,
    ], $token);
    if ($r['code'] !== 201) return ['err' => "HTTP {$r['code']}: " . substr($r['raw'], 0, 250)];
    $productId = $r['body']['data']['id'] ?? null;
    return $productId ? true : ['err' => 'no id'];
});

// 4. العملاء
echo "\n── 4. العملاء ──\n";
$customerId = null;
test('إنشاء عميل جديد', function() use ($base, &$token, &$customerId) {
    $r = req('POST', "$base/customers", [
        'name'  => 'عميل تجريبي ' . date('H:i:s'),
        'phone' => '05' . rand(10000000, 99999999),
        'type'  => 'b2c',
    ], $token);
    if ($r['code'] !== 201) return ['err' => "HTTP {$r['code']}: " . substr($r['raw'], 0, 250)];
    $customerId = $r['body']['data']['id'] ?? null;
    return $customerId ? true : ['err' => 'no id'];
});

// 5. المركبات
echo "\n── 5. المركبات ──\n";
$vehicleId = null;
test('إنشاء مركبة جديدة', function() use ($base, &$token, &$customerId, &$vehicleId) {
    if (!$customerId) return ['err' => 'no customer'];
    $r = req('POST', "$base/vehicles", [
        'customer_id'  => $customerId,
        'plate_number' => 'TST-' . rand(1000, 9999),
        'make'         => 'Toyota',
        'model'        => 'Camry',
        'year'         => 2022,
    ], $token);
    if ($r['code'] !== 201) return ['err' => "HTTP {$r['code']}: " . substr($r['raw'], 0, 250)];
    $vehicleId = $r['body']['data']['id'] ?? null;
    return $vehicleId ? true : ['err' => 'no id'];
});

// 6. أمر العمل
echo "\n── 6. أوامر العمل ──\n";
$woId = null;
test('إنشاء أمر عمل', function() use ($base, &$token, &$customerId, &$vehicleId, &$woId) {
    if (!$vehicleId) return ['err' => 'no vehicle'];
    $r = req('POST', "$base/work-orders", [
        'customer_id'        => $customerId,
        'vehicle_id'         => $vehicleId,
        'priority'           => 'normal',
        'customer_complaint' => 'تغيير زيت المحرك',
        'items'              => [
            ['item_type' => 'service', 'name' => 'تغيير الزيت', 'quantity' => 1, 'unit_price' => 80, 'tax_rate' => 15],
        ],
    ], $token);
    if ($r['code'] !== 201) return ['err' => "HTTP {$r['code']}: " . substr($r['raw'], 0, 300)];
    $woId = $r['body']['data']['id'] ?? null;
    return $woId ? true : ['err' => 'no id'];
});

// 7. نقطة البيع
echo "\n── 7. نقطة البيع ──\n";
test('عملية POS', function() use ($base, &$token, &$customerId, &$productId) {
    if (!$productId || !$customerId) return ['err' => 'missing dependencies'];
    $r = req('POST', "$base/pos/sale", [
        'customer_id' => $customerId,
        'payment'     => ['method' => 'cash', 'amount' => 45.00],
        'items'       => [
            ['product_id' => $productId, 'name' => 'زيت محرك', 'quantity' => 1, 'unit_price' => 45.00, 'tax_rate' => 15],
        ],
    ], $token, ['Idempotency-Key: pos-' . time()]);
    if ($r['code'] !== 201) return ['err' => "HTTP {$r['code']}: " . substr($r['raw'], 0, 300)];
    return ['ok' => true];
});

// 8. الفواتير
echo "\n── 8. الفواتير ──\n";
test('إنشاء فاتورة يدوية', function() use ($base, &$token, &$customerId) {
    if (!$customerId) return ['err' => 'no customer'];
    $r = req('POST', "$base/invoices", [
        'customer_id' => $customerId,
        'issued_at'   => date('Y-m-d'),
        'due_at'      => date('Y-m-d', strtotime('+30 days')),
        'items'       => [['name' => 'خدمة فحص', 'quantity' => 1, 'unit_price' => 150, 'tax_rate' => 15]],
        'payment'     => ['method' => 'cash', 'amount' => 172.50],
    ], $token, ['Idempotency-Key: inv-' . time()]);
    if ($r['code'] !== 201) return ['err' => "HTTP {$r['code']}: " . $r['raw']];
    return ['ok' => true];
});

// 9. التقارير
foreach ([
    'kpi'           => 'تقرير KPI',
    'sales'         => 'تقرير المبيعات',
    'vat'           => 'تقرير ضريبة القيمة المضافة',
    'inventory'     => 'تقرير المخزون',
    'work-orders'   => 'تقرير أوامر العمل',
] as $endpoint => $label) {
    test($label, function() use ($base, &$token, $endpoint) {
        $params = in_array($endpoint, ['kpi','sales','vat','work-orders'])
            ? "?from=2024-01-01&to=" . date('Y-m-d')
            : '';
        $r = req('GET', "$base/reports/$endpoint$params", [], $token);
        return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}"];
    });
}

// 10. المحفظة
echo "\n── 10. المحفظة ──\n";
test('قراءة المحفظة', function() use ($base, &$token) {
    $r = req('GET', "$base/wallet", [], $token);
    return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}: " . substr($r['raw'], 0, 200)];
});

// 11. المخزون
echo "\n── 11. المخزون ──\n";
test('قراءة المخزون', function() use ($base, &$token) {
    $r = req('GET', "$base/inventory", [], $token);
    return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}"];
});

// 12. الوقود
echo "\n── 12. الوقود ──\n";
test('قراءة سجلات الوقود', function() use ($base, &$token) {
    $r = req('GET', "$base/governance/fuel", [], $token);
    return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}"];
});

// 13. الإحالات
echo "\n── 13. الإحالات ──\n";
test('قراءة الإحالات', function() use ($base, &$token) {
    $r = req('GET', "$base/governance/referrals", [], $token);
    return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}"];
});

// 14. الاشتراكات
echo "\n── 14. الاشتراكات ──\n";
test('معلومات الاشتراك', function() use ($base, &$token) {
    $r = req('GET', "$base/subscription", [], $token);
    return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}"];
});

// 15. تسجيل الخروج
echo "\n── 15. الخروج ──\n";
test('تسجيل الخروج', function() use ($base, &$token) {
    $r = req('POST', "$base/auth/logout", [], $token);
    return $r['code'] === 200 ? true : ['err' => "HTTP {$r['code']}"];
});

// النتائج النهائية
echo "\n========================================\n";
echo "   النتائج النهائية\n";
echo "========================================\n";
echo "المجموع : " . ($pass + $fail) . "\n";
echo "ناجح    : $pass ✅\n";
echo "فاشل    : $fail ❌\n\n";

foreach ($results as $r) {
    echo "  $r\n";
}

if ($fail === 0) {
    echo "\n🎉 جميع الاختبارات نجحت! النظام يعمل بشكل مثالي.\n";
} else {
    echo "\n⚠️  يوجد $fail اختبار(ات) فاشلة تحتاج مراجعة.\n";
}
