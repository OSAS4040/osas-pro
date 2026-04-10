param(
  [string]$K6BaseUrl = "http://host.docker.internal/api",
  [string]$K6Profile = "peak",
  [string]$K6PosIncludeRaw = "true",
  [string]$K6EmailA = "simulation.owner@demo.local",
  [string]$K6PasswordA = "SimulationDemo123!",
  [string]$K6EmailB = "owner@demo.sa",
  [string]$K6PasswordB = "password",
  [string]$PgUser = "saas_user",
  [string]$PgDb = "saas_db",
  [string]$RedisPassword = "redis_password",
  [switch]$SkipBaselineGate,
  [switch]$IsolateRecurringJobs,
  [switch]$SkipRcPolicyGate
)

# Secure RC peak runner:
# - non-destructive (read-only snapshots + k6 peak)
# - captures docker/app/db/redis/host/queue telemetry in one folder
# - stops on errors and preserves evidence

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

if (-not $SkipRcPolicyGate) {
  $stateFile = Join-Path $root "load-testing/reports/rc-policy/state.json"
  if (-not (Test-Path $stateFile)) {
    throw "RC policy gate failed: missing rc-policy state. Run scripts/rc-reset-consistent.ps1 before peak RC."
  }
  $state = Get-Content -Path $stateFile -Raw | ConvertFrom-Json
  if (($state.status -ne "clean_ready") -or ([datetime]$state.cleanup_finished_at -lt [datetime]$state.seed_finished_at)) {
    throw "RC policy gate failed: invalid seed/cleanup order. Require full reset-consistent sequence."
  }
}

if (-not $SkipBaselineGate) {
  Write-Host "Running secure baseline gate before peak RC..."
  $baselineArgs = @(
    "-ExecutionPolicy", "Bypass",
    "-File", (Join-Path $root "scripts/verify-operational-baseline.ps1"),
    "-HealthUrl", "http://localhost/api/v1/health",
    "-PgUser", $PgUser,
    "-PgDb", $PgDb,
    "-RedisPassword", $RedisPassword
  )
  if ($IsolateRecurringJobs) {
    $baselineArgs += "-SkipSchedulerCheck"
  }
  & powershell @baselineArgs
  if ($LASTEXITCODE -ne 0) {
    throw "Baseline gate failed with exit code $LASTEXITCODE. Aborting RC peak run."
  }

  Write-Host "Running k6 preflight auth gate before peak RC..."
  docker run --rm `
    -v "c:/Users/nawaf/.verdent/verdent-projects/new-project-3/load-testing:/work" `
    -w /work/k6 `
    --add-host=host.docker.internal:host-gateway `
    -e K6_BASE_URL=$K6BaseUrl `
    -e K6_EMAIL_A=$K6EmailA `
    -e K6_PASSWORD_A=$K6PasswordA `
    -e K6_EMAIL_B=$K6EmailB `
    -e K6_PASSWORD_B=$K6PasswordB `
    grafana/k6:latest run preflight.js
  if ($LASTEXITCODE -ne 0) {
    throw "k6 preflight auth gate failed. This is configuration drift; fix seed/credentials before peak RC."
  }
}

$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$outDir = Join-Path $root "load-testing/reports/rc-peak-$ts"
New-Item -ItemType Directory -Path $outDir -Force | Out-Null

$statsFile = Join-Path $outDir "docker-stats.csv"
$pgFile = Join-Path $outDir "postgres-snapshots.log"
$redisFile = Join-Path $outDir "redis-snapshots.log"
$hostFile = Join-Path $outDir "host-snapshots.log"
$queueFile = Join-Path $outDir "queue-summary.log"
$k6OutFile = Join-Path $outDir "k6-peak-output.log"
$k6ErrFile = Join-Path $outDir "k6-peak-error.log"

"timestamp,name,cpu_pct,mem_pct,mem_usage,net_io,block_io,pids" | Out-File -FilePath $statsFile -Encoding utf8

Write-Host "RC output: $outDir"

function Append-PostgresSnapshot {
  param([string]$Now)
  "=== $Now ===" | Out-File -FilePath $pgFile -Encoding utf8 -Append
  $pg = docker exec saas_postgres psql -U $PgUser -d $PgDb -t -A -c "select 'active_non_idle='||count(*) from pg_stat_activity where state<>'idle'; select 'waiting_locks='||count(*) from pg_stat_activity where wait_event_type='Lock'; select 'max_xact_age_sec='||coalesce(max(extract(epoch from (now()-xact_start)))::bigint,0) from pg_stat_activity where xact_start is not null;" 2>&1
  if ((($pg | Out-String) -match "FATAL") -or (($pg | Out-String) -match "role .* does not exist")) {
    throw "PostgreSQL snapshot auth/config failed. Check PgUser/PgDb."
  }
  $pg | Out-File -FilePath $pgFile -Encoding utf8 -Append
}

function Append-RedisSnapshot {
  param([string]$Now)
  "=== $Now ===" | Out-File -FilePath $redisFile -Encoding utf8 -Append
  $r1 = docker exec saas_redis sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli --raw INFO stats | grep -E 'instantaneous_ops_per_sec|total_error_replies|rejected_connections|evicted_keys'" 2>&1
  $r2 = docker exec saas_redis sh -lc "REDISCLI_AUTH=$RedisPassword redis-cli --raw LLEN queues:default; REDISCLI_AUTH=$RedisPassword redis-cli --raw LLEN queues:high_priority; REDISCLI_AUTH=$RedisPassword redis-cli --raw LLEN queues:low_priority" 2>&1
  if ((($r1 | Out-String) -match "NOAUTH") -or (($r2 | Out-String) -match "NOAUTH")) {
    throw "Redis snapshot auth/config failed. Check RedisPassword."
  }
  $r1 | Out-File -FilePath $redisFile -Encoding utf8 -Append
  $r2 | Out-File -FilePath $redisFile -Encoding utf8 -Append
}

function Append-HostSnapshot {
  param([string]$Now)
  "=== $Now ===" | Out-File -FilePath $hostFile -Encoding utf8 -Append
  Get-Counter "\Processor(_Total)\% Processor Time","\Memory\Available MBytes","\PhysicalDisk(_Total)\Avg. Disk sec/Transfer" -SampleInterval 1 -MaxSamples 1 | Out-File -FilePath $hostFile -Encoding utf8 -Append
}

# Launch k6 peak run in background process and capture output.
$k6Args = @(
  "run", "--rm",
  "-v", "c:/Users/nawaf/.verdent/verdent-projects/new-project-3/load-testing:/work",
  "-w", "/work/k6",
  "--add-host=host.docker.internal:host-gateway",
  "-e", "K6_BASE_URL=$K6BaseUrl",
  "-e", "K6_PROFILE=$K6Profile",
  "-e", "K6_POS_INCLUDE_RAW=$K6PosIncludeRaw",
  "-e", "K6_EMAIL_A=$K6EmailA",
  "-e", "K6_PASSWORD_A=$K6PasswordA",
  "-e", "K6_EMAIL_B=$K6EmailB",
  "-e", "K6_PASSWORD_B=$K6PasswordB",
  "grafana/k6:latest", "run", "suite.js"
)

$k6 = Start-Process -FilePath docker -ArgumentList $k6Args -NoNewWindow -PassThru -RedirectStandardOutput $k6OutFile -RedirectStandardError $k6ErrFile

try {
  while (-not $k6.HasExited) {
    $now = (Get-Date).ToString("o")
    docker stats --no-stream --format "{{.Name}},{{.CPUPerc}},{{.MemPerc}},{{.MemUsage}},{{.NetIO}},{{.BlockIO}},{{.PIDs}}" | ForEach-Object { "$now,$_"} | Out-File -FilePath $statsFile -Encoding utf8 -Append
    Append-PostgresSnapshot -Now $now
    Append-RedisSnapshot -Now $now
    Append-HostSnapshot -Now $now
    Start-Sleep -Seconds 10
    $k6.Refresh()
  }
  $k6.WaitForExit()
}
finally {
  "Peak telemetry dir: $outDir" | Out-File -FilePath $queueFile -Encoding utf8
  docker compose exec -T app php artisan queue:failed | Out-File -FilePath $queueFile -Encoding utf8 -Append
  docker compose logs --since 20m queue_default queue_high queue_low | Select-String -Pattern "FAIL|failed|exception|timeout" | Out-File -FilePath $queueFile -Encoding utf8 -Append
}

Write-Host "K6 exit code: $($k6.ExitCode)"
Write-Host "RC_OUTPUT_DIR=$outDir"
$k6Out = if (Test-Path $k6OutFile) { Get-Content -Path $k6OutFile -Raw } else { "" }
$k6Err = if (Test-Path $k6ErrFile) { Get-Content -Path $k6ErrFile -Raw } else { "" }
if (($k6Out -match "setup_login_failures") -or ($k6Err -match "script exception")) {
  Write-Host "Detected k6 setup/auth failure markers. Treating run as failed."
  exit 98
}
if ($null -eq $k6.ExitCode -or $k6.ExitCode -eq "") {
  Write-Host "K6 process exit code unavailable, no setup/auth failure markers found; returning 0."
  exit 0
}
exit $k6.ExitCode

