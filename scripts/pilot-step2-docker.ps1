<#
.SYNOPSIS
  Pilot step 2 on Windows: same as make staging-gate + make verify + integrity-verify (Docker).

.DESCRIPTION
  Requires: docker compose stack up (frontend + app). Stops on first non-zero exit.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/pilot-step2-docker.ps1
#>
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

function Step([string]$title) {
  Write-Host ""
  Write-Host "=== $title ==="
}

Step "staging-gate: Vitest (frontend container)"
docker compose exec -T frontend sh -lc "cd /app && npm ci && npm test"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Step "staging-gate: PHPUnit SaaS path"
docker compose exec -T app sh -lc "cd /var/www && ./vendor/bin/phpunit tests/Unit/Support/SaasPlatformAccessTest.php tests/Feature/Saas/"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Step "verify: lint + build (frontend)"
docker compose exec -T frontend sh -lc "cd /app && npm run lint:check && npm run build"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Step "verify: full PHPUnit (app)"
docker compose exec -T app sh -lc "cd /var/www && php artisan config:clear && ./vendor/bin/phpunit"
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Step "integrity:verify"
docker compose exec -T app php artisan integrity:verify
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host ""
Write-Host "PILOT STEP 2: PASS - next: docs/Staging_Manual_Test_Checklist.md (section 3 in Pilot_Phase_Safe_Next_Steps.md)."
exit 0
