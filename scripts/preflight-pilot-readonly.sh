#!/usr/bin/env bash
# فحوصات قراءة فقط قبل Pilot / Staging (نفس فكرة preflight-pilot-readonly.ps1).
# الاستخدام من جذر المشروع:
#   bash scripts/preflight-pilot-readonly.sh
#   API_BASE_URL=https://staging.example.com FRONTEND_BASE_URL=https://staging.example.com bash scripts/preflight-pilot-readonly.sh
#   bash scripts/preflight-pilot-readonly.sh --skip-frontend --with-ocr-verify
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

API_BASE="${API_BASE_URL:-http://127.0.0.1}"
FRONTEND_BASE="${FRONTEND_BASE_URL:-}"
SKIP_DOCKER=0
SKIP_FRONTEND=0
WITH_OCR=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --api-url=*) API_BASE="${1#*=}" ;;
    --frontend-url=*) FRONTEND_BASE="${1#*=}" ;;
    --skip-docker) SKIP_DOCKER=1 ;;
    --skip-frontend) SKIP_FRONTEND=1 ;;
    --with-ocr-verify) WITH_OCR=1 ;;
    -h|--help)
      echo "Usage: $0 [--api-url=URL] [--frontend-url=URL] [--skip-docker] [--skip-frontend] [--with-ocr-verify]"
      echo "Env: API_BASE_URL, FRONTEND_BASE_URL (optional defaults for URLs)"
      exit 0
      ;;
    *)
      echo "Unknown option: $1 (try --help)" >&2
      exit 2
      ;;
  esac
  shift
done

# Trim trailing slash on API_BASE
API_BASE="${API_BASE%/}"
FRONTEND_BASE="${FRONTEND_BASE%/}"

failed=0
step() { echo ""; echo "=== $1 ==="; }

step "Policy: env examples (read-only)"
if ! node scripts/check-policy-env-example.mjs; then
  echo "FAIL: policy-env-example"
  failed=1
else
  echo "OK: policy-env-example"
fi

health_url="${API_BASE}/api/v1/health"
step "API health: GET ${health_url}"
if ! out="$(curl -sfS --max-time 20 "$health_url")"; then
  echo "FAIL: health request"
  failed=1
else
  echo "OK: HTTP 200"
  if [[ "$out" =~ \"status\"[[:space:]]*:[[:space:]]*\"healthy\" ]]; then
    echo "OK: body reports healthy"
  else
    echo "WARN: body may be degraded - review content"
    printf '%s' "$out" | head -c 300
    echo ""
  fi
fi

if [[ -n "$FRONTEND_BASE" && "$SKIP_FRONTEND" -eq 0 ]]; then
  step "Frontend: HEAD ${FRONTEND_BASE}"
  if curl -sfS --max-time 20 -o /dev/null -I "$FRONTEND_BASE"; then
    echo "OK: frontend HEAD"
  else
    echo "WARN: HEAD failed, trying GET"
    if curl -sfS --max-time 20 -o /dev/null "$FRONTEND_BASE"; then
      echo "OK: frontend GET"
    else
      echo "FAIL: frontend unreachable"
      failed=1
    fi
  fi
fi

if [[ "$SKIP_DOCKER" -eq 0 ]]; then
  step "Docker Compose (read-only status)"
  docker compose ps 2>&1 || echo "WARN: docker compose ps unavailable"
fi

if [[ "$WITH_OCR" -eq 1 ]]; then
  step "OCR: php artisan ocr:verify --fail (app container)"
  if docker compose exec -T app sh -lc "cd /var/www && php artisan ocr:verify --fail"; then
    echo "OK: ocr:verify"
  else
    echo "FAIL: ocr:verify"
    failed=1
  fi
fi

echo ""
if [[ "$failed" -ne 0 ]]; then
  echo "PREFLIGHT RESULT: FAIL - review output above before Pilot stage."
  exit 1
fi
echo "PREFLIGHT RESULT: PASS - proceed to manual UAT (docs/Pilot_Phase_Safe_Next_Steps.md)."
exit 0
