<?php
chdir('/var/www'); require '/var/www/vendor/autoload.php';
$app=require_once '/var/www/bootstrap/app.php';
$kernel=$app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap();

$p = \App\Models\Product::find(25);
echo "Product 25: " . $p->name . "\n";
echo "track_inventory: " . var_export($p->track_inventory, true) . "\n";
echo "type: " . gettype($p->track_inventory) . "\n";

// Check all products
$all = \App\Models\Product::where('company_id',1)->get(['id','name','track_inventory']);
foreach ($all as $prod) {
    echo "  #{$prod->id} {$prod->name}: track=" . var_export($prod->track_inventory, true) . "\n";
}
