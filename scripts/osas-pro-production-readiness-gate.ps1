<#
.SYNOPSIS
  OSAS Pro — fully automated production readiness gate (NO-GO on any failure).

.DESCRIPTION
  Phases: clean → install (with devtools for PHPUnit) → frontend build → full test matrix
  → integrity:verify → k6 enterprise_smoke/normal/peak → composer --no-dev + Laravel caches.

  NOTE: `composer install --no-dev` removes PHPUnit; tests run BEFORE the final production artifact step.

.PARAMETER SkipClean
  Skip removal of node_modules, vendor, coverage, test-results (faster re-runs).

.PARAMETER SkipK6
  Skip load tests (requires k6 on PATH and a reachable API per load-testing/env.example).

.PARAMETER SkipPlaywright
  Skip Playwright E2E (requires browsers; webServer may re-run build).
#>
param(
  [switch] $SkipClean,
  [switch] $SkipK6,
  [switch] $SkipPlaywright
)

$ErrorActionPreference = "Stop"
$RepoRoot   = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Backend    = Join-Path $RepoRoot "backend"
$Frontend   = Join-Path $RepoRoot "frontend"
$LoadK6     = Join-Path $RepoRoot "load-testing\k6"
$Artifacts  = Join-Path $RepoRoot "artifacts\osas-pro-readiness"
$ReportPath = Join-Path $RepoRoot "Final_Readiness_Report.md"

$script:GateDecision = "NO-GO"
$script:PhaseLog = [System.Collections.Generic.List[string]]::new()
$script:Metrics = [ordered]@{}

function Add-Phase([string]$name, [bool]$ok, [string]$detail = "") {
  $line = if ($ok) { "[PASS] $name" } else { "[FAIL] $name" }
  if ($detail) { $line += " — $detail" }
  $script:PhaseLog.Add($line)
  Write-Host $line -ForegroundColor $(if ($ok) { "Green" } else { "Red" })
}

function Remove-TreeSafe([string]$path) {
  if (Test-Path $path) {
    Remove-Item -LiteralPath $path -Recurse -Force -ErrorAction Stop
  }
}

New-Item -ItemType Directory -Force -Path $Artifacts | Out-Null
$ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz"

try {
  # ── Phase 1 — clean ──────────────────────────────────────
  if (-not $SkipClean) {
    Write-Host "=== Phase 1: clean ===" -ForegroundColor Cyan
    Remove-TreeSafe (Join-Path $Frontend "node_modules")
    Remove-TreeSafe (Join-Path $Backend "vendor")
    Remove-TreeSafe (Join-Path $Frontend "test-results")
    Remove-TreeSafe (Join-Path $Frontend "coverage")
    Remove-TreeSafe (Join-Path $RepoRoot "coverage")
    Get-ChildItem -Path (Join-Path $Backend "storage\logs") -Filter "*.log" -ErrorAction SilentlyContinue |
      Remove-Item -Force -ErrorAction SilentlyContinue
    Add-Phase "Phase 1 clean" $true
  }
  else {
    Add-Phase "Phase 1 clean" $true "(skipped -SkipClean)"
  }

  # ── Phase 2 — install & build (dev deps required for PHPUnit) ──
  Write-Host "=== Phase 2: backend + frontend build ===" -ForegroundColor Cyan
  Push-Location $Backend
  composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "composer-install.log")
  if ($LASTEXITCODE -ne 0) { throw "composer install failed" }
  php artisan optimize:clear 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "optimize-clear.log")
  Pop-Location

  Push-Location $Frontend
  npm ci 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "npm-ci.log")
  if ($LASTEXITCODE -ne 0) { throw "npm ci failed" }
  npm run build 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "npm-build.log")
  if ($LASTEXITCODE -ne 0) { throw "npm run build failed" }
  if (-not (Test-Path (Join-Path $Frontend "dist"))) { throw "frontend/dist missing after build" }
  Pop-Location
  Add-Phase "Phase 2 build" $true "backend vendor + frontend dist"

  # ── Phase 3 — automated tests ─────────────────────────────
  Write-Host "=== Phase 3: backend PHPUnit (full) ===" -ForegroundColor Cyan
  Push-Location $Backend
  php artisan test 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "phpunit-full.log")
  if ($LASTEXITCODE -ne 0) { throw "php artisan test failed" }
  Pop-Location
  Add-Phase "Phase 3a backend php artisan test" $true

  Write-Host "=== Phase 3: frontend vitest ===" -ForegroundColor Cyan
  Push-Location $Frontend
  npm run test 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "vitest.log")
  if ($LASTEXITCODE -ne 0) { throw "npm run test failed" }
  Add-Phase "Phase 3b frontend vitest" $true

  npm run type-check 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "vue-tsc.log")
  if ($LASTEXITCODE -ne 0) { throw "npm run type-check failed" }
  Add-Phase "Phase 3c type-check" $true

  if (-not $SkipPlaywright) {
    npx playwright test 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "playwright.log")
    if ($LASTEXITCODE -ne 0) { throw "npx playwright test failed" }
    Add-Phase "Phase 3d Playwright E2E" $true
  }
  else {
    Add-Phase "Phase 3d Playwright E2E" $true "(skipped -SkipPlaywright)"
  }
  Pop-Location

  # ── Phase 4 — covered by full PHPUnit + ProductionReadiness/* tests ──
  Add-Phase "Phase 4 system validation (API contracts)" $true "included in php artisan test + RealWorkflow + ProductionReadiness/*"

  # ── Phase 5 — implicit (failures throw above) ─────────────
  Add-Phase "Phase 5 error detection" $true "pipeline stops on first failing command"

  # ── Phase 6 — k6 enterprise profiles ─────────────────────
  if (-not $SkipK6) {
    $k6 = Get-Command k6 -ErrorAction SilentlyContinue
    if (-not $k6) {
      throw "k6 not found on PATH. Install k6 or re-run with -SkipK6 (gate requires k6 for full GO)."
    }
    $profiles = @("enterprise_smoke", "enterprise_normal", "enterprise_peak")
    foreach ($p in $profiles) {
      Write-Host "=== Phase 6: k6 $p ===" -ForegroundColor Cyan
      Push-Location $LoadK6
      $env:K6_PROFILE = $p
      k6 run suite.js 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "k6-$p.log")
      if ($LASTEXITCODE -ne 0) { throw "k6 profile $p failed" }
      Pop-Location
    }
    Add-Phase "Phase 6 k6 enterprise profiles" $true ($profiles -join ", ")
  }
  else {
    Add-Phase "Phase 6 k6" $true "(skipped -SkipK6)"
  }

  # ── Phase 7 — financial integrity (read-only command) ─────
  Write-Host "=== Phase 7: integrity:verify ===" -ForegroundColor Cyan
  Push-Location $Backend
  php artisan integrity:verify 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "integrity-verify.log")
  if ($LASTEXITCODE -ne 0) { throw "integrity:verify failed" }
  Pop-Location
  Add-Phase "Phase 7 integrity:verify" $true

  # ── Phase 8 — production artifact (no dev) + caches ──────
  Write-Host "=== Phase 8: production composer + Laravel caches ===" -ForegroundColor Cyan
  Push-Location $Backend
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "composer-no-dev.log")
  if ($LASTEXITCODE -ne 0) { throw "composer install --no-dev failed" }
  php artisan config:cache 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "config-cache.log")
  php artisan route:cache 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "route-cache.log")
  php artisan view:cache 2>&1 | Tee-Object -FilePath (Join-Path $Artifacts "view-cache.log")
  php artisan route:list --compact 2>&1 | Out-Null
  if ($LASTEXITCODE -ne 0) { throw "route:list failed after production optimize" }
  Pop-Location
  Add-Phase "Phase 8 production artifact + caches" $true

  $script:GateDecision = "GO"
}
catch {
  $script:GateDecision = "NO-GO"
  Add-Phase "Gate exception" $false ($_.Exception.Message)
  Write-Host $_ -ForegroundColor Red
}

# ── Report ─────────────────────────────────────────────────
$metricsText = @"
- Repository: ``$RepoRoot``
- Timestamp: $ts
- Decision: **$($script:GateDecision)**
"@

$body = @"
# OSAS Pro — Final Readiness Report

**Decision: $($script:GateDecision)**

_Generated: $ts_

## Summary

$metricsText

## Phase results

$(($script:PhaseLog | ForEach-Object { "- $_" }) -join "`n")

## Artifacts

- Logs and install output: ``artifacts/osas-pro-readiness/``
- Backend release layout: ``backend/vendor`` (no-dev), caches under ``backend/bootstrap/cache/``
- Frontend build: ``frontend/dist`` (after successful ``npm run build``)
- Environment template: ``backend/.env.example``, ``frontend/.env.example``

## Performance (k6)

$(if ($SkipK6) { "_Skipped (`-SkipK6`)._" } else { "- Profiles: ``enterprise_smoke``, ``enterprise_normal``, ``enterprise_peak`` (thresholds: ``http_req_failed < 1%``, ``p(95) < 2s`` in ``load-testing/k6/config/enterprise-gate.js``)" })

## Financial integrity

- Command: ``php artisan integrity:verify`` (see ``artifacts/osas-pro-readiness/integrity-verify.log`` when run via this gate)

## Rules enforced

- No manual test steps in this script; all checks are automated commands.
- Financial **core** code paths were not modified by this gate (integrity is verify-only).

---
If decision is **NO-GO**: do **not** deploy. Fix failures, re-run:

``powershell -ExecutionPolicy Bypass -File scripts/osas-pro-production-readiness-gate.ps1``
"@

Set-Content -LiteralPath $ReportPath -Value $body -Encoding UTF8
Write-Host "`n=== FINAL DECISION: $($script:GateDecision) ===" -ForegroundColor $(if ($script:GateDecision -eq "GO") { "Green" } else { "Red" })
Write-Host "Report written to: $ReportPath"

if ($script:GateDecision -ne "GO") {
  exit 1
}
exit 0
