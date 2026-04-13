#!/usr/bin/env bash
# OSAS Pro — production readiness gate (Unix). Mirrors scripts/osas-pro-production-readiness-gate.ps1
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKEND="$ROOT/backend"
FRONTEND="$ROOT/frontend"
LOAD_K6="$ROOT/load-testing/k6"
ART="$ROOT/artifacts/osas-pro-readiness"
REPORT="$ROOT/Final_Readiness_Report.md"

SKIP_CLEAN=0
SKIP_K6=0
SKIP_PLAYWRIGHT=0
for arg in "$@"; do
  case "$arg" in
    --skip-clean) SKIP_CLEAN=1 ;;
    --skip-k6) SKIP_K6=1 ;;
    --skip-playwright) SKIP_PLAYWRIGHT=1 ;;
  esac
done

mkdir -p "$ART"
DECISION="NO-GO"
PHASES=()

pass() { PHASES+=("[PASS] $1"); echo "[PASS] $1"; }
write_report() {
  local TS
  TS="$(date -Iseconds)"
  {
    echo "# OSAS Pro — Final Readiness Report"
    echo "**Decision: $DECISION**"
    echo "_Generated: $TS"
    echo
    echo "## Phase results"
    printf '%s\n' "${PHASES[@]:-}"
    echo
    echo "## Artifacts"
    echo "- \`artifacts/osas-pro-readiness/\`"
    echo "- \`backend/.env.example\`, \`frontend/.env.example\`"
  } > "$REPORT"
  echo "Report: $REPORT"
}
die() { PHASES+=("[FAIL] $1"); echo "[FAIL] $1" >&2; DECISION="NO-GO"; write_report; exit 1; }

if [[ "$SKIP_CLEAN" -eq 0 ]]; then
  rm -rf "$FRONTEND/node_modules" "$BACKEND/vendor" "$FRONTEND/test-results" "$FRONTEND/coverage" "$ROOT/coverage" || true
  rm -f "$BACKEND/storage/logs"/*.log 2>/dev/null || true
  pass "Phase 1 clean"
else
  pass "Phase 1 clean (skipped)"
fi

cd "$BACKEND"
composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1 | tee "$ART/composer-install.log"
php artisan optimize:clear 2>&1 | tee "$ART/optimize-clear.log"

cd "$FRONTEND"
npm ci 2>&1 | tee "$ART/npm-ci.log"
npm run build 2>&1 | tee "$ART/npm-build.log"
[[ -d dist ]] || die "frontend/dist missing after build"
pass "Phase 2 build"

cd "$BACKEND"
php artisan test 2>&1 | tee "$ART/phpunit-full.log"
pass "Phase 3a backend php artisan test"

cd "$FRONTEND"
npm run test 2>&1 | tee "$ART/vitest.log"
npm run type-check 2>&1 | tee "$ART/vue-tsc.log"
if [[ "$SKIP_PLAYWRIGHT" -eq 0 ]]; then
  npx playwright test 2>&1 | tee "$ART/playwright.log"
else
  pass "Phase 3d Playwright (skipped)"
fi
pass "Phase 3b-d frontend"

pass "Phase 4 system validation (API contracts in PHPUnit + RealWorkflow)"

if [[ "$SKIP_K6" -eq 0 ]]; then
  command -v k6 >/dev/null || die "k6 not on PATH (install k6 or use --skip-k6)"
  for p in enterprise_smoke enterprise_normal enterprise_peak; do
    ( cd "$LOAD_K6" && export K6_PROFILE="$p" && k6 run suite.js 2>&1 | tee "$ART/k6-$p.log" )
  done
  pass "Phase 6 k6 enterprise_smoke + enterprise_normal + enterprise_peak"
else
  pass "Phase 6 k6 (skipped)"
fi

cd "$BACKEND"
php artisan integrity:verify 2>&1 | tee "$ART/integrity-verify.log"
pass "Phase 7 integrity:verify"

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader 2>&1 | tee "$ART/composer-no-dev.log"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan route:list --compact >/dev/null
pass "Phase 8 production composer + caches"

DECISION="GO"
write_report
echo "=== FINAL DECISION: $DECISION ==="
exit 0
