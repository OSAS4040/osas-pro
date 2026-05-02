<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

chdir('/var/www');
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// حذف جميع فواتير INV-1-XXXXXX (من POS وAPI القديمة)
$toDelete = DB::table('invoices')
    ->where('company_id', 1)
    ->where('invoice_number', 'LIKE', 'INV-1-%')
    ->pluck('id');
echo 'سيتم حذف: '.count($toDelete)." فاتورة\n";
if (count($toDelete) > 0) {
    DB::table('payments')->whereIn('invoice_id', $toDelete)->delete();
    DB::table('invoice_items')->whereIn('invoice_id', $toDelete)->delete();
    $del = DB::table('invoices')->whereIn('id', $toDelete)->delete();
    echo "تم الحذف: $del\n";
}

$max = DB::table('invoices')->where('company_id', 1)->whereNotNull('invoice_counter')->max('invoice_counter') ?? 0;
echo "Max counter بعد التنظيف: $max\n";
