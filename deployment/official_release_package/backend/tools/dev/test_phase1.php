<?php

/**
 * اختبار شامل للمرحلة 1: النظام المالي المحصن
 */

$base = 'http://172.19.0.2/api/v1';
$pass = 0; $fail = 0; $results = [];

function req(string $method, string $url, array $payload = [], array $headers = []): array {
    $ch = curl_init($url);
    $allHeaders = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $allHeaders,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if ($payload && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => json_decode($body, true) ?? $body];
}

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail, $results;
    if ($ok) { $pass++; $results[] = "✅ PASS  $label"; }
    else      { $fail++; $results[] = "❌ FAIL  $label — $detail"; }
}

echo "\n" . str_repeat('=', 55) . "\n";
echo "   اختبار المرحلة 1 — النظام المالي المحصن\n";
echo str_repeat('=', 55) . "\n\n";

// ── 1. تسجيل الدخول
echo "── 1. المصادقة ──\n";
$r = req('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
$token = $r['body']['token'] ?? null;
check('تسجيل الدخول', $token !== null, json_encode($r['body']));
if (!$token) { echo "\nلا يمكن المتابعة بدون Token.\n"; exit(1); }
$auth = ["Authorization: Bearer $token"];

// ── 2. دليل الحسابات
echo "\n── 2. دليل الحسابات ──\n";
$r = req('GET', "$base/chart-of-accounts", [], $auth);
check('قراءة دليل الحسابات', $r['status'] === 200, "HTTP {$r['status']}");

$accounts = [];
if ($r['status'] === 200) {
    $accounts = $r['body']['data']['data'] ?? $r['body']['data'] ?? [];
}
check('وجود 20+ حساب أساسي', count($accounts) >= 20, count($accounts) . ' حساب فقط');

// ── 3. التحقق من وجود منتج
echo "\n── 3. المنتجات ──\n";
$pList = req('GET', "$base/products?per_page=5", [], $auth);
$productsList = $pList['body']['data']['data'] ?? $pList['body']['data'] ?? [];
$productId = $productsList[0]['id'] ?? null;
check('وجود منتجات', $productId !== null, "HTTP {$pList['status']} | count=" . count($productsList));

// ── 4. إنشاء فاتورة وتحقق من القيد المحاسبي
echo "\n── 4. الفواتير + القيود المحاسبية ──\n";

if ($productId) {
    $idem = 'inv-m1-' . md5(microtime(true) . getmypid() . rand());
    $r = req('POST', "$base/invoices", [
        'items'           => [['product_id' => $productId, 'name' => 'Test Item', 'quantity' => 2, 'unit_price' => 100.0, 'tax_rate' => 15]],
        'customer_type'   => 'b2c',
        'idempotency_key' => $idem,
    ], array_merge($auth, ["Idempotency-Key: $idem"]));

    check('إنشاء فاتورة', in_array($r['status'], [200, 201]), "HTTP {$r['status']} — " . substr(json_encode($r['body']), 0, 200));
    $invoiceId = $r['body']['data']['id'] ?? null;

    if ($invoiceId) {
        sleep(1);
        $lr = req('GET', "$base/ledger?per_page=5", [], $auth);
        $entries = $lr['body']['data']['data'] ?? $lr['body']['data'] ?? [];
        check('إنشاء قيد محاسبي تلقائياً', count($entries) > 0, 'لا توجد قيود');

        if (count($entries) > 0) {
            $entry = $entries[0];
            check('القيد متوازن (مدين = دائن)', abs($entry['total_debit'] - $entry['total_credit']) < 0.01,
                "Debit: {$entry['total_debit']} | Credit: {$entry['total_credit']}");
        }
    }
} else {
    check('إنشاء فاتورة', false, 'لا يوجد منتج');
    check('إنشاء قيد محاسبي', false, 'لا يوجد منتج');
}

// ── 5. دفتر الأستاذ
echo "\n── 5. دفتر الأستاذ ──\n";
$r = req('GET', "$base/ledger", [], $auth);
check('قراءة دفتر الأستاذ', $r['status'] === 200, "HTTP {$r['status']}");

$r = req('GET', "$base/ledger/trial-balance", [], $auth);
check('ميزان المراجعة', $r['status'] === 200, "HTTP {$r['status']}");
$trialData = $r['body']['data'] ?? [];
check('ميزان المراجعة يحتوي بيانات', is_array($trialData), 'ليس مصفوفة');

// ── 6. Immutability — DB Trigger
echo "\n── 6. اختبار الـ Immutability ──\n";
$lr2 = req('GET', "$base/ledger?per_page=1", [], $auth);
$entries2  = $lr2['body']['data']['data'] ?? $lr2['body']['data'] ?? [];
$firstEntry = $entries2[0] ?? null;

if ($firstEntry) {
    $entryId = $firstEntry['id'];
    // لا يجب أن يكون هناك endpoint PUT للقيود
    $rUpdate = req('PUT', "$base/ledger/$entryId", ['description' => 'hacked'], $auth);
    check('منع تعديل القيود — لا endpoint PUT', in_array($rUpdate['status'], [404, 405, 401, 403]), "HTTP {$rUpdate['status']}");

    // اختبار الـ Reversal
    $r = req('POST', "$base/ledger/$entryId/reverse", ['reason' => 'اختبار إلغاء القيد'], $auth);
    check('إنشاء قيد إلغاء (Reversal)', $r['status'] === 201, "HTTP {$r['status']} — " . substr(json_encode($r['body']), 0, 300));

    if ($r['status'] === 201) {
        // محاولة إلغاء نفس القيد مرتين (يجب أن يُرفض)
        $r2 = req('POST', "$base/ledger/$entryId/reverse", ['reason' => 'إلغاء مكرر'], $auth);
        check('منع الإلغاء المكرر', $r2['status'] !== 201, "HTTP {$r2['status']}");
    }
} else {
    check('منع تعديل القيود', false, 'لا توجد قيود للاختبار');
}

// ── 7. VAT Engine
echo "\n── 7. محرك الضريبة ──\n";
if ($productId) {
    $idem2 = 'vat-m1-' . md5(microtime(true) . getmypid() . rand() . 'vat');
    $r = req('POST', "$base/invoices", [
        'items' => [
            ['product_id' => $productId, 'name' => 'Standard VAT', 'quantity' => 1, 'unit_price' => 100.0, 'tax_rate' => 15],
            ['product_id' => $productId, 'name' => 'Zero Rated',   'quantity' => 1, 'unit_price' => 50.0,  'tax_rate' => 0],
        ],
        'customer_type'   => 'b2c',
        'idempotency_key' => $idem2,
    ], array_merge($auth, ["Idempotency-Key: $idem2"]));

    check('فاتورة بأسعار ضريبة مختلفة', in_array($r['status'], [200, 201]), "HTTP {$r['status']}");
    if (in_array($r['status'], [200, 201])) {
        $inv = $r['body']['data'];
        $expectedTax = 15.0; // فقط على المنتج الأول
        check('حساب الضريبة صحيح', abs((float)$inv['tax_amount'] - $expectedTax) < 0.01,
            "Expected: 15.00 | Got: {$inv['tax_amount']}");
    }
}

// ── ملخص
echo "\n" . str_repeat('=', 55) . "\n";
echo "   ملخص النتائج\n";
echo str_repeat('=', 55) . "\n";
echo "المجموع : " . ($pass + $fail) . " اختبار\n";
echo "ناجح    : $pass\n";
echo "فاشل    : $fail\n";
if ($fail > 0) {
    echo "\nالاختبارات الفاشلة:\n";
}
echo "\n";
foreach ($results as $r) { echo "$r\n"; }
echo "\n";
