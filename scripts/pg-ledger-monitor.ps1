# PostgreSQL load / lock probe while k6 runs (every 15s).
# Usage (from repo root, during load test):
#   pwsh -File scripts/pg-ledger-monitor.ps1
# Optional: -IntervalSeconds 20 -Iterations 40

param(
    [int] $IntervalSeconds = 15,
    [int] $Iterations = 60
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot/..

$dbUser = if ($env:DB_USERNAME) { $env:DB_USERNAME } else { "saas_user" }
$dbName = if ($env:DB_DATABASE) { $env:DB_DATABASE } else { "saas_db" }

$sql1 = @"
SELECT pid, state, wait_event_type, wait_event, left(query, 200) AS query_preview, query_start
FROM pg_stat_activity
WHERE state <> 'idle' AND datname = current_database()
ORDER BY query_start ASC NULLS LAST;
"@

$sql2 = @"
SELECT locktype, relation::regclass, mode, granted, pid
FROM pg_locks
WHERE NOT granted;
"@

$sql3 = @"
SELECT deadlocks, conflicts, temp_files
FROM pg_stat_database
WHERE datname = current_database();
"@

for ($i = 0; $i -lt $Iterations; $i++) {
    $ts = Get-Date -Format "o"
    Write-Host "`n===== $ts (tick $($i+1)/$Iterations) =====" -ForegroundColor Cyan

    docker compose exec -T postgres psql -U $dbUser -d $dbName -c $sql1 2>&1
    docker compose exec -T postgres psql -U $dbUser -d $dbName -c $sql2 2>&1
    docker compose exec -T postgres psql -U $dbUser -d $dbName -c $sql3 2>&1

    Start-Sleep -Seconds $IntervalSeconds
}
