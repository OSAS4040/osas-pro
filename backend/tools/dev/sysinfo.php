<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$db = app('db');

// DB version
$pg = $db->select('SELECT version()')[0]->version;
echo "PostgreSQL: $pg\n";

// Redis version + info
$r = new Redis();
$r->connect(env('REDIS_HOST','redis'), (int)env('REDIS_PORT',6379), 2.0);
if (env('REDIS_PASSWORD')) $r->auth(env('REDIS_PASSWORD'));
$info = $r->info();
echo "Redis version: " . $info['redis_version'] . "\n";
echo "Redis maxmemory: " . $info['maxmemory'] . "\n";
echo "Redis maxmemory_policy: " . $info['maxmemory_policy'] . "\n";
echo "Redis used_memory_human: " . $info['used_memory_human'] . "\n";
echo "Redis connected_clients: " . $info['connected_clients'] . "\n";

// Table counts
$tables = ['users','companies','customers','vehicles','invoices','work_orders','products','wallets','journal_entries','bookings','plans','subscriptions'];
foreach ($tables as $t) {
    try {
        $c = $db->select("SELECT COUNT(*) as c FROM {$t}")[0]->c;
        echo "Table {$t}: {$c} rows\n";
    } catch(\Exception $e) { echo "Table {$t}: NOT EXISTS\n"; }
}

// Index count
$idxs = $db->select("SELECT COUNT(*) as c FROM pg_indexes WHERE schemaname='public'")[0]->c;
echo "Total DB indexes: $idxs\n";

// PHP info
echo "PHP version: " . PHP_VERSION . "\n";
echo "OPcache enabled: " . (ini_get('opcache.enable') ? 'yes' : 'no') . "\n";
echo "OPcache JIT: " . ini_get('opcache.jit') . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
