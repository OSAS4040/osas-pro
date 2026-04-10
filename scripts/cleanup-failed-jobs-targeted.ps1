param(
  [string]$PgUser = "saas_user",
  [string]$PgDb = "saas_db",
  [int]$OlderThanMinutes = 30
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

$targetJobs = @(
  "App\Jobs\ExpireInventoryReservationsJob",
  "App\Jobs\ExpireIdempotencyKeysJob",
  "App\Jobs\SendDocumentExpiryNotificationsJob",
  "App\Jobs\PostPosLedgerJob"
)
$targetList = ($targetJobs | ForEach-Object { "'$_'" }) -join ","

$ts = Get-Date -Format "yyyyMMdd-HHmmss"
$outDir = Join-Path $root "load-testing/reports/failed-jobs-cleanup-$ts"
New-Item -ItemType Directory -Path $outDir -Force | Out-Null
$report = Join-Path $outDir "cleanup-report.log"

"Targeted failed_jobs cleanup report" | Out-File -FilePath $report -Encoding utf8
"timestamp=$(Get-Date -Format o)" | Out-File -FilePath $report -Encoding utf8 -Append
"older_than_minutes=$OlderThanMinutes" | Out-File -FilePath $report -Encoding utf8 -Append
"targets=$($targetJobs -join ', ')" | Out-File -FilePath $report -Encoding utf8 -Append
"" | Out-File -FilePath $report -Encoding utf8 -Append

$before = docker exec saas_postgres psql -U $PgUser -d $PgDb -t -A -c "select payload::json->>'displayName' as job, count(*) from failed_jobs where payload::json->>'displayName' in ($targetList) and exception like 'Illuminate\\Queue\\MaxAttemptsExceededException:%' and failed_at < now() - interval '$OlderThanMinutes minute' group by 1 order by 2 desc;"
"== BEFORE ==" | Out-File -FilePath $report -Encoding utf8 -Append
if ($before) {
  $before | Out-File -FilePath $report -Encoding utf8 -Append
} else {
  "(no matching stale rows)" | Out-File -FilePath $report -Encoding utf8 -Append
}
"" | Out-File -FilePath $report -Encoding utf8 -Append

$deleted = docker exec saas_postgres psql -U $PgUser -d $PgDb -t -A -c "with doomed as (select id from failed_jobs where payload::json->>'displayName' in ($targetList) and exception like 'Illuminate\\Queue\\MaxAttemptsExceededException:%' and failed_at < now() - interval '$OlderThanMinutes minute') delete from failed_jobs where id in (select id from doomed) returning id;" | Measure-Object | Select-Object -ExpandProperty Count

$after = docker exec saas_postgres psql -U $PgUser -d $PgDb -t -A -c "select payload::json->>'displayName' as job, count(*) from failed_jobs where payload::json->>'displayName' in ($targetList) and exception like 'Illuminate\\Queue\\MaxAttemptsExceededException:%' and failed_at < now() - interval '$OlderThanMinutes minute' group by 1 order by 2 desc;"
"deleted_rows=$deleted" | Out-File -FilePath $report -Encoding utf8 -Append
"" | Out-File -FilePath $report -Encoding utf8 -Append
"== AFTER ==" | Out-File -FilePath $report -Encoding utf8 -Append
if ($after) {
  $after | Out-File -FilePath $report -Encoding utf8 -Append
} else {
  "(no matching stale rows)" | Out-File -FilePath $report -Encoding utf8 -Append
}

Write-Host "CLEANUP_REPORT=$report"
