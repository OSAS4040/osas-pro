<?php
require_once '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$count = \DB::table('idempotency_keys')->count();
echo "Count: $count\n";
$rows = \DB::table('idempotency_keys')->select('id','company_id','key','request_hash','expires_at')->get();
foreach ($rows as $r) {
    echo "ID:{$r->id} company:{$r->company_id} key:{$r->key} hash:{$r->request_hash}\n";
}
echo "Inventory qty: " . (\DB::table('inventory')->value('quantity') ?? 'NULL') . "\n";
echo "Invoices: " . \DB::table('invoices')->count() . "\n";
