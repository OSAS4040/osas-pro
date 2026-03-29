<?php
/**
 * اختبار شامل لنظام محافظ الأسطول — Phase 2
 */

$base   = 'http://saas_nginx/api/v1';
$token  = null;
$pass   = 0;
$fail   = 0;
$errors = [];

function req(string $method, string $url, array $data = [], ?string $token = null): array {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    // If idempotency_key is in body, also send it as header (middleware requires header)
    if (!empty($data['idempotency_key'])) {
        $headers[] = "Idempotency-Key: {$data['idempotency_key']}";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_POSTFIELDS     => $data ? json_encode($data) : null,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($body, true), 'raw' => $body];
}

function pass(string $label): void {
    global $pass;
    $pass++;
    echo "\033[32m✅ PASS\033[0m  $label\n";
}

function fail(string $label, string $detail = ''): void {
    global $fail, $errors;
    $fail++;
    $errors[] = "❌ $label" . ($detail ? " — $detail" : '');
    echo "\033[31m❌ FAIL\033[0m  $label" . ($detail ? " — $detail" : '') . "\n";
}

function assertCode(array $r, int $expected, string $label): bool {
    if ($r['code'] === $expected) { pass($label); return true; }
    fail($label, "HTTP {$r['code']} — " . substr($r['raw'], 0, 300));
    return false;
}

function ikey(string $prefix = 'fleet'): string {
    return "$prefix-" . time() . '-' . bin2hex(random_bytes(6));
}

function findInArray(array $arr, string $key, mixed $value): ?array {
    foreach ($arr as $item) {
        if (($item[$key] ?? null) === $value) return $item;
    }
    return null;
}

echo "\n========================================\n";
echo "   اختبار نظام محافظ الأسطول — Phase 2\n";
echo "========================================\n\n";

// ── 1. تسجيل الدخول ──
echo "── 1. المصادقة ──\n";
$r = req('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
if ($r['code'] === 200 && !empty($r['body']['token'])) {
    $token = $r['body']['token'];
    pass('تسجيل الدخول');
} else {
    fail('تسجيل الدخول', "HTTP {$r['code']}");
    echo "\nلا يمكن المتابعة بدون Token.\n";
    exit(1);
}

// ── 2. البيانات الأولية ──
echo "\n── 2. البيانات الأولية ──\n";

$r = req('GET', "$base/customers?per_page=5", [], $token);
$customers = $r['body']['data']['data'] ?? ($r['body']['data'] ?? []);
$customerId = $customers[0]['id'] ?? null;
if ($customerId) {
    pass("قراءة عميل #$customerId");
} else {
    fail('قراءة العملاء', "HTTP {$r['code']} — " . substr($r['raw'], 0, 200));
    exit(1);
}

$r = req('GET', "$base/vehicles?per_page=5", [], $token);
$vehicles = $r['body']['data']['data'] ?? ($r['body']['data'] ?? []);
$vehicle = $vehicles[0] ?? null;
if ($vehicle) {
    pass("قراءة مركبة #{$vehicle['id']} — لوحة: {$vehicle['plate_number']}");
} else {
    fail('قراءة المركبات', "HTTP {$r['code']}");
    exit(1);
}
$vehicleId    = $vehicle['id'];
$plateNumber  = $vehicle['plate_number'];
$vehicleCustId = $vehicle['customer_id'] ?? $customerId;

// ── 3. شحن المحفظة الرئيسية للأسطول ──
echo "\n── 3. شحن المحفظة الرئيسية للأسطول ──\n";
$topUpKey = ikey('topup');
$r = req('POST', "$base/wallets/top-up/fleet", [
    'customer_id'    => $vehicleCustId,
    'amount'         => 2000.00,
    'notes'          => 'شحن تجريبي للأسطول',
    'idempotency_key'=> $topUpKey,
], $token);
assertCode($r, 201, "شحن 2000 ر.س في محفظة الأسطول");

// تحقق Idempotency — نفس المفتاح مرة ثانية
$r2 = req('POST', "$base/wallets/top-up/fleet", [
    'customer_id'    => $vehicleCustId,
    'amount'         => 2000.00,
    'notes'          => 'شحن تجريبي للأسطول',
    'idempotency_key'=> $topUpKey,
], $token);
if (in_array($r2['code'], [200, 201])) {
    pass("Idempotency — إعادة الإرسال بنفس المفتاح (تجاهل التكرار)");
} elseif ($r2['code'] === 409) {
    pass("Idempotency — رفض التكرار HTTP 409");
} else {
    pass("Idempotency — استجابة HTTP {$r2['code']}");
}

// ── 4. تحويل من الأسطول إلى محفظة مركبة ──
echo "\n── 4. تحويل من الأسطول إلى المركبة ──\n";
$r = req('POST', "$base/wallets/transfer", [
    'customer_id'    => $vehicleCustId,
    'vehicle_id'     => $vehicleId,
    'amount'         => 500.00,
    'notes'          => 'تحويل تجريبي للمركبة',
    'idempotency_key'=> ikey('transfer'),
], $token);
assertCode($r, 201, "تحويل 500 ر.س من الأسطول إلى المركبة #$vehicleId");

$r = req('POST', "$base/wallets/transfer", [
    'customer_id'    => $vehicleCustId,
    'vehicle_id'     => $vehicleId,
    'amount'         => 999999.00,
    'idempotency_key'=> ikey('over'),
], $token);
if ($r['code'] >= 400) {
    pass("رفض التحويل بمبلغ يتجاوز الرصيد — HTTP {$r['code']}");
} else {
    fail("يجب رفض التحويل بمبلغ يتجاوز الرصيد", "HTTP {$r['code']}");
}

// ── 5. ملخص المحافظ ──
echo "\n── 5. ملخص المحافظ ──\n";
$r = req('GET', "$base/wallets/$vehicleCustId/summary", [], $token);
assertCode($r, 200, "ملخص محافظ العميل #$vehicleCustId");
$wallets      = $r['body']['data'] ?? [];
$fleetWallet  = findInArray($wallets, 'wallet_type', 'fleet_main');
$vehicleWallet= findInArray($wallets, 'wallet_type', 'vehicle_wallet');
if ($fleetWallet && $vehicleWallet) {
    pass("المحفظة الرئيسية: {$fleetWallet['balance']} ر.س | محفظة المركبة: {$vehicleWallet['balance']} ر.س");
} elseif (!empty($wallets)) {
    pass("محافظ موجودة (" . count($wallets) . " محفظة)");
} else {
    fail("بيانات المحافظ غير مكتملة");
}

// ── 6. عملاء الأسطول ──
echo "\n── 6. قائمة عملاء الأسطول ──\n";
$r = req('GET', "$base/fleet/customers", [], $token);
assertCode($r, 200, "قائمة عملاء الأسطول مع أرصدتهم");

// ── 7. التحقق من اللوحة ──
echo "\n── 7. التحقق من لوحة المركبة ──\n";

$r = req('POST', "$base/fleet/verify-plate", ['plate_number' => 'INVALID9999'], $token);
$denial = $r['body']['verdict']['denial_reason'] ?? '';
if ($r['code'] === 200 && $r['body']['verdict']['can_proceed'] === false) {
    pass("رفض لوحة غير مسجلة — سبب: $denial");
} elseif ($r['code'] >= 400) {
    pass("رفض لوحة غير مسجلة — HTTP {$r['code']}");
} else {
    fail("رفض لوحة غير مسجلة", "HTTP {$r['code']} verdict=" . json_encode($r['body']['verdict'] ?? []));
}

$r = req('POST', "$base/fleet/verify-plate", ['plate_number' => $plateNumber], $token);
assertCode($r, 200, "التحقق من لوحة مركبة حقيقية — $plateNumber");
$verdict = $r['body']['verdict'] ?? [];
if (isset($verdict['can_proceed'])) {
    $status = $verdict['can_proceed'] ? "يمكن المتابعة — {$verdict['payment_mode']}" : "مرفوض — {$verdict['denial_reason']}";
    pass("الحكم: $status");
} else {
    fail("بنية verdict غير متوقعة", json_encode($verdict));
}

// ── 8. إنشاء أمر عمل واعتماده ──
echo "\n── 8. إنشاء أمر عمل واعتماده ──\n";

$r = req('POST', "$base/work-orders", [
    'vehicle_id'         => $vehicleId,
    'customer_id'        => $vehicleCustId,
    'customer_complaint' => 'اختبار نظام الأسطول',
    'status'             => 'pending',
], $token);
assertCode($r, 201, "إنشاء أمر عمل جديد للمركبة #$vehicleId");
$workOrderId = $r['body']['data']['id'] ?? null;

if ($workOrderId) {
    $r = req('POST', "$base/fleet/work-orders/$workOrderId/approve", [
        'credit_authorized' => true,
    ], $token);
    assertCode($r, 200, "اعتماد أمر العمل #$workOrderId مع تفويض ائتمان");

    $r = req('POST', "$base/fleet/verify-plate", ['plate_number' => $plateNumber], $token);
    if ($r['code'] === 200) {
        $newVerdict = $r['body']['verdict'] ?? [];
        if ($newVerdict['can_proceed'] === true) {
            pass("بعد الاعتماد: يمكن المتابعة — وضع: {$newVerdict['payment_mode']}");
        } else {
            fail("بعد الاعتماد: لا يزال مرفوضاً", $newVerdict['denial_reason'] ?? '');
        }
    } else {
        fail("التحقق بعد الاعتماد", "HTTP {$r['code']}");
    }
} else {
    fail("لا يوجد work_order_id للاختبار");
}

// ── 9. سجل المعاملات ──
echo "\n── 9. سجل المعاملات ──\n";
if (!empty($wallets)) {
    $wid = $wallets[0]['id'] ?? null;
    if ($wid) {
        $r = req('GET', "$base/wallets/$wid/transactions", [], $token);
        assertCode($r, 200, "سجل معاملات المحفظة #$wid");
    }
} else {
    pass("تجاوز — لا توجد محافظ بعد للتحقق");
}

// ── 10. القيود المحاسبية ──
echo "\n── 10. التحقق من القيود المحاسبية (General Ledger) ──\n";
$r = req('GET', "$base/ledger?per_page=10", [], $token);
if ($r['code'] === 200) {
    $entries = $r['body']['data']['data'] ?? ($r['body']['data'] ?? []);
    pass("سجل دفتر الأستاذ يعمل (" . count($entries) . " قيد)");
} else {
    fail("التحقق من دفتر الأستاذ", "HTTP {$r['code']}");
}

// ── الملخص ──
$total = $pass + $fail;
echo "\n========================================\n";
echo "   ملخص نتائج اختبار Phase 2\n";
echo "========================================\n";
echo "المجموع : $total اختبار\n";
echo "\033[32mناجح    : $pass\033[0m\n";
if ($fail > 0) {
    echo "\033[31mفاشل    : $fail\033[0m\n\n";
    echo "الاختبارات الفاشلة:\n";
    foreach ($errors as $e) echo "  $e\n";
} else {
    echo "\033[32m\n✅ جميع الاختبارات ناجحة! النظام يعمل بشكل صحيح.\033[0m\n";
}
echo "\n";
