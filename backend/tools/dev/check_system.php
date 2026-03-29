<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check inventory table
echo "=== Inventory table check ===\n";
$count = DB::table('inventory')->count();
echo "Rows in inventory: $count\n";

if ($count > 0) {
    $rows = DB::table('inventory')->limit(3)->get(['id', 'product_id', 'branch_id', 'quantity', 'company_id']);
    foreach ($rows as $r) {
        echo "  id={$r->id} product={$r->product_id} branch={$r->branch_id} qty={$r->quantity} co={$r->company_id}\n";
    }
}

// Check products
echo "\n=== Products check ===\n";
$prods = DB::table('products')->where('is_active', true)->limit(5)->get(['id', 'name', 'product_type', 'sale_price']);
echo "Active products: " . count($prods) . "\n";
foreach ($prods as $p) {
    echo "  id={$p->id} name={$p->name} type={$p->product_type} price={$p->sale_price}\n";
}

// Check payments table
echo "\n=== Payments table check ===\n";
echo "Columns: ";
$cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='payments' AND table_schema='public' ORDER BY ordinal_position");
echo implode(', ', array_column($cols, 'column_name')) . "\n";

// Check reports route
echo "\n=== Checking reports issue ===\n";
try {
    $from = date('Y-01-01');
    $to = date('Y-12-31');
    $r = DB::table('invoices')
        ->where('company_id', 1)
        ->whereBetween('issued_at', [$from, $to])
        ->whereNotIn('status', ['cancelled', 'draft'])
        ->selectRaw('COUNT(*) as invoice_count, SUM(total) as total_sales')
        ->first();
    echo "Sales query OK: count={$r->invoice_count} total={$r->total_sales}\n";
} catch (\Throwable $e) {
    echo "Sales query ERROR: " . $e->getMessage() . "\n";
}
