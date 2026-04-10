# Windows: نفس منطق make dev-bootstrap — migrate + dev:demo-seed (الأخير يعمل فقط عند APP_ENV=local).
# استخدمه إن لم يكن GNU make مثبتاً:  pwsh -File scripts/dev-bootstrap.ps1
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot/..
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan dev:demo-seed
