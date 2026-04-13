#!/usr/bin/env bash
# يطابق منطق `make staging-gate` — يتطلب تشغيلاً مسبقاً: docker compose up -d
# الاستخدام: من جذر المشروع: bash scripts/staging-gate.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "== Staging gate: Vitest (frontend) =="
docker compose exec -T frontend sh -lc "cd /app && npm ci && npm test"

echo "== Staging gate: PHPUnit (SaaS / platform) =="
# vendor/bin/phpunit يقرأ phpunit.xml (DB_HOST=postgres) — php artisan test قد يحمّل .env قبل PHPUnit فيُجبر 127.0.0.1 في بعض الإعدادات
docker compose exec -T app sh -lc "cd /var/www && php artisan config:clear && ./vendor/bin/phpunit tests/Unit/Support/SaasPlatformAccessTest.php tests/Feature/Saas/ tests/Feature/Auth/"

echo "== Staging gate: OK =="
