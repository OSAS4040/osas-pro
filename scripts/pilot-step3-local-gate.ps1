<#
.SYNOPSIS
  جزء آلي من الخطوة 3 (Pilot): سياسة env + فحص محلي للـ API والواجهة؛ اختياري E2E كما في CI.

.DESCRIPTION
  - A1 من Staging_Manual_Test_Checklist: check-policy-env-example
  - فحص GET /api/v1/health (Docker/nginx محلي)
  - اختياري: GET على جذر الواجهة (افتراضي نفس منفذ nginx)
  - -WithE2e: من مجلد frontend — npm ci + playwright chromium + npm run test:ci
  - -WithOcrVerify: نفس فحص Tesseract كما في نهاية staging-gate

  لا يغني عن البنود ب، ج، د، هـ على Staging الحقيقي.

.PARAMETER ApiBaseUrl
  مثال: http://127.0.0.1

.PARAMETER FrontendBaseUrl
  مثال: http://127.0.0.1 أو http://127.0.0.1:5173 عند Vite dev

.PARAMETER SkipFrontend
  يتخطى فحص الجذر (مفيد عند 502 بدون SPA)

.PARAMETER WithE2e
  يشغّل npm run test:ci (lint + vue-tsc + vitest + playwright)

.PARAMETER WithOcrVerify
  بعد فحص الصحة: `docker compose exec -T app php artisan ocr:verify --fail` (يتطلب حاوية app شغّالة).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/pilot-step3-local-gate.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/pilot-step3-local-gate.ps1 -WithE2e

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/pilot-step3-local-gate.ps1 -WithOcrVerify
#>
param(
  [string]$ApiBaseUrl = "http://127.0.0.1",
  [string]$FrontendBaseUrl = "http://127.0.0.1",
  [switch]$SkipFrontend,
  [switch]$WithE2e,
  [switch]$WithOcrVerify
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

function Step([string]$title) {
  Write-Host ""
  Write-Host "=== $title ==="
}

Step "A1: policy env examples (check-policy-env-example)"
node scripts/check-policy-env-example.mjs
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Step "Local stack: GET $ApiBaseUrl/api/v1/health"
try {
  $health = Invoke-WebRequest -Uri "$ApiBaseUrl/api/v1/health" -UseBasicParsing -TimeoutSec 20
} catch {
  Write-Error "Health request failed: $_"
  exit 1
}
if ($health.StatusCode -ne 200) {
  Write-Error "Expected HTTP 200 from health, got $($health.StatusCode)"
  exit 1
}
$body = $health.Content
if ($body -notmatch 'healthy|"ok"|"status"') {
  Write-Warning "Health body unexpected (no obvious healthy marker): $($body.Substring(0, [Math]::Min(200, $body.Length)))"
}
Write-Host "OK: HTTP $($health.StatusCode)"

if (-not $SkipFrontend) {
  Step "Local stack: GET $FrontendBaseUrl (SPA / nginx)"
  try {
    $fe = Invoke-WebRequest -Uri $FrontendBaseUrl -UseBasicParsing -TimeoutSec 20 -MaximumRedirection 5
    if ($fe.StatusCode -lt 200 -or $fe.StatusCode -ge 400) {
      throw "HTTP $($fe.StatusCode)"
    }
    Write-Host "OK: HTTP $($fe.StatusCode)"
  } catch {
    Write-Warning "Frontend root check failed: $_"
    Write-Host "TIP: use -SkipFrontend or -FrontendBaseUrl http://127.0.0.1:5173 if Vite dev runs separately."
  }
}

if ($WithOcrVerify) {
  Step "OCR: php artisan ocr:verify --fail (app container)"
  docker compose exec -T app sh -lc "cd /var/www && php artisan ocr:verify --fail"
  $ocrExit = $LASTEXITCODE
  if ($ocrExit -ne 0) {
    Write-Error "ocr:verify failed (exit $ocrExit)"
    exit $ocrExit
  }
  Write-Host "OK: ocr:verify"
}

if ($WithE2e) {
  Step "Frontend test:ci (lint + type-check + vitest + playwright)"
  Push-Location (Join-Path $root "frontend")
  try {
    npm ci
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    npx playwright install chromium
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    $env:CI = "true"
    npm run test:ci
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
  } finally {
    Pop-Location
  }
}

Write-Host ""
Write-Host "STEP 3 (automated local gate): PASS"
Write-Host "NEXT (manual on real Staging): docs/Staging_Manual_Test_Checklist.md sections B, C, D, H."
Write-Host "Then Pilot step 4: one-sentence scope + full walkthrough on Staging (docs/Pilot_Phase_Safe_Next_Steps.md)."
exit 0
