<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$redisPass = env('REDIS_PASSWORD', null);
$host = env('REDIS_HOST', 'redis');
$port = (int)env('REDIS_PORT', 6379);

$r = new Redis();
$r->connect($host, $port, 2.0);
if ($redisPass) $r->auth($redisPass);

// Set eviction policy
$r->config('SET', 'maxmemory-policy', 'allkeys-lru');
$r->config('SET', 'maxmemory', '384mb');

// Verify
$policy = $r->config('GET', 'maxmemory-policy');
$mem    = $r->config('GET', 'maxmemory');
$info   = $r->info('server');

echo "Redis version: " . $info['redis_version'] . "\n";
echo "Eviction policy: " . $policy['maxmemory-policy'] . "\n";
echo "Max memory: " . $mem['maxmemory'] . "\n";
echo "Connected clients: " . $r->info('clients')['connected_clients'] . "\n";

// Quick latency benchmark (100 SET + 100 GET)
$t = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $r->set("bench_$i", "val_$i", 10);
}
for ($i = 0; $i < 100; $i++) {
    $r->get("bench_$i");
}
$elapsed = round((microtime(true) - $t) * 1000, 2);
echo "Raw Redis 200 ops: {$elapsed}ms (avg " . round($elapsed/200, 3) . "ms/op)\n";

// Test Laravel Cache layer
$cache = app('cache')->store('redis');
$t2 = microtime(true);
for ($i = 0; $i < 50; $i++) {
    $cache->put("lbench_$i", ['data' => str_repeat('x', 100)], 60);
}
for ($i = 0; $i < 50; $i++) {
    $cache->get("lbench_$i");
}
$e2 = round((microtime(true) - $t2) * 1000, 2);
echo "Laravel Cache 100 ops: {$e2}ms (avg " . round($e2/100, 3) . "ms/op)\n";

// Check OPcache
$op = opcache_get_status(false);
if ($op) {
    echo "OPcache: enabled, hit rate=" . round($op['opcache_statistics']['opcache_hit_rate'],2) . "%\n";
    echo "OPcache JIT: " . ($op['jit']['enabled'] ?? false ? 'ENABLED' : 'disabled') . "\n";
} else {
    echo "OPcache: NOT enabled\n";
}

echo "\nDONE\n";
