<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$db = app('db');
$tables = ['wallets', 'customers', 'products'];
foreach ($tables as $t) {
    $exists = $db->select("SELECT to_regclass('public.{$t}') as r");
    if (!$exists[0]->r) { echo "{$t}: NOT EXISTS\n"; continue; }
    $cols = $db->select("SELECT column_name FROM information_schema.columns WHERE table_name='{$t}' ORDER BY ordinal_position");
    echo "{$t}: " . implode(', ', array_column($cols, 'column_name')) . "\n";
}
