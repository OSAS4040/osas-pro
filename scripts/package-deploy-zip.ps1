# Creates a ZIP of tracked files only (git archive). Run from repo root:
#   powershell -ExecutionPolicy Bypass -File scripts/package-deploy-zip.ps1
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $root '.git'))) {
    Write-Error 'Run from a git repository root (folder containing .git).'
}
Set-Location $root
$rel = Join-Path $root 'releases'
if (-not (Test-Path $rel)) { New-Item -ItemType Directory -Path $rel | Out-Null }
$name = "saas-deploy-$(Get-Date -Format 'yyyyMMdd-HHmm').zip"
$out = Join-Path $rel $name
& git archive --format=zip -o $out HEAD
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Write-Host "Created: $out"
Write-Host "On server: composer install (backend), npm ci && npm run build (frontend) with env, php artisan migrate"
