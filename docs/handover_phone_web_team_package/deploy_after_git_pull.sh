#!/usr/bin/env bash
# بعد git pull على الوسم/الفرع المعتمد: composer + migrate + build للواجهة.
# يمكن تشغيله من أي مسار داخل المستودع؛ يبحث عن backend/artisan صعوداً.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR"
while [[ ! -f "$ROOT/backend/artisan" && "$(dirname "$ROOT")" != "$ROOT" ]]; do
  ROOT="$(dirname "$ROOT")"
done
if [[ ! -f "$ROOT/backend/artisan" ]]; then
  echo "خطأ: لم يُعثر على backend/artisan — شغّل السكربت من داخل مستودع المشروع."
  exit 1
fi

echo "== جذر المشروع: $ROOT =="

echo "== Backend: composer (إنتاج) =="
cd "$ROOT/backend"
composer install --no-dev --optimize-autoloader --no-interaction

echo "== Backend: migrate =="
php artisan migrate --force

echo "== Backend: كاش إنتاج (عدّل إن لزم) =="
php artisan optimize:clear || true
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

echo "== Frontend: build =="
cd "$ROOT/frontend"
if [[ -f package-lock.json ]]; then
  npm ci
else
  npm install
fi
npm run build

echo "== انتهى. اربطوا nginx (أو Docker) بمجلد frontend/dist. =="
echo "== تحققوا من Twilio / PHONE_OTP في .env للإنتاج. =="
