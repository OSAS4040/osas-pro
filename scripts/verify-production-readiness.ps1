# بوابة جاهزية إنتاج محلية — تفترض Docker Compose يعمل (docker compose ps).
# الواجهة: lint + vue-tsc + vitest + npm audit
# الخلفية (داخل حاوية app): Laravel Pint --test + PHPUnit phase0..phase7 + php artisan ocr:verify --fail
$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $Root

Write-Host '== Frontend: lint, types, unit tests, npm audit ==' -ForegroundColor Cyan
Push-Location (Join-Path $Root 'frontend')
npm run test:ci:static
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
Pop-Location

Write-Host '== Backend: Pint (style check only) ==' -ForegroundColor Cyan
docker compose exec -T app sh -lc 'cd /var/www && ./vendor/bin/pint --test'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host '== Backend: PHPUnit phases 0-7 ==' -ForegroundColor Cyan
docker compose exec -T app sh -lc 'cd /var/www && php -d memory_limit=512M artisan config:clear'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
$phaseGroups = @(
    'phase0', 'phase1', 'phase2', 'phase3',
    'phase4', 'phase5', 'phase6', 'phase7'
)
foreach ($g in $phaseGroups) {
    Write-Host "== PHPUnit --group=$g ==" -ForegroundColor DarkCyan
    docker compose exec -T app sh -lc "cd /var/www && php -d memory_limit=512M ./vendor/bin/phpunit --group=$g"
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

Write-Host '== Backend: OCR verify ==' -ForegroundColor Cyan
docker compose exec -T app sh -lc 'cd /var/www && php -d memory_limit=512M artisan ocr:verify --fail'
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host '== Production readiness gate: OK ==' -ForegroundColor Green
