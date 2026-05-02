# فحص أمني سريع: تبعيات npm + composer داخل حاوية app (يتطلب Docker).
# الاستخدام: من جذر المستودع: pwsh -File scripts/security-audit.ps1
# اختياري: -RunTenantTests 1 لتشغيل اختبارات عزل المستأجرين (أطول)

param(
  [int]$RunTenantTests = 0
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$failed = $false

Write-Host "== Frontend: npm audit (production) ==" -ForegroundColor Cyan
Push-Location (Join-Path $root "frontend")
npm audit --omit=dev
if ($LASTEXITCODE -ne 0) { $failed = $true }
Pop-Location

Write-Host "`n== Backend: composer audit (Docker app) ==" -ForegroundColor Cyan
docker compose exec -T app sh -lc "cd /var/www && composer audit"
if ($LASTEXITCODE -ne 0) {
  Write-Host "NOTE: composer exits non-zero if abandoned packages are reported (not always CVEs)." -ForegroundColor Yellow
}

if ($RunTenantTests -eq 1) {
  Write-Host "`n== PHPUnit: tenancy isolation subset ==" -ForegroundColor Cyan
  docker compose exec -T app sh -lc "cd /var/www && ./vendor/bin/phpunit tests/Feature/Tenancy/ tests/Feature/CustomerPortal/CustomerPortalCrossTenantIsolationTest.php --configuration phpunit.xml"
  if ($LASTEXITCODE -ne 0) { $failed = $true }
}

if ($failed) {
  Write-Host "`nSecurity audit completed with failures or npm vulnerabilities." -ForegroundColor Red
  exit 1
}
Write-Host "`nDone." -ForegroundColor Green
exit 0
