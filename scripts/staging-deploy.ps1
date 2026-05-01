<#
.SYNOPSIS
  Deploy current changes to staging-like Docker stack from containers.

.DESCRIPTION
  Equivalent to scripts/staging-deploy.sh for Windows users without Git Bash.
  Steps:
  - Optional docker compose build
  - docker compose up -d
  - Wait for /up
  - migrate --force + optimize:clear + queue restart
  - Optional demo seed
  - Wait for /api/v1/health
  - Optional staging gate

.EXAMPLE
  pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/staging-deploy.ps1
#>
[CmdletBinding()]
param(
  [switch]$Rebuild,
  [switch]$SkipGate,
  [switch]$SeedDemo,
  [int]$HealthWaitSecs = 180
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

function Wait-ForUrl {
  param(
    [Parameter(Mandatory = $true)][string]$Url,
    [Parameter(Mandatory = $true)][string]$Label,
    [Parameter(Mandatory = $true)][int]$TimeoutSecs
  )

  $deadline = (Get-Date).AddSeconds($TimeoutSecs)
  $attempt = 0
  while ((Get-Date) -lt $deadline) {
    $attempt++
    try {
      Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10 | Out-Null
      Write-Host "OK: $Label (attempt $attempt)" -ForegroundColor Green
      return
    } catch {
      Start-Sleep -Seconds 3
    }
  }
  throw "Timeout waiting for $Label within ${TimeoutSecs}s"
}

Write-Host "== Staging deploy from container stack ==" -ForegroundColor Cyan

if ($Rebuild) {
  Write-Host "== Build containers =="
  docker compose build
  if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "== Start services =="
docker compose up -d
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "== Wait for app /up =="
Wait-ForUrl -Url "http://127.0.0.1/up" -Label "Laravel /up" -TimeoutSecs $HealthWaitSecs

Write-Host "== Apply backend release steps =="
docker compose exec -T app php artisan migrate --force
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
docker compose exec -T app php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
# opcache.validate_timestamps=0 => must recycle php-fpm to load new code/routes.
Write-Host "== Reload app runtime (php-fpm/opcache) ==" 
docker compose restart app nginx
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
docker compose restart queue_high queue_default queue_pos queue_low scheduler
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

if ($SeedDemo) {
  Write-Host "== Seed demo users/data (optional) =="
  docker compose exec -T app php artisan dev:demo-seed
  if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "== Wait for API health =="
Wait-ForUrl -Url "http://127.0.0.1/api/v1/health" -Label "API health" -TimeoutSecs $HealthWaitSecs

if (-not $SkipGate) {
  Write-Host "== Run staging gate =="
  powershell -NoProfile -ExecutionPolicy Bypass -File scripts/staging-gate.ps1
  if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host "== Staging deploy finished successfully ==" -ForegroundColor Green
exit 0
