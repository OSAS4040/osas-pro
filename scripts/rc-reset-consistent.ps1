param(
  [string]$PgUser = "saas_user",
  [string]$PgDb = "saas_db",
  [int]$CleanupOlderThanMinutes = 30
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

$stateDir = Join-Path $root "load-testing/reports/rc-policy"
New-Item -ItemType Directory -Path $stateDir -Force | Out-Null
$stateFile = Join-Path $stateDir "state.json"

$seedStartedAt = (Get-Date).ToString("o")
docker compose exec -T app php artisan db:seed --force
if ($LASTEXITCODE -ne 0) { throw "Seed failed with exit code $LASTEXITCODE" }
$seedFinishedAt = (Get-Date).ToString("o")

& powershell -ExecutionPolicy Bypass -File (Join-Path $root "scripts/cleanup-failed-jobs-targeted.ps1") `
  -PgUser $PgUser -PgDb $PgDb -OlderThanMinutes $CleanupOlderThanMinutes
if ($LASTEXITCODE -ne 0) { throw "Targeted cleanup failed with exit code $LASTEXITCODE" }
$cleanupFinishedAt = (Get-Date).ToString("o")

$state = @{
  policy_version = 1
  status = "clean_ready"
  seed_started_at = $seedStartedAt
  seed_finished_at = $seedFinishedAt
  cleanup_finished_at = $cleanupFinishedAt
  notes = "Seed then targeted cleanup completed in consistent order."
}
$state | ConvertTo-Json -Depth 4 | Out-File -FilePath $stateFile -Encoding utf8

Write-Host "RC_POLICY_STATE=$stateFile"
