<?php
/**
 * seed_all_data.php — ملف بذر البيانات التجريبية عبر API
 */
$base = 'http://saas_nginx/api/v1';

function req(string $method, string $url, array $body = [], array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => array_merge(
            ['Content-Type: application/json', 'Accept: application/json'],
            $headers
        ),
    ]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($raw, true) ?? [], 'raw' => $raw];
}

echo "=== بذر البيانات التجريبية ===\n\n";

// 1. تسجيل الدخول
$login = req('POST', "$base/auth/login", ['email' => 'owner@demo.sa', 'password' => 'Password123!']);
if ($login['code'] !== 200) {
    die("❌ تعذّر تسجيل الدخول: " . $login['raw'] . "\n");
}
$token = $login['body']['token'] ?? $login['body']['data']['token'] ?? null;
if (!$token) die("❌ لم يتم العثور على التوكن\n");
echo "✅ تسجيل الدخول: نجح\n";
$auth = ["Authorization: Bearer $token"];

// 2. إنشاء المنتجات/الخدمات
$services = [
    ['name' => 'تغيير زيت المحرك',    'sku' => 'SVC-001', 'price' => 150,  'cost' => 80,  'type' => 'service', 'tax_rate' => 15],
    ['name' => 'فحص شامل للمركبة',    'sku' => 'SVC-002', 'price' => 300,  'cost' => 120, 'type' => 'service', 'tax_rate' => 15],
    ['name' => 'تغيير فلتر الهواء',  'sku' => 'SVC-003', 'price' => 80,   'cost' => 30,  'type' => 'service', 'tax_rate' => 15],
    ['name' => 'تبديل البطارية',      'sku' => 'SVC-004', 'price' => 450,  'cost' => 200, 'type' => 'service', 'tax_rate' => 15],
    ['name' => 'صيانة نظام الفرامل', 'sku' => 'SVC-005', 'price' => 600,  'cost' => 300, 'type' => 'service', 'tax_rate' => 15],
    ['name' => 'تكييف - تعبئة فريون', 'sku' => 'SVC-006', 'price' => 200,  'cost' => 90,  'type' => 'service', 'tax_rate' => 15],
    ['name' => 'زيت موتول 5W40',      'sku' => 'PRD-001', 'price' => 120,  'cost' => 70,  'type' => 'product', 'tax_rate' => 15, 'track_inventory' => true, 'quantity' => 50],
    ['name' => 'فلتر زيت',            'sku' => 'PRD-002', 'price' => 35,   'cost' => 15,  'type' => 'product', 'tax_rate' => 15, 'track_inventory' => true, 'quantity' => 100],
];

$productIds = [];
echo "\n--- المنتجات والخدمات ---\n";
foreach ($services as $svc) {
    $r = req('POST', "$base/products", $svc, $auth);
    if (in_array($r['code'], [200, 201])) {
        $id = $r['body']['data']['id'] ?? $r['body']['id'] ?? null;
        $productIds[$svc['sku']] = $id;
        echo "  ✅ {$svc['name']} (#{$id})\n";
    } elseif ($r['code'] === 422) {
        // قد يكون موجوداً مسبقاً - جلبه
        $list = req('GET', "$base/products?search={$svc['sku']}", [], $auth);
        $id   = $list['body']['data'][0]['id'] ?? null;
        $productIds[$svc['sku']] = $id;
        echo "  ♻️  {$svc['name']} موجود مسبقاً (#{$id})\n";
    } else {
        echo "  ❌ {$svc['name']}: HTTP {$r['code']}\n";
    }
}

// 3. إنشاء العملاء
$customersData = [
    ['name' => 'أحمد العتيبي',     'phone' => '0501111111', 'email' => 'ahmed@test.sa',    'type' => 'individual'],
    ['name' => 'محمد الحربي',      'phone' => '0502222222', 'email' => 'mohammed@test.sa', 'type' => 'individual'],
    ['name' => 'فهد القحطاني',    'phone' => '0503333333', 'email' => 'fahad@test.sa',    'type' => 'individual'],
    ['name' => 'عبدالله الغامدي', 'phone' => '0504444444', 'email' => 'abdulla@test.sa',  'type' => 'individual'],
    ['name' => 'شركة الفيصل',     'phone' => '0555555555', 'email' => 'info@alfaisal.sa', 'type' => 'company', 'vat_number' => '300123456789003'],
];

$customerIds = [];
echo "\n--- العملاء ---\n";
foreach ($customersData as $cust) {
    $r = req('POST', "$base/customers", $cust, $auth);
    if (in_array($r['code'], [200, 201])) {
        $id = $r['body']['data']['id'] ?? $r['body']['id'] ?? null;
        $customerIds[] = $id;
        echo "  ✅ {$cust['name']} (#{$id})\n";
    } elseif ($r['code'] === 422) {
        $list = req('GET', "$base/customers?search=" . urlencode($cust['phone']), [], $auth);
        $id   = $list['body']['data'][0]['id'] ?? null;
        $customerIds[] = $id;
        echo "  ♻️  {$cust['name']} موجود (#{$id})\n";
    } else {
        echo "  ❌ {$cust['name']}: HTTP {$r['code']} — " . substr($r['raw'], 0, 100) . "\n";
    }
}

// 4. إنشاء المركبات
$vehiclesData = [
    ['plate_number' => 'أبج1234', 'make' => 'Toyota',  'model' => 'Camry',       'year' => 2022, 'color' => 'أبيض', 'customer_idx' => 0],
    ['plate_number' => 'ده و5678','make' => 'Nissan',  'model' => 'Altima',      'year' => 2021, 'color' => 'فضي',  'customer_idx' => 1],
    ['plate_number' => 'زحط9012', 'make' => 'Hyundai', 'model' => 'Sonata',      'year' => 2023, 'color' => 'أسود', 'customer_idx' => 2],
    ['plate_number' => 'يكل3456', 'make' => 'Toyota',  'model' => 'LandCruiser', 'year' => 2020, 'color' => 'أبيض', 'customer_idx' => 3],
    ['plate_number' => 'منس7890', 'make' => 'Ford',    'model' => 'F-150',       'year' => 2022, 'color' => 'أحمر', 'customer_idx' => 4],
];

$vehicleIds = [];
echo "\n--- المركبات ---\n";
foreach ($vehiclesData as $veh) {
    $custId = $customerIds[$veh['customer_idx']] ?? null;
    if (!$custId) { echo "  ⚠️  لا يوجد عميل للمركبة {$veh['plate_number']}\n"; continue; }
    $payload = array_merge($veh, ['customer_id' => $custId]);
    unset($payload['customer_idx']);
    $r = req('POST', "$base/vehicles", $payload, $auth);
    if (in_array($r['code'], [200, 201])) {
        $id = $r['body']['data']['id'] ?? $r['body']['id'] ?? null;
        $vehicleIds[] = $id;
        echo "  ✅ {$veh['make']} {$veh['model']} - {$veh['plate_number']} (#{$id})\n";
    } elseif ($r['code'] === 422) {
        $list = req('GET', "$base/vehicles?search=" . urlencode($veh['plate_number']), [], $auth);
        $id   = $list['body']['data'][0]['id'] ?? null;
        $vehicleIds[] = $id;
        echo "  ♻️  {$veh['make']} {$veh['model']} موجود (#{$id})\n";
    } else {
        echo "  ❌ {$veh['plate_number']}: HTTP {$r['code']} — " . substr($r['raw'], 0, 100) . "\n";
    }
}

// 5. إنشاء أوامر العمل
$workOrdersData = [
    ['vehicle_idx' => 0, 'customer_idx' => 0, 'description' => 'تغيير زيت المحرك وفلتر الزيت',     'status' => 'completed', 'total' => 185],
    ['vehicle_idx' => 1, 'customer_idx' => 1, 'description' => 'فحص شامل للمركبة وصيانة دورية',    'status' => 'completed', 'total' => 345],
    ['vehicle_idx' => 2, 'customer_idx' => 2, 'description' => 'صيانة نظام الفرامل الأمامي',       'status' => 'in_progress','total' => 600],
    ['vehicle_idx' => 3, 'customer_idx' => 3, 'description' => 'تبديل البطارية وفحص الكهرباء',    'status' => 'pending',    'total' => 450],
    ['vehicle_idx' => 4, 'customer_idx' => 4, 'description' => 'صيانة نظام تكييف الهواء',         'status' => 'completed', 'total' => 230],
];

$workOrderIds = [];
echo "\n--- أوامر العمل ---\n";
foreach ($workOrdersData as $wo) {
    $vid  = $vehicleIds[$wo['vehicle_idx']]  ?? null;
    $cid  = $customerIds[$wo['customer_idx']] ?? null;
    if (!$vid || !$cid) { echo "  ⚠️  بيانات ناقصة لأمر العمل\n"; continue; }
    $payload = [
        'vehicle_id'  => $vid,
        'customer_id' => $cid,
        'description' => $wo['description'],
        'status'      => $wo['status'],
        'total_amount'=> $wo['total'],
        'priority'    => 'normal',
    ];
    $r = req('POST', "$base/work-orders", $payload, $auth);
    if (in_array($r['code'], [200, 201])) {
        $id = $r['body']['data']['id'] ?? $r['body']['id'] ?? null;
        $workOrderIds[] = ['id' => $id, 'customer_id' => $cid, 'vehicle_id' => $vid, 'total' => $wo['total'], 'status' => $wo['status']];
        echo "  ✅ {$wo['description']} (#{$id})\n";
    } else {
        echo "  ❌ {$wo['description']}: HTTP {$r['code']}\n";
        $workOrderIds[] = null;
    }
}

// 6. إنشاء الفواتير
echo "\n--- الفواتير ---\n";
$invoiceStatuses = ['paid', 'paid', 'draft', 'pending', 'paid'];
foreach ($workOrderIds as $i => $wo) {
    if (!$wo) continue;
    $subtotal = round($wo['total'] / 1.15, 2);
    $tax      = round($wo['total'] - $subtotal, 2);
    $status   = $invoiceStatuses[$i] ?? 'draft';
    $payload  = [
        'customer_id'   => $wo['customer_id'],
        'work_order_id' => $wo['id'],
        'type'          => 'tax_invoice',
        'status'        => $status,
        'subtotal'      => $subtotal,
        'tax_amount'    => $tax,
        'total_amount'  => (float)$wo['total'],
        'paid_amount'   => $status === 'paid' ? (float)$wo['total'] : 0,
        'currency'      => 'SAR',
        'items'         => [
            ['description' => 'خدمة صيانة', 'quantity' => 1, 'unit_price' => $subtotal, 'tax_rate' => 15]
        ],
    ];
    $r = req('POST', "$base/invoices", $payload, $auth);
    if (in_array($r['code'], [200, 201])) {
        $id  = $r['body']['data']['id']             ?? $r['body']['id'] ?? null;
        $num = $r['body']['data']['invoice_number'] ?? $r['body']['invoice_number'] ?? '?';
        echo "  ✅ فاتورة #{$num} - {$wo['total']} ر.س - {$status} (#{$id})\n";
    } else {
        echo "  ❌ فاتورة: HTTP {$r['code']} — " . substr($r['raw'], 0, 150) . "\n";
    }
}

echo "\n=== اكتمل البذر ===\n";
echo "العملاء: "  . count(array_filter($customerIds)) . "\n";
echo "المركبات: " . count(array_filter($vehicleIds))  . "\n";
echo "أوامر العمل: " . count(array_filter($workOrderIds)) . "\n";
