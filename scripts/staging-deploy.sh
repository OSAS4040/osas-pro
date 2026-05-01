#!/usr/bin/env bash
# Deploy current changes to staging-like Docker stack from containers.
# Usage:
#   make staging-deploy
# Optional env vars:
#   STAGING_REBUILD=1        # run docker compose build before up
#   STAGING_RUN_GATE=1       # run scripts/staging-gate.sh after deploy (default: 1)
#   STAGING_SEED_DEMO=0      # run dev:demo-seed after migrate (default: 0)
#   STAGING_HEALTH_WAIT_SECS # health timeout in seconds (default: 180)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

HEALTH_WAIT_SECS="${STAGING_HEALTH_WAIT_SECS:-180}"
STAGING_REBUILD="${STAGING_REBUILD:-0}"
STAGING_RUN_GATE="${STAGING_RUN_GATE:-1}"
STAGING_SEED_DEMO="${STAGING_SEED_DEMO:-0}"

wait_for_url() {
  local url="$1"
  local label="$2"
  local deadline=$(( $(date +%s) + HEALTH_WAIT_SECS ))
  local attempt=0
  while [ "$(date +%s)" -lt "$deadline" ]; do
    attempt=$((attempt + 1))
    if curl -sfS -o /dev/null --max-time 10 "$url"; then
      echo "OK: ${label} (attempt ${attempt})"
      return 0
    fi
    sleep 3
  done
  echo "ERROR: timeout waiting for ${label} within ${HEALTH_WAIT_SECS}s"
  return 1
}

echo "== Staging deploy from container stack =="

if [ "$STAGING_REBUILD" = "1" ]; then
  echo "== Build containers =="
  docker compose build
fi

echo "== Start services =="
docker compose up -d

echo "== Wait for app /up =="
wait_for_url "http://127.0.0.1/up" "Laravel /up"

echo "== Apply backend release steps =="
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
# opcache.validate_timestamps=0 => must recycle php-fpm to load new code/routes.
echo "== Reload app runtime (php-fpm/opcache) =="
docker compose restart app nginx
docker compose restart queue_high queue_default queue_pos queue_low scheduler

if [ "$STAGING_SEED_DEMO" = "1" ]; then
  echo "== Seed demo users/data (optional) =="
  docker compose exec -T app php artisan dev:demo-seed
fi

echo "== Wait for API health =="
wait_for_url "http://127.0.0.1/api/v1/health" "API health"

if [ "$STAGING_RUN_GATE" = "1" ]; then
  echo "== Run staging gate =="
  bash scripts/staging-gate.sh
fi

echo "== Staging deploy finished successfully =="
