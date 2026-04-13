<?php
/**
 * OSAS Performance Profiler
 * Deep diagnosis of every bottleneck before we tune
 */
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function bench(string $label, callable $fn, int $runs = 5): array {
    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        $t0 = microtime(true);
        $fn();
        $times[] = (microtime(true) - $t0) * 1000;
    }
    sort($times);
    return [
        'label' => $label,
        'runs'  => $runs,
        'min'   => round($times[0], 2),
        'max'   => round($times[$runs-1], 2),
        'avg'   => round(array_sum($times) / $runs, 2),
        'p50'   => round($times[(int)($runs/2)], 2),
    ];
}

$results = [];

// ─── 1. Redis ────────────────────────────────────────────────────────────────
$redisPass = env('REDIS_PASSWORD', null);
$redisHost = env('REDIS_HOST', 'redis');
$redisPort = (int) env('REDIS_PORT', 6379);

function redisConnect(): Redis {
    global $redisHost, $redisPort, $redisPass;
    $r = new Redis();
    $r->connect($redisHost, $redisPort);
    if ($redisPass) $r->auth($redisPass);
    return $r;
}

$results[] = bench('Redis PING (raw socket)', function() {
    $r = redisConnect();
    $r->ping();
    $r->close();
});

$results[] = bench('Redis SET+GET (Laravel Cache)', function() {
    Cache::put('bench_key', str_repeat('x',1024), 10);
    Cache::get('bench_key');
});

$results[] = bench('Redis pipeline 10 ops', function() {
    $r = redisConnect();
    $pipe = $r->pipeline();
    for ($i = 0; $i < 10; $i++) $pipe->set("bench_pipe_{$i}", $i);
    $pipe->exec();
    $r->close();
});

// ─── 2. DB ───────────────────────────────────────────────────────────────────
$results[] = bench('DB raw SELECT 1', function() {
    DB::select('SELECT 1');
});

$results[] = bench('DB companies first()', function() {
    DB::table('companies')->first();
});

$companyId = DB::table('companies')->value('id');

$results[] = bench('Invoices count (company scope)', function() use ($companyId) {
    DB::table('invoices')->where('company_id', $companyId)->count();
});

$results[] = bench('Invoices + customer JOIN', function() use ($companyId) {
    DB::table('invoices as i')
        ->join('customers as c', 'c.id', '=', 'i.customer_id')
        ->where('i.company_id', $companyId)
        ->select('i.id','i.total','c.name')
        ->limit(20)->get();
});

$results[] = bench('Work orders + items count', function() use ($companyId) {
    DB::table('work_orders')->where('company_id', $companyId)->count();
});

$results[] = bench('Wallet balance lookup', function() use ($companyId) {
    DB::table('wallets')->where('company_id', $companyId)->sum('balance');
});

$results[] = bench('Journal entries count', function() use ($companyId) {
    $table = DB::getSchemaBuilder()->hasTable('journal_entries') ? 'journal_entries' : 'ledger_entries';
    DB::table($table)->where('company_id', $companyId)->count();
});

// ─── 3. Eloquent ORM ─────────────────────────────────────────────────────────
$results[] = bench('User::find (no relations)', function() {
    App\Models\User::find(1);
});

$results[] = bench('Invoice::with(items) first', function() use ($companyId) {
    App\Models\Invoice::with('items')
        ->where('company_id', $companyId)
        ->first();
});

$results[] = bench('Invoice::with(items,customer) limit 10', function() use ($companyId) {
    App\Models\Invoice::with(['items','customer'])
        ->where('company_id', $companyId)
        ->latest()
        ->limit(10)
        ->get();
});

// ─── 4. OPcache ──────────────────────────────────────────────────────────────
$opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : null;

// ─── 5. Current DB indexes ───────────────────────────────────────────────────
$hotTables = ['invoices','work_orders','customers','vehicles','wallets','journal_entries','ledger_entries'];
$indexes = [];
foreach ($hotTables as $t) {
    if (!DB::getSchemaBuilder()->hasTable($t)) continue;
    $idxList = DB::select("
        SELECT indexname, indexdef
        FROM pg_indexes
        WHERE tablename = ?
        ORDER BY indexname
    ", [$t]);
    $indexes[$t] = count($idxList) . ' indexes: ' . implode(', ', array_column($idxList, 'indexname'));
}

// ─── Output ──────────────────────────────────────────────────────────────────
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║           OSAS PERFORMANCE PROFILER — " . date('H:i:s') . "                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

echo "┌─ BENCHMARK RESULTS (all times in ms) ────────────────────────────────\n";
printf("│  %-40s %6s %6s %6s %6s\n", 'Operation', 'min', 'avg', 'p50', 'max');
echo "├───────────────────────────────────────────────────────────────────────\n";
foreach ($results as $r) {
    $flag = $r['avg'] > 50 ? ' ⚠' : ($r['avg'] > 20 ? ' ~' : ' ✓');
    printf("│  %-40s %6.1f %6.1f %6.1f %6.1f%s\n",
        substr($r['label'],0,40), $r['min'], $r['avg'], $r['p50'], $r['max'], $flag);
}
echo "└───────────────────────────────────────────────────────────────────────\n\n";

echo "┌─ OPCACHE STATUS ──────────────────────────────────────────────────────\n";
if ($opcache) {
    echo "│  Enabled:        " . ($opcache['opcache_enabled'] ? 'YES ✓' : 'NO ✗') . "\n";
    echo "│  Memory used:    " . round($opcache['memory_usage']['used_memory']/1024/1024,1) . " MB\n";
    echo "│  Cached scripts: " . $opcache['opcache_statistics']['num_cached_scripts'] . "\n";
    echo "│  Hit rate:       " . round($opcache['opcache_statistics']['opcache_hit_rate'],2) . "%\n";
    echo "│  JIT:            " . (isset($opcache['jit']['enabled']) && $opcache['jit']['enabled'] ? 'ON ✓' : 'OFF') . "\n";
} else {
    echo "│  OPcache NOT available\n";
}
echo "└───────────────────────────────────────────────────────────────────────\n\n";

echo "┌─ DB INDEXES ON HOT TABLES ────────────────────────────────────────────\n";
foreach ($indexes as $t => $info) {
    printf("│  %-18s %s\n", $t, $info);
}
echo "└───────────────────────────────────────────────────────────────────────\n\n";

echo "┌─ REDIS CONFIG ────────────────────────────────────────────────────────\n";
try {
    $r = redisConnect();
    $cfg = $r->config('GET', 'maxmemory-policy');
    $info = $r->info('server');
    echo "│  Version:         " . ($info['redis_version'] ?? '?') . "\n";
    echo "│  Max memory:      " . ($info['maxmemory_human'] ?? '?') . "\n";
    echo "│  Eviction:        " . ($cfg['maxmemory-policy'] ?? '?') . "\n";
    echo "│  Connected:       " . ($r->ping() === true || $r->ping() === '+PONG' ? 'YES ✓' : 'NO') . "\n";
    $r->close();
} catch(Throwable $e) {
    echo "│  Error: " . $e->getMessage() . "\n";
}
echo "└───────────────────────────────────────────────────────────────────────\n\n";

// Bottleneck summary
$slow = array_filter($results, fn($r) => $r['avg'] > 20);
echo "┌─ BOTTLENECKS IDENTIFIED ──────────────────────────────────────────────\n";
if (empty($slow)) {
    echo "│  ✓ No significant bottlenecks found\n";
} else {
    foreach ($slow as $r) {
        $reason = '';
        if (str_contains($r['label'], 'Redis')) $reason = '→ network overhead / Laravel serialization';
        elseif (str_contains($r['label'], 'JOIN'))  $reason = '→ missing index on FK column';
        elseif (str_contains($r['label'], 'with'))  $reason = '→ N+1 / missing eager load index';
        else $reason = '→ review query plan';
        printf("│  ⚠ %-40s avg=%sms  %s\n", $r['label'], $r['avg'], $reason);
    }
}
echo "└───────────────────────────────────────────────────────────────────────\n\n";
