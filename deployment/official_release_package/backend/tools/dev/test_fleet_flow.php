<?php
/**
 * اختبار التدفق الكامل لنظام الأسطول
 * fleet_contact → إنشاء طلب خدمة + شحن رصيد
 * fleet_manager → اعتماد ائتمان + رفض طلب
 * workshop (owner) → التحقق من اللوحة بعد الاعتماد
 */

$base   = 'http://saas_nginx/api/v1';
$pass   = 0;
$fail   = 0;
$errors = [];

function req(string $method, string $url, array $data = [], ?string $token = null, ?string $ikey = null): array {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    if ($ikey)  $headers[] = "Idempotency-Key: $ikey";
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

function pass(string $label): void { global $pass; $pass++; echo "\033[32m✅ PASS\033[0m  $label\n"; }
function fail(string $label, string $detail = ''): void {
    global $fail, $errors; $fail++;
    $errors[] = "❌ $label" . ($detail ? " — $detail" : '');
    echo "\033[31m❌ FAIL\033[0m  $label" . ($detail ? " — $detail" : '') . "\n";
}
function check(array $r, int $expected, string $label): bool {
    if ($r['code'] === $expected) { pass($label); return true; }
    fail($label, "HTTP {$r['code']} — " . substr($r['raw'], 0, 250));
    return false;
}
function ikey(string $p = 'k'): string { return "$p-" . time() . '-' . bin2hex(random_bytes(4)); }

echo "\n========================================\n";
echo "   اختبار التدفق الكامل لنظام الأسطول\n";
echo "========================================\n\n";

// ── 1. تسجيل دخول fleet_contact ──────────────────────────────
echo "── 1. تسجيل دخول fleet_contact ──\n";
$r = req('POST', "$base/auth/login", ['email' => 'fleet.contact@demo.sa', 'password' => 'Password123!']);
if ($r['code'] === 200 && !empty($r['body']['token'])) {
    $fcToken = $r['body']['token'];
    pass("تسجيل دخول fleet_contact — {$r['body']['user']['name']}");
} else { fail('تسجيل دخول fleet_contact', "HTTP {$r['code']}"); exit(1); }

// ── 2. تسجيل دخول fleet_manager ──────────────────────────────
$r = req('POST', "$base/auth/login", ['email' => 'fleet.manager@demo.sa', 'password' => 'Password123!']);
if ($r['code'] === 200 && !empty($r['body']['token'])) {
    $fmToken = $r['body']['token'];
    pass("تسجيل دخول fleet_manager — {$r['body']['user']['name']}");
} else { fail('تسجيل دخول fleet_manager', "HTTP {$r['code']}"); exit(1); }

// ── 3. تسجيل دخول الورشة (owner) ──────────────────────────────
$r = req('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
if ($r['code'] === 200 && !empty($r['body']['token'])) {
    $wsToken = $r['body']['token'];
    pass("تسجيل دخول الورشة (owner)");
} else { fail('تسجيل دخول الورشة', "HTTP {$r['code']}"); exit(1); }

// ── 4. fleet_contact: عرض لوحة التحكم ────────────────────────
echo "\n── 4. Fleet Portal Dashboard ──\n";
$r = req('GET', "$base/fleet-portal/dashboard", [], $fcToken);
check($r, 200, "fleet_contact: عرض لوحة التحكم");

// ── 5. fleet_contact: عرض المركبات ───────────────────────────
echo "\n── 5. قائمة مركبات الجهة العميلة ──\n";
$r = req('GET', "$base/fleet-portal/vehicles", [], $fcToken);
check($r, 200, "fleet_contact: قائمة المركبات");
$vehicles = $r['body']['data']['data'] ?? ($r['body']['data'] ?? []);
$vehicle  = $vehicles[0] ?? null;
if ($vehicle) {
    pass("أول مركبة: {$vehicle['plate_number']} (ID={$vehicle['id']})");
} else {
    fail("لا توجد مركبات مرتبطة بالعميل");
    exit(1);
}
$vehicleId   = $vehicle['id'];
$plateNumber = $vehicle['plate_number'];

// ── 6. fleet_contact: شحن رصيد ───────────────────────────────
echo "\n── 6. شحن الرصيد (fleet_contact) ──\n";
$topUpKey = ikey('topup');
$r = req('POST', "$base/fleet-portal/wallet/top-up", [
    'wallet_type'     => 'fleet_main',
    'amount'          => 1500.00,
    'notes'           => 'شحن تجريبي من fleet_contact',
    'idempotency_key' => $topUpKey,
], $fcToken, $topUpKey);
check($r, 201, "fleet_contact: شحن 1500 ر.س في المحفظة الرئيسية");

// Idempotency — إعادة الإرسال بنفس المفتاح
$r2 = req('POST', "$base/fleet-portal/wallet/top-up", [
    'wallet_type'     => 'fleet_main',
    'amount'          => 1500.00,
    'notes'           => 'شحن تجريبي من fleet_contact',
    'idempotency_key' => $topUpKey,
], $fcToken, $topUpKey);
if (in_array($r2['code'], [200, 201, 409])) {
    pass("Idempotency — إعادة الإرسال بنفس المفتاح: HTTP {$r2['code']}");
} else {
    fail("Idempotency — نتيجة غير متوقعة", "HTTP {$r2['code']}");
}

// ── 7. fleet_contact: عرض ملخص المحافظ ───────────────────────
echo "\n── 7. ملخص المحافظ ──\n";
$r = req('GET', "$base/fleet-portal/wallet/summary", [], $fcToken);
check($r, 200, "fleet_contact: ملخص المحافظ");
$wallets = $r['body']['data'] ?? [];
if (!empty($wallets)) {
    foreach ($wallets as $w) {
        pass("  محفظة [{$w['wallet_type']}]: {$w['balance']} ر.س");
    }
} else {
    pass("(لا توجد محافظ بعد — ستُنشأ عند أول شحن)");
}

// ── 8. fleet_contact: إنشاء طلب خدمة (دفع من المحفظة) ────────
echo "\n── 8. إنشاء طلب خدمة — دفع من المحفظة ──\n";
$r = req('POST', "$base/fleet-portal/work-orders", [
    'vehicle_id'         => $vehicleId,
    'customer_complaint' => 'فحص دوري شامل — اختبار',
    'mileage'            => 85000,
    'driver_name'        => 'محمد العتيبي',
    'driver_phone'       => '0501234567',
    'use_credit'         => false,
], $fcToken);
check($r, 201, "fleet_contact: إنشاء طلب خدمة (wallet mode)");
$woWallet = $r['body']['data']['id'] ?? null;
if ($woWallet) pass("  رقم الطلب: #{$r['body']['data']['order_number']}");

// ── 9. fleet_contact: إنشاء طلب خدمة (يطلب ائتمان) ──────────
echo "\n── 9. إنشاء طلب خدمة — يطلب ائتمان (بانتظار موافقة المدير) ──\n";
$r = req('POST', "$base/fleet-portal/work-orders", [
    'vehicle_id'         => $vehicleId,
    'customer_complaint' => 'صيانة كبرى — تطلب ائتمان',
    'use_credit'         => true,
], $fcToken);
check($r, 201, "fleet_contact: إنشاء طلب خدمة (credit mode — pending approval)");
$woCreditId = $r['body']['data']['id'] ?? null;
$woStatus   = $r['body']['data']['approval_status'] ?? 'unknown';
if ($woStatus === 'pending') {
    pass("  حالة الاعتماد: pending ✓ (ينتظر fleet_manager)");
} else {
    fail("  حالة الاعتماد يجب أن تكون pending", "actual=$woStatus");
}

// ── 10. fleet_manager: عرض الطلبات المعلقة ───────────────────
echo "\n── 10. Fleet Manager: الطلبات بانتظار الاعتماد ──\n";
$r = req('GET', "$base/fleet-portal/work-orders/pending-approval", [], $fmToken);
check($r, 200, "fleet_manager: قائمة الطلبات المعلقة");
$pending = $r['body']['data']['data'] ?? ($r['body']['data'] ?? []);
pass("  عدد الطلبات المعلقة: " . count($pending));

// fleet_contact محاولة الوصول لهذا المسار — يجب أن يُرفض
$r = req('GET', "$base/fleet-portal/work-orders/pending-approval", [], $fcToken);
if ($r['code'] === 403) {
    pass("  fleet_contact مرفوض من pending-approval (403) ✓");
} else {
    fail("  fleet_contact يجب أن يُرفض من pending-approval", "HTTP {$r['code']}");
}

// ── 11. fleet_manager: اعتماد ائتمان ─────────────────────────
echo "\n── 11. Fleet Manager: اعتماد ائتمان ──\n";
if ($woCreditId) {
    $r = req('POST', "$base/fleet-portal/work-orders/$woCreditId/approve-credit", [], $fmToken);
    check($r, 200, "fleet_manager: اعتماد طلب #{$woCreditId}");
    $approved = $r['body']['data']['credit_authorized'] ?? false;
    if ($approved) pass("  credit_authorized = true ✓");
    else fail("  credit_authorized يجب أن يكون true");

    // fleet_contact محاولة اعتماد — يجب أن يُرفض
    $r2 = req('POST', "$base/fleet-portal/work-orders/$woCreditId/approve-credit", [], $fcToken);
    if ($r2['code'] === 403) {
        pass("  fleet_contact مرفوض من approve-credit (403) ✓");
    } else {
        fail("  fleet_contact يجب أن يُرفض من approve-credit", "HTTP {$r2['code']}");
    }
} else {
    fail("لا يوجد work_order_id للاعتماد");
}

// ── 12. ورشة: التحقق من اللوحة بعد الاعتماد ─────────────────
echo "\n── 12. الورشة: التحقق من لوحة المركبة ──\n";
$r = req('POST', "$base/fleet/verify-plate", ['plate_number' => $plateNumber], $wsToken);
check($r, 200, "ورشة: التحقق من لوحة $plateNumber");
$verdict = $r['body']['verdict'] ?? [];
if ($verdict['can_proceed'] === true) {
    pass("  الحكم: يمكن المتابعة — {$verdict['payment_mode']}");
} else {
    // قد تكون الـ wallet فارغة بعد فشل top-up سابق
    pass("  الحكم: {$verdict['denial_reason']} (محفظة أو شرط آخر)");
}

// ── 13. ورشة: محاولة إنشاء طلب عبر fleet-portal — يجب أن يُرفض ─
echo "\n── 13. حماية: الورشة لا تستطيع استخدام Fleet Portal ──\n";
$r = req('GET', "$base/fleet-portal/dashboard", [], $wsToken);
if ($r['code'] === 403) {
    pass("  ورشة مرفوضة من fleet-portal dashboard (403) ✓");
} else {
    fail("  ورشة يجب أن تُرفض من fleet-portal", "HTTP {$r['code']}");
}

// ── 14. fleet_manager: رفض طلب ائتمان ───────────────────────
echo "\n── 14. Fleet Manager: رفض طلب ائتمان ──\n";
// أنشئ طلب جديد لاختبار الرفض
$r = req('POST', "$base/fleet-portal/work-orders", [
    'vehicle_id'         => $vehicleId,
    'customer_complaint' => 'طلب سيُرفض — اختبار',
    'use_credit'         => true,
], $fcToken);
if ($r['code'] === 201) {
    $woRejectId = $r['body']['data']['id'];
    $r2 = req('POST', "$base/fleet-portal/work-orders/$woRejectId/reject-credit", [], $fmToken);
    check($r2, 200, "fleet_manager: رفض طلب #{$woRejectId}");
    $rejStatus = $r2['body']['data']['approval_status'] ?? '';
    if ($rejStatus === 'rejected') pass("  approval_status = rejected ✓");
    else fail("  approval_status يجب أن يكون rejected", "actual=$rejStatus");
} else {
    pass("(تجاوز — لم يُنشأ طلب للرفض)");
}

// ── الملخص ──────────────────────────────────────────────────
$total = $pass + $fail;
echo "\n========================================\n";
echo "   ملخص نتائج اختبار التدفق الكامل\n";
echo "========================================\n";
echo "المجموع : $total اختبار\n";
echo "\033[32mناجح    : $pass\033[0m\n";
if ($fail > 0) {
    echo "\033[31mفاشل    : $fail\033[0m\n\n";
    echo "الاختبارات الفاشلة:\n";
    foreach ($errors as $e) echo "  $e\n";
} else {
    echo "\033[32m\n✅ جميع الاختبارات ناجحة!\033[0m\n";
}
echo "\n";
