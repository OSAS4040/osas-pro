<#
.SYNOPSIS
  Staging gate: Vitest in frontend container + PHPUnit by phase 0–7 + OCR verify in app.

.DESCRIPTION
  Same behavior as bash scripts/staging-gate.sh — requires docker compose up (frontend + app).
  Runs `php artisan ocr:verify --fail` so Tesseract (eng+ara) matches Executive_Gate_Current_Phase.md.
  Use on Windows when Git Bash is not available.

.EXAMPLE
  pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/staging-gate.ps1
#>
#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

Write-Host "== Staging gate: Vitest (frontend) =="
docker compose exec -T frontend sh -lc "cd /app && npm ci && npm test"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "== Staging gate: PHPUnit phases 0-7 (includes Auth in phase0) =="
docker compose exec -T app sh -lc 'cd /var/www && php artisan config:clear && for g in phase0 phase1 phase2 phase3 phase4 phase5 phase6 phase7; do echo "== PHPUnit --group=$g ==" && ./vendor/bin/phpunit --group="$g" || exit 1; done'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "== Staging gate: OCR (Tesseract eng+ara in app container) =="
docker compose exec -T app sh -lc "cd /var/www && php artisan ocr:verify --fail"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "== Staging gate: OK =="
exit 0
