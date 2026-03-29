<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$user = DB::table('users')->where('email', 'owner@demo.sa')->first();
$companyId = $user->company_id;
$branchId  = $user->branch_id;
$userId    = $user->id;
$now = now();

echo "═══════════════════════════════════════\n";
echo "  إضافة بيانات تجريبية للنظام\n";
echo "═══════════════════════════════════════\n\n";

// ── العملاء ──────────────────────────────
$customersData = [
    ['name' => 'أحمد محمد العتيبي',  'phone' => '0501234567', 'email' => 'ahmed@example.com',   'type' => 'individual'],
    ['name' => 'فهد سعد الغامدي',     'phone' => '0509876543', 'email' => 'fahad@example.com',   'type' => 'individual'],
    ['name' => 'نورة خالد الشمري',    'phone' => '0555555555', 'email' => 'noura@example.com',   'type' => 'individual'],
    ['name' => 'شركة الخليج للتجارة', 'phone' => '0112345678', 'email' => 'gulf@company.sa',     'type' => 'b2b'],
    ['name' => 'محمد علي الزهراني',   'phone' => '0561112233', 'email' => 'moh@example.com',     'type' => 'individual'],
    ['name' => 'مؤسسة النجاح',        'phone' => '0113456789', 'email' => 'success@biz.sa',      'type' => 'b2b'],
    ['name' => 'خالد إبراهيم القحطاني','phone'=> '0531234567', 'email' => 'khaled@example.com',  'type' => 'individual'],
    ['name' => 'سارة عبدالله الدوسري','phone' => '0547654321', 'email' => 'sara@example.com',    'type' => 'individual'],
];

$customerIds = [];
foreach ($customersData as $c) {
    $exists = DB::table('customers')
        ->where('company_id', $companyId)
        ->where('phone', $c['phone'])
        ->value('id');
    if ($exists) {
        $customerIds[] = $exists;
        continue;
    }
    $id = DB::table('customers')->insertGetId([
        'uuid'       => Str::uuid(), 'company_id' => $companyId,
        'name'       => $c['name'],  'phone'      => $c['phone'],
        'email'      => $c['email'], 'type'       => $c['type'],
        'is_active'  => true,        'version'    => 1,
        'created_at' => $now,        'updated_at' => $now,
    ]);
    $customerIds[] = $id;
}
echo "✅ العملاء: " . count($customerIds) . " عميل\n";

// ── المركبات ──────────────────────────────
$vehiclesData = [
    ['plate' => 'أ ب ج 1234', 'make' => 'Toyota',   'model' => 'Camry',   'year' => 2022, 'fuel' => 'petrol',   'color' => 'أبيض',   'cust' => 0],
    ['plate' => 'د ه و 5678', 'make' => 'Hyundai',  'model' => 'Tucson',  'year' => 2021, 'fuel' => 'petrol',   'color' => 'رمادي',  'cust' => 1],
    ['plate' => 'ز ح ط 9012', 'make' => 'Kia',      'model' => 'Sportage','year' => 2023, 'fuel' => 'petrol',   'color' => 'أسود',   'cust' => 2],
    ['plate' => 'ي ك ل 3456', 'make' => 'BMW',      'model' => 'X5',      'year' => 2020, 'fuel' => 'petrol',   'color' => 'أبيض',   'cust' => 0],
    ['plate' => 'م ن س 7890', 'make' => 'Toyota',   'model' => 'Land Cruiser','year'=>2019,'fuel'=> 'petrol',   'color' => 'بيج',    'cust' => 4],
    ['plate' => 'ع ف ص 2345', 'make' => 'Nissan',   'model' => 'Patrol',  'year' => 2022, 'fuel' => 'petrol',   'color' => 'أبيض',   'cust' => 6],
    ['plate' => 'ق ر ش 6789', 'make' => 'Mercedes', 'model' => 'E-Class', 'year' => 2021, 'fuel' => 'petrol',   'color' => 'فضي',    'cust' => 1],
    ['plate' => 'ت ث خ 1111', 'make' => 'Tesla',    'model' => 'Model 3', 'year' => 2023, 'fuel' => 'electric', 'color' => 'أحمر',   'cust' => 7],
    ['plate' => 'ذ ض ظ 2222', 'make' => 'Ford',     'model' => 'F-150',   'year' => 2020, 'fuel' => 'petrol',   'color' => 'أزرق',   'cust' => 3],
    ['plate' => 'غ ش ئ 3333', 'make' => 'Lexus',    'model' => 'RX',      'year' => 2022, 'fuel' => 'petrol',   'color' => 'بني',    'cust' => 5],
];

$vehicleCount = 0;
foreach ($vehiclesData as $v) {
    $exists = DB::table('vehicles')
        ->where('company_id', $companyId)
        ->where('plate_number', $v['plate'])
        ->exists();
    if ($exists) { $vehicleCount++; continue; }

    $custId = $customerIds[$v['cust']] ?? null;
    DB::table('vehicles')->insert([
        'uuid'                => Str::uuid(), 'company_id'          => $companyId,
        'branch_id'           => $branchId,   'customer_id'         => $custId,
        'created_by_user_id'  => $userId,     'plate_number'        => $v['plate'],
        'make'                => $v['make'],  'model'               => $v['model'],
        'year'                => $v['year'],  'fuel_type'           => $v['fuel'],
        'color'               => $v['color'], 'is_active'           => true,
        'version'             => 1,           'created_at'          => $now,
        'updated_at'          => $now,
    ]);
    $vehicleCount++;
}
echo "✅ المركبات: $vehicleCount مركبة\n";

// ── الموردون ──────────────────────────────
$suppliersData = [
    ['name' => 'شركة قطع غيار الخليج',   'phone' => '0112223334', 'email' => 'parts@gulf.sa'],
    ['name' => 'مؤسسة التوريدات الصناعية','phone' => '0113334445', 'email' => 'info@industrial.sa'],
    ['name' => 'شركة نجمة للمواد',        'phone' => '0114445556', 'email' => 'star@materials.sa'],
];
$supplierCount = 0;
foreach ($suppliersData as $s) {
    $exists = DB::table('suppliers')->where('company_id', $companyId)->where('name', $s['name'])->exists();
    if ($exists) { $supplierCount++; continue; }
    DB::table('suppliers')->insert([
        'uuid'               => Str::uuid(), 'company_id'         => $companyId,
        'created_by_user_id' => $userId,     'name'               => $s['name'],
        'phone'              => $s['phone'],  'email'              => $s['email'],
        'is_active'          => true,         'version'            => 1,
        'created_at'         => $now,         'updated_at'         => $now,
    ]);
    $supplierCount++;
}
echo "✅ الموردون: $supplierCount مورد\n";

// ── المنتجات ──────────────────────────────
$unit = DB::table('units')->where('company_id', $companyId)->first();
$productsData = [
    ['name'=>'زيت محرك 5W-30', 'sku'=>'OIL-5W30', 'cost'=>25, 'price'=>45, 'qty'=>150],
    ['name'=>'فلتر زيت',       'sku'=>'FLT-OIL',  'cost'=>15, 'price'=>30, 'qty'=>200],
    ['name'=>'فلتر هواء',      'sku'=>'FLT-AIR',  'cost'=>20, 'price'=>40, 'qty'=>120],
    ['name'=>'تيل فرامل',      'sku'=>'BRK-FLUID', 'cost'=>18, 'price'=>35, 'qty'=>80],
    ['name'=>'شمعات إشعال',    'sku'=>'SPARK-4',   'cost'=>40, 'price'=>80, 'qty'=>60],
    ['name'=>'مساحات زجاج',    'sku'=>'WIPER-PR',  'cost'=>30, 'price'=>60, 'qty'=>40],
    ['name'=>'بطارية 60AH',    'sku'=>'BAT-60AH',  'cost'=>200,'price'=>350,'qty'=>20],
    ['name'=>'مبرد ماء',       'sku'=>'COOLANT-1', 'cost'=>22, 'price'=>42, 'qty'=>100],
];
$productCount = 0;
foreach ($productsData as $p) {
    $existId = DB::table('products')->where('company_id', $companyId)->where('sku', $p['sku'])->value('id');
    if (!$existId) {
        $existId = DB::table('products')->insertGetId([
            'uuid'            => Str::uuid(), 'company_id'      => $companyId,
            'name'            => $p['name'],  'sku'             => $p['sku'],
            'unit_id'         => $unit?->id,  'unit'            => $unit?->name ?? 'قطعة',
            'cost_price'      => $p['cost'],  'sale_price'      => $p['price'],
            'tax_rate'        => 15,           'is_taxable'      => true,
            'is_active'       => true,         'track_inventory' => true,
            'product_type'    => 'product',    'version'         => 1,
            'created_at'      => $now,         'updated_at'      => $now,
        ]);
    }
    // مخزون
    $invExists = DB::table('inventory')->where('product_id', $existId)->where('company_id', $companyId)->exists();
    if (!$invExists) {
        DB::table('inventory')->insert([
            'company_id'        => $companyId, 'branch_id'         => $branchId,
            'product_id'        => $existId,   'quantity'          => $p['qty'],
            'reserved_quantity' => 0,           'reorder_point'     => 10,
            'version'           => 1,           'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }
    $productCount++;
}
echo "✅ المنتجات: $productCount منتج (مع مخزون)\n";

// ── أوامر العمل ──────────────────────────
$woColumns = array_column(
    DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='work_orders' ORDER BY ordinal_position"),
    'column_name'
);
echo "   [work_orders columns: " . implode(', ', $woColumns) . "]\n";

$woCount = 0;
$woData = [
    ['vehicle_idx'=>0, 'customer_idx'=>0, 'status'=>'completed', 'desc'=>'تغيير زيت المحرك وفلتر الزيت',        'labor'=>150],
    ['vehicle_idx'=>1, 'customer_idx'=>1, 'status'=>'in_progress','desc'=>'فحص الفرامل وتعديل التوازن',          'labor'=>200],
    ['vehicle_idx'=>2, 'customer_idx'=>2, 'status'=>'pending',    'desc'=>'تغيير فلتر الهواء وشمعات الإشعال',    'labor'=>120],
    ['vehicle_idx'=>3, 'customer_idx'=>0, 'status'=>'completed',  'desc'=>'صيانة دورية شاملة',                  'labor'=>500],
    ['vehicle_idx'=>4, 'customer_idx'=>4, 'status'=>'pending',    'desc'=>'فحص نظام التبريد',                    'labor'=>180],
];

$vehicleIds = DB::table('vehicles')->where('company_id', $companyId)->pluck('id')->toArray();

foreach ($woData as $wo) {
    $vehicleId  = $vehicleIds[$wo['vehicle_idx']] ?? null;
    $customerId = $customerIds[$wo['customer_idx']] ?? null;
    if (!$vehicleId) continue;

    $row = [
        'uuid'                => Str::uuid(),
        'company_id'          => $companyId,
        'branch_id'           => $branchId,
        'created_by_user_id'  => $userId,
        'vehicle_id'          => $vehicleId,
        'customer_id'         => $customerId,
        'status'              => $wo['status'],
        'priority'            => 'normal',
        'customer_complaint'  => $wo['desc'],
        'estimated_total'     => $wo['labor'],
        'version'             => 1,
        'order_number'        => 'WO-' . str_pad($woCount+1, 4, '0', STR_PAD_LEFT),
        'work_order_number'   => 'WO-' . str_pad($woCount+1, 4, '0', STR_PAD_LEFT),
        'created_at'          => $now,
        'updated_at'          => $now,
    ];

    DB::table('work_orders')->insert($row);
    $woCount++;
}
echo "✅ أوامر العمل: $woCount أمر\n";

echo "\n═══════════════════════════════════════\n";
echo "  ملخص البيانات المضافة\n";
echo "═══════════════════════════════════════\n";
foreach (['customers','vehicles','products','suppliers','work_orders'] as $t) {
    $c = DB::table($t)->where('company_id', $companyId)->count();
    echo "  $t: $c\n";
}
echo "═══════════════════════════════════════\n";
echo "✅ اكتمل بنجاح!\n";
