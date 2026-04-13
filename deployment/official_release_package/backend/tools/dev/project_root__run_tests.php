<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use App\Enums\StockMovementType;

$results = [];

function test(string $section, string $name, callable $fn): mixed {
    global $results;
    try {
        $val = $fn();
        $results[] = "✅ PASS  [$section] $name";
        return $val;
    } catch (\Throwable $e) {
        $results[] = "❌ FAIL  [$section] $name — " . $e->getMessage();
        return null;
    }
}

// ── get suppliers schema ──
$supplierCols = array_column(
    DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'suppliers' ORDER BY ordinal_position"),
    'column_name'
);

echo "\n";
echo "╔══════════════════════════════════════════════════╗\n";
echo "║    اختبار شامل: المخزون · الفواتير · العملاء     ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

$user = User::where('email', 'owner@demo.sa')->firstOrFail();
Auth::login($user);
app()->instance('trace_id', Str::uuid()->toString());
app()->instance('tenant_company_id', $user->company_id);

// Cleanup leftovers
DB::table('products')->where('sku', 'like', 'EXT-TEST-%')->where('company_id', $user->company_id)->orderBy('id')->each(function($p) {
    DB::table('stock_movements')->where('product_id', $p->id)->delete();
    DB::table('invoice_items')->where('product_id', $p->id)->delete();
    DB::table('inventory')->where('product_id', $p->id)->delete();
    DB::table('products')->where('id', $p->id)->delete();
});
DB::table('customers')->where('phone', 'like', '05-TEST-%')->where('company_id', $user->company_id)->delete();
DB::table('suppliers')->where('name', 'like', '%توريد اختبار%')->where('company_id', $user->company_id)->delete();
DB::table('idempotency_keys')->where('company_id', $user->company_id)->delete();

$unit = DB::table('units')->where('company_id', $user->company_id)->first();
$rand = rand(100, 999);

$productId = DB::table('products')->insertGetId([
    'uuid' => Str::uuid(), 'company_id' => $user->company_id,
    'name' => "منتج اختبار EXT-{$rand}", 'name_ar' => 'اختبار',
    'sku'  => "EXT-TEST-{$rand}", 'unit_id' => $unit->id,
    'unit' => $unit->name ?? 'قطعة', 'cost_price' => 40.00,
    'sale_price' => 75.00, 'tax_rate' => 15.00, 'is_taxable' => true,
    'is_active' => true, 'track_inventory' => true, 'product_type' => 'product',
    'version' => 1, 'created_at' => now(), 'updated_at' => now(),
]);
echo "▶ منتج اختبار: ID={$productId} (EXT-TEST-{$rand})\n";
echo "▶ أعمدة جدول suppliers: " . implode(', ', $supplierCols) . "\n\n";

// ═══════════════════════════════════════════════════
// ── 1. المخزون ──
// ═══════════════════════════════════════════════════
echo "══ 1. المخزون ══════════════════════════════════\n";
$inventoryService = app(\App\Services\InventoryService::class);

$movement1 = test('مخزون', 'إضافة مخزون (ManualAdd) عبر InventoryService', function() use ($inventoryService, $user, $productId) {
    $m = $inventoryService->addStock(
        companyId:     $user->company_id,
        branchId:      $user->branch_id,
        productId:     $productId,
        quantity:      200,
        userId:        $user->id,
        type:          StockMovementType::ManualAdd->value,
        traceId:       app('trace_id'),
        note:          'اختبار إضافة مخزون',
    );
    echo "   → حركة رقم {$m->id} | الكمية بعد: {$m->quantity_after}\n";
    return $m;
});

test('مخزون', 'التحقق من رصيد المخزون', function() use ($inventoryService, $user, $productId) {
    $level = $inventoryService->getStockLevel($user->company_id, $user->branch_id, $productId);
    if ($level['quantity'] < 200) throw new \Exception("الكمية غير صحيحة: {$level['quantity']}");
    echo "   → الكمية: {$level['quantity']} | المتاح: {$level['available']}\n";
});

$movement2 = test('مخزون', 'خصم مخزون (deductStock)', function() use ($inventoryService, $user, $productId) {
    $m = $inventoryService->deductStock(
        companyId: $user->company_id, branchId: $user->branch_id,
        productId: $productId, quantity: 30, userId: $user->id,
        referenceType: 'manual_test', referenceId: 1,
        traceId: app('trace_id'), note: 'خصم اختبار',
    );
    echo "   → بعد الخصم: {$m->quantity_after} وحدة\n";
    return $m;
});

test('مخزون', 'التحقق من الكمية بعد الخصم (200 - 30 = 170)', function() use ($inventoryService, $user, $productId) {
    $balance = $inventoryService->getBalance($user->company_id, $user->branch_id, $productId);
    if (abs($balance - 170) > 0.01) throw new \Exception("المتوقع 170، الفعلي: {$balance}");
    echo "   → الرصيد الصحيح: {$balance}\n";
});

test('مخزون', 'عكس الحركة (reverseMovement)', function() use ($inventoryService, $user, $productId, $movement2) {
    if (!$movement2) throw new \Exception("لا توجد حركة للعكس");
    $reversal = $inventoryService->reverseMovement($movement2, $user->id, app('trace_id'));
    $balance = $inventoryService->getBalance($user->company_id, $user->branch_id, $productId);
    if (abs($balance - 200) > 0.01) throw new \Exception("بعد العكس المتوقع 200، الفعلي: {$balance}");
    echo "   → الرصيد بعد العكس: {$balance} ✓\n";
});

test('مخزون', 'رفض الخصم عند نقص المخزون', function() use ($inventoryService, $user, $productId) {
    try {
        $inventoryService->deductStock(
            companyId: $user->company_id, branchId: $user->branch_id,
            productId: $productId, quantity: 9999, userId: $user->id,
            referenceType: 'test', referenceId: 0, traceId: app('trace_id'),
        );
        throw new \Exception("كان يجب أن يرفض!");
    } catch (\DomainException $e) {
        echo "   → رفض صحيح: " . substr($e->getMessage(), 0, 60) . "\n";
    }
});

test('مخزون', 'قراءة سجل الحركات (stock_movements)', function() use ($user, $productId) {
    $rows = DB::table('stock_movements')
        ->where('company_id', $user->company_id)
        ->where('product_id', $productId)
        ->orderBy('id')->get();
    echo "   → عدد الحركات: {$rows->count()}\n";
    foreach ($rows as $r)
        echo "     • [{$r->type}] qty={$r->quantity} ({$r->quantity_before}→{$r->quantity_after})\n";
});

// ═══════════════════════════════════════════════════
// ── 2. العملاء ──
// ═══════════════════════════════════════════════════
echo "\n══ 2. العملاء ══════════════════════════════════\n";

$customerId = test('عملاء', 'إنشاء عميل جديد', function() use ($user, $rand) {
    $id = DB::table('customers')->insertGetId([
        'uuid'       => Str::uuid(), 'company_id' => $user->company_id,
        'name'       => "أحمد اختبار {$rand}", 'phone' => "05-TEST-{$rand}",
        'email'      => "test{$rand}@example.com", 'type' => 'individual',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    echo "   → عميل ID={$id}\n";
    return $id;
});

test('عملاء', 'قراءة بيانات العميل', function() use ($user, $customerId) {
    if (!$customerId) throw new \Exception("لا يوجد عميل");
    $c = DB::table('customers')->where('id', $customerId)->first();
    echo "   → الاسم: {$c->name} | الهاتف: {$c->phone}\n";
});

test('عملاء', 'تعديل بيانات العميل', function() use ($user, $customerId) {
    if (!$customerId) throw new \Exception("لا يوجد عميل");
    DB::table('customers')->where('id', $customerId)->update(['name' => 'أحمد محمد المحدّث', 'updated_at' => now()]);
    $name = DB::table('customers')->where('id', $customerId)->value('name');
    if ($name !== 'أحمد محمد المحدّث') throw new \Exception("التعديل فشل");
    echo "   → الاسم الجديد: {$name}\n";
});

test('عملاء', 'البحث في العملاء', function() use ($user) {
    $count = DB::table('customers')->where('company_id', $user->company_id)
        ->where('name', 'like', '%محدّث%')->count();
    if ($count < 1) throw new \Exception("لم يُعثر على عميل");
    echo "   → نتائج البحث: {$count} عميل\n";
});

// ═══════════════════════════════════════════════════
// ── 3. الموردون ──
// ═══════════════════════════════════════════════════
echo "\n══ 3. الموردون ══════════════════════════════════\n";

$supplierId = test('موردون', 'إنشاء مورد جديد', function() use ($user, $rand, $supplierCols) {
    $data = [
        'company_id'          => $user->company_id,
        'created_by_user_id'  => $user->id,
        'name'                => "شركة توريد اختبار {$rand}",
        'created_at'          => now(), 'updated_at' => now(),
    ];
    if (in_array('uuid', $supplierCols)) $data['uuid'] = Str::uuid();
    if (in_array('phone', $supplierCols)) $data['phone'] = "05-TEST-S{$rand}";
    if (in_array('email', $supplierCols)) $data['email'] = "sup{$rand}@example.com";

    $id = DB::table('suppliers')->insertGetId($data);
    echo "   → مورد ID={$id}\n";
    return $id;
});

test('موردون', 'قراءة بيانات المورد', function() use ($user, $supplierId) {
    if (!$supplierId) throw new \Exception("لا يوجد مورد");
    $s = DB::table('suppliers')->where('id', $supplierId)->first();
    echo "   → الاسم: {$s->name}\n";
});

test('موردون', 'تعديل بيانات المورد', function() use ($user, $supplierId) {
    if (!$supplierId) throw new \Exception("لا يوجد مورد");
    DB::table('suppliers')->where('id', $supplierId)->update(['name' => 'شركة توريد محدّثة', 'updated_at' => now()]);
    $name = DB::table('suppliers')->where('id', $supplierId)->value('name');
    if ($name !== 'شركة توريد محدّثة') throw new \Exception("التعديل فشل");
    echo "   → الاسم المحدّث: {$name}\n";
});

// ═══════════════════════════════════════════════════
// ── 4. الفواتير ──
// ═══════════════════════════════════════════════════
echo "\n══ 4. الفواتير ══════════════════════════════════\n";
$invoiceService = app(\App\Services\InvoiceService::class);
$product = DB::table('products')->where('id', $productId)->first();

$invoiceId = test('فواتير', 'إنشاء فاتورة بيع عبر InvoiceService', function() use ($invoiceService, $user, $productId, $product) {
    $invoice = $invoiceService->createInvoice([
        'idempotency_key' => Str::uuid()->toString(),
        'type'            => 'sale',
        'customer_type'   => 'b2c',
        'items'           => [[
            'product_id' => $productId, 'name' => $product->name,
            'sku'        => $product->sku, 'quantity' => 3,
            'unit_price' => 75.00, 'cost_price' => 40.00, 'tax_rate' => 15,
        ]],
        'payment' => ['method' => 'cash', 'amount' => 258.75],
    ], $user->company_id, $user->branch_id, $user->id);
    echo "   → {$invoice->invoice_number} — الإجمالي: {$invoice->total} ريال\n";
    return $invoice->id;
});

test('فواتير', 'قراءة الفاتورة مع بنودها', function() use ($invoiceId) {
    if (!$invoiceId) throw new \Exception("لا توجد فاتورة");
    $inv = DB::table('invoices')->where('id', $invoiceId)->first();
    $items = DB::table('invoice_items')->where('invoice_id', $invoiceId)->get();
    if ($items->count() < 1) throw new \Exception("لا توجد بنود");
    echo "   → {$inv->invoice_number} | الإجمالي: {$inv->total} | البنود: {$items->count()}\n";
});

test('فواتير', 'التحقق من صحة سلسلة الهاش', function() use ($user) {
    $invoices = DB::table('invoices')->where('company_id', $user->company_id)->orderBy('invoice_counter')->get();
    $prev = hash('sha256', 'genesis');
    foreach ($invoices as $inv) {
        $expected = hash('sha256', $inv->invoice_number . number_format((float)$inv->total, 4, '.', '') . $prev);
        if ($inv->invoice_hash !== $expected) throw new \Exception("الهاش غير صحيح: {$inv->invoice_number}");
        $prev = $inv->invoice_hash;
    }
    echo "   → سلسلة الهاش سليمة لـ {$invoices->count()} فاتورة\n";
});

test('فواتير', 'فاتورة مرتبطة بعميل', function() use ($invoiceService, $user, $productId, $product, $customerId) {
    if (!$customerId) throw new \Exception("لا يوجد عميل");
    $invoice = $invoiceService->createInvoice([
        'type' => 'sale', 'customer_id' => $customerId, 'customer_type' => 'b2c',
        'items' => [[
            'product_id' => $productId, 'name' => $product->name,
            'sku' => $product->sku, 'quantity' => 1,
            'unit_price' => 75.00, 'cost_price' => 40.00, 'tax_rate' => 15,
        ]],
        'payment' => ['method' => 'cash', 'amount' => 86.25],
    ], $user->company_id, $user->branch_id, $user->id);
    if ($invoice->customer_id !== $customerId) throw new \Exception("العميل لم يُربط");
    echo "   → {$invoice->invoice_number} مرتبطة بعميل ID={$invoice->customer_id}\n";
});

// ═══════════════════════════════════════════════════
// ── 5. POS + مخزون معاً ──
// ═══════════════════════════════════════════════════
echo "\n══ 5. POS مع تتبع المخزون ══════════════════════\n";
$posService = app(\App\Services\POSService::class);
$balanceBefore = $inventoryService->getBalance($user->company_id, $user->branch_id, $productId);
echo "   ▶ المخزون قبل البيع: {$balanceBefore}\n";

test('POS+مخزون', 'بيع POS ويتأكد خصم المخزون تلقائياً', function() use ($posService, $inventoryService, $user, $productId, $product, $balanceBefore) {
    $qty  = 5;
    $iKey = Str::uuid()->toString();
    $invoice = $posService->sale([
        'idempotency_key' => $iKey,
        'items' => [[
            'product_id' => $productId, 'name' => $product->name,
            'sku'        => $product->sku, 'quantity' => $qty,
            'unit_price' => 75.00, 'cost_price' => 40.00, 'tax_rate' => 15,
        ]],
        'payment' => ['method' => 'cash', 'amount' => 431.25],
    ], $user->company_id, $user->branch_id, $user->id, $iKey);

    $balanceAfter = $inventoryService->getBalance($user->company_id, $user->branch_id, $productId);
    $expected = $balanceBefore - $qty;
    if (abs($balanceAfter - $expected) > 0.01)
        throw new \Exception("المتوقع {$expected}، الفعلي {$balanceAfter}");
    echo "   → {$invoice->invoice_number} | المخزون: {$balanceBefore} → {$balanceAfter} (-{$qty}) ✓\n";
});

// ═══════════════════════════════════════════════════
// ── 6. التقارير ──
// ═══════════════════════════════════════════════════
echo "\n══ 6. التقارير الأساسية ══════════════════════\n";

test('تقارير', 'إجمالي مبيعات الشركة', function() use ($user) {
    $total = DB::table('invoices')->where('company_id', $user->company_id)->where('type', 'sale')->sum('total');
    echo "   → إجمالي المبيعات: {$total} ريال\n";
});

test('تقارير', 'عدد الفواتير لكل حالة', function() use ($user) {
    $rows = DB::table('invoices')->where('company_id', $user->company_id)
        ->select('status', DB::raw('count(*) as cnt'))->groupBy('status')->get();
    foreach ($rows as $r) echo "   → {$r->status}: {$r->cnt}\n";
});

test('تقارير', 'المنتجات ذات المخزون المنخفض', function() use ($user) {
    $low = DB::table('inventory')->where('company_id', $user->company_id)
        ->whereColumn('quantity', '<=', 'reorder_point')->count();
    echo "   → منتجات بمخزون منخفض: {$low}\n";
});

test('تقارير', 'أعلى 3 منتجات مبيعاً', function() use ($user) {
    $top = DB::table('invoice_items')->where('company_id', $user->company_id)
        ->whereNotNull('product_id')
        ->select('product_id', DB::raw('sum(quantity) as total_qty'))
        ->groupBy('product_id')->orderByDesc('total_qty')->limit(3)->get();
    foreach ($top as $t) {
        $name = DB::table('products')->where('id', $t->product_id)->value('name');
        echo "   → {$name}: {$t->total_qty} وحدة\n";
    }
});

// ═══════════════════════════════════════════════════
// CLEANUP
// ═══════════════════════════════════════════════════
echo "\n══ 7. تنظيف ══════════════════════════════════\n";
test('تنظيف', 'حذف بيانات الاختبار', function() use ($user, $productId, $customerId, $supplierId) {
    DB::table('invoice_items')->where('product_id', $productId)->delete();
    DB::table('stock_movements')->where('product_id', $productId)->delete();
    DB::table('inventory')->where('product_id', $productId)->delete();
    DB::table('products')->where('id', $productId)->delete();
    if ($customerId) DB::table('customers')->where('id', $customerId)->delete();
    if ($supplierId) DB::table('suppliers')->where('id', $supplierId)->delete();
    DB::table('idempotency_keys')->where('company_id', $user->company_id)->delete();
    echo "   → تم حذف جميع بيانات الاختبار\n";
});

// ── SUMMARY ──
echo "\n";
foreach ($results as $r) echo $r . "\n";

$pass = count(array_filter($results, fn($r) => str_starts_with($r, '✅')));
$fail = count(array_filter($results, fn($r) => str_starts_with($r, '❌')));
$total = count($results);

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║                   ملخص النتائج                   ║\n";
echo "╠══════════════════════════════════════════════════╣\n";
echo "║  المجموع : $total اختبار" . str_repeat(' ', 41 - strlen("المجموع : $total اختبار")) . "║\n";
echo "║  ناجح    : $pass" . str_repeat(' ', 44 - strlen("ناجح    : $pass")) . "║\n";
echo "║  فاشل    : $fail" . str_repeat(' ', 44 - strlen("فاشل    : $fail")) . "║\n";
echo "╚══════════════════════════════════════════════════╝\n";
if ($fail === 0) echo "\n✅ جميع الاختبارات نجحت!\n";
echo "\n";
