<?php
require '/var/www/vendor/autoload.php';
$app = require '/var/www/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Delete old idempotency keys
$deleted = DB::table('idempotency_keys')->delete();
echo "Deleted idempotency_keys: $deleted\n";

// Create cache tables if missing
if (!Schema::hasTable('cache')) {
    DB::statement("CREATE TABLE cache (key VARCHAR(255) NOT NULL PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL)");
    echo "Created cache table\n";
} else {
    echo "Cache table already exists\n";
}

if (!Schema::hasTable('cache_locks')) {
    DB::statement("CREATE TABLE cache_locks (key VARCHAR(255) NOT NULL PRIMARY KEY, owner VARCHAR(255) NOT NULL, expiration INTEGER NOT NULL)");
    echo "Created cache_locks table\n";
} else {
    echo "Cache_locks table already exists\n";
}

echo "Done\n";
