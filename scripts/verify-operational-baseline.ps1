# Operational baseline checks — read-only / non-destructive (no migrate, no flush, no data changes).
# Run from repo root:
#   powershell -ExecutionPolicy Bypass -File scripts/verify-operational-baseline.ps1
# Optional:
#   -PgUser saas_user -PgDb saas_db -RedisPassword redis_password

param(
  [string]$HealthUrl = "http://localhost/api/v1/health",
  [string]$PgUser = "saas_user",
  [string]$PgDb = "saas_db",
  [string]$RedisPassword = "redis_password",
  # Must match docker-compose.yml DOCKER_STACK_PREFIX (default saas → containers saas_app, saas_postgres, …).
  [string]$DockerStackPrefix = "saas",
  [switch]$SkipSchedulerCheck
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

$p = $DockerStackPrefix.Trim()
$required = @(
  "${p}_app", "${p}_nginx", "${p}_postgres", "${p}_redis", "${p}_frontend",
  "${p}_queue_default", "${p}_queue_high", "${p}_queue_low", "${p}_scheduler"
)
if ($SkipSchedulerCheck) {
  $required = $required | Where-Object { $_ -ne "${p}_scheduler" }
}
$pgCtn = "${p}_postgres"
$redisCtn = "${p}_redis"

Write-Host "== Docker containers (running) =="
$running = docker ps --format "{{.Names}}" 2>$null
$missing = @()
foreach ($n in $required) {
  if ($running -notcontains $n) { $missing += $n } else { Write-Host "  OK  $n" }
}
if ($missing.Count -gt 0) {
  Write-Host "MISSING or not running:" ($missing -join ", ")
  exit 2
}

Write-Host "`n== HTTP GET $HealthUrl =="
try {
  $h = Invoke-WebRequest -Uri $HealthUrl -UseBasicParsing -TimeoutSec 30
  Write-Host "  Status:" $h.StatusCode
  if ($h.StatusCode -ne 200) { exit 3 }
} catch {
  Write-Host "  FAILED:" $_.Exception.Message
  exit 3
}

Write-Host "`n== PostgreSQL failed_jobs count (read-only) =="
$fj = docker exec $pgCtn psql -U $PgUser -d $PgDb -t -A -c "select count(*) from failed_jobs;" 2>$null
if (-not $fj) {
  Write-Host "  FAILED: unable to read PostgreSQL with provided PgUser/PgDb."
  exit 4
}
Write-Host "  failed_jobs:" ($fj | Out-String).Trim()

Write-Host "`n== PostgreSQL failed_jobs breakdown (last 24h) =="
$fjBreakdown = docker exec $pgCtn psql -U $PgUser -d $PgDb -t -A -c "select split_part(exception, E'\n', 1) as first_line, count(*) from failed_jobs where failed_at >= now() - interval '24 hour' group by 1 order by 2 desc limit 5;" 2>$null
if ($fjBreakdown) {
  $fjBreakdown | ForEach-Object { Write-Host "  $_" }
} else {
  Write-Host "  (no rows)"
}

Write-Host "`n== Redis queue lengths (read-only) =="
$auth = "REDISCLI_AUTH=$RedisPassword"
$d = docker exec $redisCtn sh -lc "$auth redis-cli --raw LLEN queues:default" 2>$null
$h = docker exec $redisCtn sh -lc "$auth redis-cli --raw LLEN queues:high_priority" 2>$null
$l = docker exec $redisCtn sh -lc "$auth redis-cli --raw LLEN queues:low_priority" 2>$null
if ((($d | Out-String) -match "NOAUTH") -or (($h | Out-String) -match "NOAUTH") -or (($l | Out-String) -match "NOAUTH")) {
  Write-Host "  FAILED: Redis authentication failed."
  exit 5
}
Write-Host "  default:" ($d | Out-String).Trim()
Write-Host "  high_priority:" ($h | Out-String).Trim()
Write-Host "  low_priority:" ($l | Out-String).Trim()

Write-Host "`n== Redis key stats (read-only) =="
$redisOps = docker exec $redisCtn sh -lc "$auth redis-cli --raw INFO stats | grep -E 'instantaneous_ops_per_sec|rejected_connections|total_error_replies'" 2>$null
if ($redisOps) {
  $redisOps | ForEach-Object { Write-Host "  $_" }
}

Write-Host "`nBaseline checks finished OK."
