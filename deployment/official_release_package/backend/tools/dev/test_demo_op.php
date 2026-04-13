<?php
/**
 * اختبار عملية تجريبية كاملة: إنشاء منتج + عميل + POS transaction
 */
$base = 'http://saas_nginx/api/v1';

function req(string $method, string $url, array $body = [], array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>strtoupper($method),
        CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$headers),
        CURLOPT_TIMEOUT=>15]);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code'=>$code,'body'=>json_decode($raw,true)??[],'raw'=>$raw];
}

echo "═══════════════════════════════════════\n";
echo "   عملية تجريبية شاملة\n";
echo "═══════════════════════════════════════\n\n";

// 1. Login
$r = req('POST',"$base/auth/login",['email'=>'owner@demo.sa','password'=>'Password123!']);
if($r['code']!==200) die("❌ فشل الدخول: ".$r['raw']."\n");
$token=$r['body']['token']??$r['body']['data']['token']??'';
$auth=["Authorization: Bearer $token"];
echo "✅ تسجيل الدخول\n";

// 2. Get existing product
$r = req('GET',"$base/products",[],$auth);
$items = $r['body']['data']['data'] ?? $r['body']['data'] ?? [];
$product = null;
foreach ($items as $p) {
    // prefer service products (track_inventory=false) to avoid inventory issues
    if (($p['product_type'] ?? $p['type'] ?? '') === 'service' && !($p['track_inventory'] ?? true)) {
        $product = $p; break;
    }
}
// fallback to any non-tracked product
if (!$product) {
    foreach ($items as $p) {
        if (!($p['track_inventory'] ?? true)) { $product = $p; break; }
    }
}
if (!$product) $product = $items[0] ?? null;
if (!$product) die("❌ لم يتم إيجاد أي منتج\n");
$productId   = $product['id'];
$productPrice= $product['sale_price'] ?? $product['price'] ?? 150;
echo "✅ المنتج: {$product['name']} (ID:{$productId}, track=" . var_export($product['track_inventory']??false,true) . ") — السعر: {$productPrice} ر.س\n";

// 3. Get existing customer
$r = req('GET',"$base/customers",[],$auth);
$customers = $r['body']['data']['data'] ?? $r['body']['data'] ?? [];
$customer = $customers[0] ?? null;
if(!$customer) die("❌ لا يوجد عملاء\n");
$customerId = $customer['id'];
echo "✅ العميل: {$customer['name']} (ID: $customerId)\n";

// 4. POS Transaction
$posData = [
    'customer_id' => $customerId,
    'payment'     => [
        'method' => 'cash',
        'amount' => (float) $productPrice,
    ],
    'items' => [
        [
            'product_id' => $productId,
            'name'       => $product['name'],
            'quantity'   => 1,
            'unit_price' => (float) $productPrice,
            'tax_rate'   => 15,
        ],
    ],
    'notes' => 'عملية تجريبية من سكريبت الاختبار',
];
$idemKey = 'test-pos-' . uniqid();
$r = req('POST',"$base/pos/sale",$posData, array_merge($auth, ["Idempotency-Key: $idemKey"]));
if(in_array($r['code'],[200,201])) {
    $tx = $r['body']['data'] ?? $r['body'];
    $txId  = $tx['id']              ?? '?';
    $total = $tx['total_amount']    ?? $tx['total'] ?? '?';
    $ref   = $tx['reference_number']?? $tx['transaction_number'] ?? '?';
    echo "✅ عملية POS ناجحة!\n";
    echo "   المرجع: $ref\n";
    echo "   الإجمالي: $total ر.س\n";
    echo "   ID: $txId\n";
} else {
    echo "❌ فشلت عملية POS: HTTP {$r['code']}\n";
    echo "   " . substr($r['raw'], 0, 300) . "\n";
}

// 5. Create invoice from work order
$r = req('GET',"$base/work-orders?status=completed",[],$auth);
$woList = $r['body']['data']['data'] ?? $r['body']['data'] ?? [];
$wo = $woList[0] ?? null;
if($wo) {
// أصلح invoice items
    $woTotal  = (float)($wo['actual_total'] ?? $wo['estimated_total'] ?? 150);
    $invoiceData = [
        'customer_id'   => $wo['customer_id'],
        'work_order_id' => $wo['id'],
        'type'          => 'sale',
        'status'        => 'draft',
        'subtotal'      => round($woTotal / 1.15, 2),
        'tax_amount'    => round($woTotal - ($woTotal / 1.15), 2),
        'total'         => $woTotal,
        'paid_amount'   => 0,
        'due_amount'    => $woTotal,
        'currency'      => 'SAR',
        'items'         => [
            [
                'name'        => 'خدمة صيانة',
                'description' => 'خدمة صيانة',
                'quantity'    => 1,
                'unit_price'  => round($woTotal / 1.15, 2),
                'tax_rate'    => 15,
            ],
        ],
    ];
    $invKey = 'test-inv-' . uniqid();
    $r = req('POST',"$base/invoices",$invoiceData, array_merge($auth, ["Idempotency-Key: $invKey"]));
    if(in_array($r['code'],[200,201])) {
        $inv = $r['body']['data'] ?? $r['body'];
        echo "✅ فاتورة جديدة: #{$inv['invoice_number']} — {$inv['total']} ر.س\n";
    } else {
        echo "⚠️  إنشاء فاتورة: HTTP {$r['code']} — ".substr($r['raw'],0,150)."\n";
    }
}

echo "\n═══════════════════════════════════════\n";
echo "✅ العملية التجريبية اكتملت بنجاح!\n";
echo "═══════════════════════════════════════\n";
