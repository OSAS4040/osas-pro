<?php
$pdo = new PDO('pgsql:host=saas_db;dbname=saas_db', 'saas_user', 'saas_password');
$count = $pdo->exec("DELETE FROM idempotency_keys");
echo "Deleted idempotency_keys: " . $count . "\n";

// Create cache table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS cache (
    key VARCHAR(255) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL,
    expiration INTEGER NOT NULL
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS cache_locks (
    key VARCHAR(255) NOT NULL PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INTEGER NOT NULL
)");
echo "Cache tables ready\n";
