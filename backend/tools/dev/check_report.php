<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check invoices.status column type
$cols = DB::select("SELECT column_name, data_type, udt_name FROM information_schema.columns WHERE table_name='invoices' AND column_name='status'");
echo "invoices.status type: " . ($cols[0]->data_type ?? 'unknown') . " / " . ($cols[0]->udt_name ?? '') . "\n";

// Try the exact query from ReportController
try {
    $r = DB::table('invoices')
        ->where('company_id', 1)
        ->whereBetween('issued_at', ['2024-01-01', '2026-12-31'])
        ->whereNotIn('status', ['cancelled', 'draft'])
        ->selectRaw('COUNT(*) as invoice_count, SUM(total) as total_sales, SUM(tax_amount) as total_tax, SUM(discount_amount) as total_discount')
        ->first();
    echo "Report query OK\n";
    echo "Results: " . json_encode($r) . "\n";
} catch (\Throwable $e) {
    echo "Report query FAILED: " . $e->getMessage() . "\n";
}
