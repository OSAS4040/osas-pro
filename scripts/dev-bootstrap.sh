#!/usr/bin/env sh
# Unix/Git Bash: نفس منطق make dev-bootstrap (migrate + seed محلي فقط)
set -e
cd "$(dirname "$0")/.."
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan dev:demo-seed
