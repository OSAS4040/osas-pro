<#
.SYNOPSIS
  فحص تقريبي «شامل»: Laravel PHPUnit + فرونت (lint / أنواع / vitest) + اختياري E2E للبوابات الثلاث (عميل، مزوّد، منصة).

.DESCRIPTION
  لا يوجد أمر واحد يغطي «كل خاصية» يدوياً؛ هذا السكربت يشغّل طبقات الاختبار الموجودة في المستودع.
  متطلبات E2E واقعية: API يعمل، بذرة Demo (انظر تعليقات ملفات e2e)، ومتغيرات بيئة عند الحاجة:
    PW_LOGIN_EMAIL / PW_LOGIN_PASSWORD (موظف/إدارة إن طُلب)
    PW_CUSTOMER_PORTAL_EMAIL / PW_CUSTOMER_PORTAL_PASSWORD
    PW_EXECUTION_PARTNER_EMAIL / PW_EXECUTION_PARTNER_PASSWORD (افتراضي في provider spec)

  تشغيل Playwright مع خادم جاهز (بدون build تلقائي):
    $env:PLAYWRIGHT_NO_WEB_SERVER = "1"
    $env:PLAYWRIGHT_BASE_URL = "http://127.0.0.1:4173"
    pwsh -File scripts\system-full-check.ps1 -SkipBackend -E2eOnly

.EXAMPLE
  pwsh -NoProfile -ExecutionPolicy Bypass -File scripts\system-full-check.ps1

.EXAMPLE
  pwsh -File scripts\system-full-check.ps1 -SkipBackend

.EXAMPLE
  فرونت فقط ثم E2E البوابات الثلاث:
  pwsh -File scripts\system-full-check.ps1 -SkipBackend -E2eOnly
#>
#Requires -Version 5.1
param(
  [switch]$SkipBackend,
  [switch]$SkipFrontend,
  [switch]$SkipE2e,
  [switch]$E2eOnly
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

function Invoke-Step {
  param([string]$Label, [scriptblock]$Block)
  Write-Host "`n=== $Label ===" -ForegroundColor Cyan
  & $Block
  if ($LASTEXITCODE -ne 0 -and $null -ne $LASTEXITCODE) {
    exit $LASTEXITCODE
  }
}

if ($E2eOnly) {
  $SkipBackend = $true
  $SkipFrontend = $true
}

if (-not $SkipBackend) {
  Invoke-Step "Backend: php artisan test (Docker)" {
    Set-Location $root
    docker compose exec -T app php artisan test
  }
}

if (-not $SkipFrontend) {
  Invoke-Step "Frontend: lint" {
    Set-Location (Join-Path $root "frontend")
    npm run lint:check
  }
  Invoke-Step "Frontend: vue-tsc" {
    Set-Location (Join-Path $root "frontend")
    npm run type-check
  }
  Invoke-Step "Frontend: vitest" {
    Set-Location (Join-Path $root "frontend")
    npm run test
  }
}

if (-not $SkipE2e) {
  Invoke-Step "E2E: بوابات عميل + مزوّد + منصة (npm run test:e2e:three-portals)" {
    Set-Location (Join-Path $root "frontend")
    npm run test:e2e:three-portals
  }
}

Write-Host "`nDone." -ForegroundColor Green
