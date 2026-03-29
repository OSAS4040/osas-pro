#!/usr/bin/env bash
# Run inside the backend container:
# docker compose exec backend bash /var/www/run-tests.sh

set -e

echo "======================================================"
echo " Running Financial + Inventory Test Suite"
echo " Environment: testing"
echo "======================================================"

cd /var/www

# 1. Fresh test DB
php artisan migrate:fresh --env=testing --seed=false --force

echo ""
echo "--- Wallet Tests ---"
php artisan test tests/Feature/Wallet/ --env=testing --stop-on-failure

echo ""
echo "--- POS Tests ---"
php artisan test tests/Feature/POS/ --env=testing --stop-on-failure

echo ""
echo "--- Inventory Tests ---"
php artisan test tests/Feature/Inventory/ --env=testing --stop-on-failure

echo ""
echo "--- Work Order Tests ---"
php artisan test tests/Feature/WorkOrder/ --env=testing --stop-on-failure

echo ""
echo "--- Integration Tests ---"
php artisan test tests/Feature/Integration/ --env=testing --stop-on-failure

echo ""
echo "======================================================"
echo " ALL SUITES PASSED"
echo "======================================================"
