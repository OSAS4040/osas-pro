<?php
chdir('/var/www'); require '/var/www/vendor/autoload.php';
$app=require_once '/var/www/bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap();

// حذف جميع فواتير INV-1-XXXXXX (من POS وAPI القديمة)
$toDelete = \Illuminate\Support\Facades\DB::table('invoices')
    ->where('company_id',1)
    ->where('invoice_number','LIKE','INV-1-%')
    ->pluck('id');
echo "سيتم حذف: " . count($toDelete) . " فاتورة\n";
if (count($toDelete) > 0) {
    \Illuminate\Support\Facades\DB::table('payments')->whereIn('invoice_id',$toDelete)->delete();
    \Illuminate\Support\Facades\DB::table('invoice_items')->whereIn('invoice_id',$toDelete)->delete();
    $del = \Illuminate\Support\Facades\DB::table('invoices')->whereIn('id',$toDelete)->delete();
    echo "تم الحذف: $del\n";
}

$max = \Illuminate\Support\Facades\DB::table('invoices')->where('company_id',1)->whereNotNull('invoice_counter')->max('invoice_counter') ?? 0;
echo "Max counter بعد التنظيف: $max\n";

