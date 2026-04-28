<#
.SYNOPSIS
  فحوصات قراءة فقط قبل بدء مرحلة الـ Pilot / Staging (لا تعدّل قاعدة بيانات ولا تنشر).

.DESCRIPTION
  - سياسة ملفات env النموذجية (Node)
  - طلب GET على /api/v1/health
  - اختياري: التحقق أن الواجهة تستجيب (HEAD أو GET على الجذر)
  - اختياري: docker compose ps (حالة الحاويات)
  - اختياري -WithOcrVerify: php artisan ocr:verify --fail داخل حاوية app
  - مكافئ bash: scripts/preflight-pilot-readonly.sh (انظر --help)

.PARAMETER ApiBaseUrl
  أصل API بدون مسار صحي، مثال: https://staging.example.com أو http://127.0.0.1

.PARAMETER FrontendBaseUrl
  إن وُجد: يُجرَب طلب HEAD على الجذر (مثال: http://127.0.0.1)

.PARAMETER SkipDocker
  لا يستدعي docker compose ps

.PARAMETER SkipFrontend
  يتخطى فحص الواجهة حتى لو وُجد FrontendBaseUrl (مفيد إذا nginx يعيد 502 محلياً أو تريد التحقق من API فقط).

.PARAMETER WithOcrVerify
  بعد فحوص القراءة: يشغّل `docker compose exec -T app php artisan ocr:verify --fail` (يتطلب حاوية app شغّالة محلياً).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/preflight-pilot-readonly.ps1

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/preflight-pilot-readonly.ps1 -ApiBaseUrl "https://staging.mycompany.com" -FrontendBaseUrl "https://staging.mycompany.com"

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/preflight-pilot-readonly.ps1 -ApiBaseUrl "http://127.0.0.1" -SkipFrontend

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts/preflight-pilot-readonly.ps1 -WithOcrVerify
#>
param(
  [string]$ApiBaseUrl = "http://127.0.0.1",
  [string]$FrontendBaseUrl = "",
  [switch]$SkipDocker,
  [switch]$SkipFrontend,
  [switch]$WithOcrVerify
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

function Write-Step([string]$msg) {
  Write-Host ""
  Write-Host "=== $msg ==="
}

$failed = $false

Write-Step "Policy: env examples (read-only)"
& node scripts/check-policy-env-example.mjs
if ($LASTEXITCODE -ne 0) {
  Write-Host "FAIL: policy-env-example (exit $LASTEXITCODE)"
  $failed = $true
}
else {
  Write-Host "OK: policy-env-example"
}

$healthUrl = ($ApiBaseUrl.TrimEnd('/') + "/api/v1/health")
Write-Step "API health: GET $healthUrl"
try {
  $r = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -Method Get -TimeoutSec 20
  if ($r.StatusCode -ne 200) {
    Write-Host "FAIL: HTTP $($r.StatusCode)"
    $failed = $true
  }
  else {
    Write-Host "OK: HTTP 200"
    if ($r.Content -match '"status"\s*:\s*"healthy"') {
      Write-Host "OK: body reports healthy"
    }
    else {
      Write-Host "WARN: body may be degraded - review content"
      Write-Host $r.Content.Substring(0, [Math]::Min(300, $r.Content.Length))
    }
  }
}
catch {
  Write-Host "FAIL: health request - $($_.Exception.Message)"
  $failed = $true
}

if ($FrontendBaseUrl -ne "" -and -not $SkipFrontend) {
  $f = $FrontendBaseUrl.TrimEnd('/')
  Write-Step "Frontend: HEAD $f"
  try {
    $fr = Invoke-WebRequest -Uri $f -UseBasicParsing -Method Head -TimeoutSec 20
    Write-Host "OK: frontend HEAD $($fr.StatusCode)"
  }
  catch {
    Write-Host "WARN: HEAD failed, trying GET - $($_.Exception.Message)"
    try {
      $fr2 = Invoke-WebRequest -Uri $f -UseBasicParsing -Method Get -TimeoutSec 20
      Write-Host "OK: frontend GET $($fr2.StatusCode)"
    }
    catch {
      Write-Host "FAIL: frontend - $($_.Exception.Message)"
      $failed = $true
    }
  }
}

if (-not $SkipDocker) {
  Write-Step "Docker Compose (read-only status)"
  try {
    docker compose ps 2>&1 | Out-Host
  }
  catch {
    Write-Host "WARN: docker compose ps unavailable - $($_.Exception.Message)"
  }
}

if ($WithOcrVerify) {
  Write-Step "OCR: php artisan ocr:verify --fail (app container)"
  try {
    docker compose exec -T app sh -lc "cd /var/www && php artisan ocr:verify --fail"
    $ocrExit = $LASTEXITCODE
    if ($ocrExit -ne 0) {
      Write-Host "FAIL: ocr:verify (exit $ocrExit)"
      $failed = $true
    }
    else {
      Write-Host "OK: ocr:verify"
    }
  }
  catch {
    Write-Host "FAIL: ocr:verify - $($_.Exception.Message)"
    $failed = $true
  }
}

Write-Host ""
if ($failed) {
  Write-Host "PREFLIGHT RESULT: FAIL - review output above before Pilot stage."
  exit 1
}
Write-Host "PREFLIGHT RESULT: PASS - proceed to manual UAT (docs/Pilot_Phase_Safe_Next_Steps.md)."
exit 0
