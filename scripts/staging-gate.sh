#!/usr/bin/env bash
# يطابق منطق `make staging-gate` — يتطلب تشغيلاً مسبقاً: docker compose up -d
# يتضمن: Vitest + PHPUnit 0–7 + التحقق من Tesseract (OCR) داخل حاوية app — انظر docs/Executive_Gate_Current_Phase.md
# الاستخدام: من جذر المشروع: bash scripts/staging-gate.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "== Staging gate: Vitest (frontend) =="
docker compose exec -T frontend sh -lc "cd /app && npm ci && npm test"

echo "== Staging gate: PHPUnit phases 0–7 (مرحلة مرحلة؛ يشمل phase0 اختبارات Auth) =="
docker compose exec -T app sh -lc 'cd /var/www && php artisan config:clear && for g in phase0 phase1 phase2 phase3 phase4 phase5 phase6 phase7; do echo "== PHPUnit --group=$g ==" && ./vendor/bin/phpunit --group="$g" || exit 1; done'

echo "== Staging gate: OCR (Tesseract eng+ara في حاوية app) =="
docker compose exec -T app sh -lc "cd /var/www && php artisan ocr:verify --fail"

echo "== Staging gate: OK =="
