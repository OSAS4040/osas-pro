#!/usr/bin/env php
<?php
/**
 * سكريبت اختبار شامل لجميع خصائص النظام
 */

$base = 'http://172.19.0.8/api/v1';
$token = null;
$results = [];
$errors  = [];

function req(string $method, string $url, array $data = [], string $token = null): array {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer $token";

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

function pass(string $test): void {
    global $results;
    $results[] = ['status' => 'PASS', 'test' => $test];
    echo "\033[32m✅ PASS\033[0m  $test\n";
}

function fail(string $test, string $reason): void {
    global $results, $errors;
    $results[] = ['status' => 'FAIL', 'test' => $test, 'reason' => $reason];
    $errors[]  = $test;
    echo "\033[31m❌ FAIL\033[0m  $test — $reason\n";
}

echo "\n========================================\n";
echo "   اختبار شامل لنظام SaaS POS\n";
echo "========================================\n\n";

// ─── 1. Health Check ─────────────────────────────
echo "── 1. فحص الصحة ──\n";
$r = req('GET', "http://nginx/api/v1/health");
if ($r['status'] === 200 && ($r['body']['status'] ?? '') === 'healthy') {
    pass('Health Check');
    pass('Database Connection: ' . ($r['body']['checks']['database'] ?? '?'));
    pass('Redis Connection: '    . ($r['body']['checks']['redis'] ?? '?'));
} else {
    fail('Health Check', "HTTP {$r['status']}");
}

// ─── 2. المصادقة ─────────────────────────────────
echo "\n── 2. المصادقة ──\n";
$r = req('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
$token = $r['body']['data']['token'] ?? $r['body']['token'] ?? null;
if ($r['status'] === 200 && $token) {
    pass('تسجيل الدخول - owner@demo.sa');
} else {
    fail('تسجيل الدخول', "HTTP {$r['status']} — " . json_encode($r['body']));
    die("\nلا يمكن المتابعة بدون Token.\n");
}

// اختبار بيانات خاطئة
$r = req('POST', "$base/auth/login", ['email' => 'wrong@test.com', 'password' => 'wrong']);
($r['status'] === 401 || $r['status'] === 422) ? pass('رفض بيانات خاطئة') : fail('رفض بيانات خاطئة', "HTTP {$r['status']}");

// ─── 3. المنتجات CRUD ────────────────────────────
echo "\n── 3. المنتجات ──\n";
$r = req('GET', "$base/products?per_page=5", [], $token);
$r['status'] === 200 ? pass('قراءة قائمة المنتجات') : fail('قراءة قائمة المنتجات', "HTTP {$r['status']}");

// إنشاء منتج
$newProduct = ['name' => 'منتج تجريبي '.time(), 'sale_price' => 99.99, 'tax_rate' => 15, 'is_taxable' => true, 'track_inventory' => true, 'product_type' => 'physical'];
$r = req('POST', "$base/products", $newProduct, $token);
if ($r['status'] === 201 || $r['status'] === 200) {
    $productId = $r['body']['data']['id'] ?? null;
    pass("إنشاء منتج جديد (ID: $productId)");
} else {
    fail('إنشاء منتج', "HTTP {$r['status']} — " . json_encode($r['body']));
    $productId = null;
}

if ($productId) {
    // قراءة
    $r = req('GET', "$base/products/$productId", [], $token);
    $r['status'] === 200 ? pass("قراءة تفاصيل منتج #$productId") : fail("قراءة منتج #$productId", "HTTP {$r['status']}");

    // تعديل
    $r = req('PUT', "$base/products/$productId", ['name' => 'منتج معدَّل', 'sale_price' => 150.00], $token);
    $r['status'] === 200 ? pass("تعديل منتج #$productId") : fail("تعديل منتج #$productId", "HTTP {$r['status']}");

    // حذف
    $r = req('DELETE', "$base/products/$productId", [], $token);
    ($r['status'] === 200 || $r['status'] === 204) ? pass("حذف منتج #$productId") : fail("حذف منتج #$productId", "HTTP {$r['status']}");
}

// بحث
$r = req('GET', "$base/products?search=test&per_page=5", [], $token);
$r['status'] === 200 ? pass('البحث في المنتجات') : fail('البحث في المنتجات', "HTTP {$r['status']}");

// ─── 4. المخزون ──────────────────────────────────
echo "\n── 4. المخزون ──\n";
$r = req('GET', "$base/inventory?per_page=5", [], $token);
$r['status'] === 200 ? pass('قراءة المخزون') : fail('قراءة المخزون', "HTTP {$r['status']} — " . json_encode($r['body']['message'] ?? $r['body']));

$r = req('GET', "$base/inventory?low_stock=1", [], $token);
$r['status'] === 200 ? pass('فلتر المخزون المنخفض') : fail('فلتر المخزون المنخفض', "HTTP {$r['status']} — " . json_encode($r['body']['message'] ?? $r['body']));

$r = req('GET', "$base/units", [], $token);
$r['status'] === 200 ? pass('قراءة وحدات القياس') : fail('قراءة وحدات القياس', "HTTP {$r['status']}");

// ─── 5. العملاء ──────────────────────────────────
echo "\n── 5. العملاء ──\n";
$r = req('GET', "$base/customers?per_page=5", [], $token);
$r['status'] === 200 ? pass('قراءة العملاء') : fail('قراءة العملاء', "HTTP {$r['status']}");

// ─── 6. الموردون ─────────────────────────────────
echo "\n── 6. الموردون ──\n";
$r = req('GET', "$base/suppliers?per_page=5", [], $token);
$r['status'] === 200 ? pass('قراءة الموردين') : fail('قراءة الموردين', "HTTP {$r['status']}");

// ─── 7. المركبات ─────────────────────────────────
echo "\n── 7. المركبات ──\n";
$r = req('GET', "$base/vehicles?per_page=5", [], $token);
$r['status'] === 200 ? pass('قراءة المركبات') : fail('قراءة المركبات', "HTTP {$r['status']}");

// ─── 8. أوامر العمل ──────────────────────────────
echo "\n── 8. أوامر العمل ──\n";
$r = req('GET', "$base/work-orders?per_page=5", [], $token);
$r['status'] === 200 ? pass('قراءة أوامر العمل') : fail('قراءة أوامر العمل', "HTTP {$r['status']}");

// ─── 9. الفواتير ─────────────────────────────────
echo "\n── 9. الفواتير ──\n";
$r = req('GET', "$base/invoices?per_page=5", [], $token);
$r['status'] === 200 ? pass('قراءة الفواتير') : fail('قراءة الفواتير', "HTTP {$r['status']}");

// ─── 10. POS - عملية بيع كاملة ───────────────────
echo "\n── 10. نقطة البيع (POS) ──\n";

// أولاً: نحتاج منتج موجود
$r = req('GET', "$base/products?per_page=1&is_active=true", [], $token);
$firstProduct = $r['body']['data']['data'][0] ?? $r['body']['data'][0] ?? null;

if ($firstProduct) {
    $salePayload = [
        'customer_id'     => null,
        'customer_type'   => 'b2c',
        'discount_amount' => 0,
        'items'           => [[
            'name'       => $firstProduct['name'],
            'item_type'  => 'part',
            'product_id' => $firstProduct['id'],
            'service_id' => null,
            'unit_price' => floatval($firstProduct['sale_price'] ?? $firstProduct['price'] ?? 10),
            'tax_rate'   => floatval($firstProduct['tax_rate'] ?? 15),
            'quantity'   => 1,
        ]],
        'payment' => ['method' => 'cash', 'amount' => floatval($firstProduct['sale_price'] ?? $firstProduct['price'] ?? 10) * 1.15],
    ];

    $r = req('POST', "$base/pos/sale", $salePayload, $token);
    if ($r['status'] === 200 || $r['status'] === 201) {
        $invoiceNum = $r['body']['data']['invoice_number'] ?? '?';
        pass("عملية بيع ناجحة — فاتورة: $invoiceNum");

        $invoiceId = $r['body']['data']['id'] ?? null;
        if ($invoiceId) {
            $r2 = req('GET', "$base/invoices/$invoiceId", [], $token);
            $r2['status'] === 200 ? pass("عرض الفاتورة #$invoiceId") : fail("عرض الفاتورة #$invoiceId", "HTTP {$r2['status']}");
        }
    } else {
        fail('عملية POS', "HTTP {$r['status']} — " . json_encode($r['body']));
    }
} else {
    fail('عملية POS', 'لا توجد منتجات نشطة للاختبار');
}

// ─── 11. التقارير ─────────────────────────────────
echo "\n── 11. التقارير ──\n";
$r = req('GET', "$base/reports/sales?from=2024-01-01&to=2026-12-31", [], $token);
$r['status'] === 200 ? pass('تقرير المبيعات') : fail('تقرير المبيعات', "HTTP {$r['status']}");

// ─── 12. تسجيل الخروج ─────────────────────────────
echo "\n── 12. تسجيل الخروج ──\n";
$r = req('POST', "$base/auth/logout", [], $token);
($r['status'] === 200 || $r['status'] === 204) ? pass('تسجيل الخروج') : fail('تسجيل الخروج', "HTTP {$r['status']}");

// ─── ملخص النتائج ────────────────────────────────
$total  = count($results);
$passed = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$failed = $total - $passed;

echo "\n========================================\n";
echo "   ملخص نتائج الاختبار\n";
echo "========================================\n";
echo "المجموع : $total اختبار\n";
echo "\033[32mناجح    : $passed\033[0m\n";
echo "\033[31mفاشل    : $failed\033[0m\n";

if ($failed > 0) {
    echo "\nالاختبارات الفاشلة:\n";
    foreach ($results as $r) {
        if ($r['status'] === 'FAIL') {
            echo "  ❌ {$r['test']} — {$r['reason']}\n";
        }
    }
}

echo "\n";
exit($failed > 0 ? 1 : 0);
