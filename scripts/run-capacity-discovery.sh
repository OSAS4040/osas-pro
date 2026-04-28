#!/usr/bin/env bash
# POSIX ladder for POS capacity (same intent as run-capacity-discovery.ps1).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

K6_BASE_URL="${K6_BASE_URL:-http://host.docker.internal/api}"
RATES="${K6_CAPACITY_LADDER_RATES:-3,5,7}"
STEADY_MIN="${K6_CAPACITY_POS_STEADY_MIN:-5}"
SKIP_PREFLIGHT="${SKIP_PREFLIGHT:-0}"
ALLOW_NON_LOCAL="${ALLOW_NON_LOCALHOST:-0}"

K6_EMAIL_A="${K6_EMAIL_A:-simulation.owner@demo.local}"
K6_PASSWORD_A="${K6_PASSWORD_A:-SimulationDemo123!}"
K6_EMAIL_B="${K6_EMAIL_B:-owner@demo.sa}"
K6_PASSWORD_B="${K6_PASSWORD_B:-password}"
K6_POS_DISTRIBUTION="${K6_POS_DISTRIBUTION:-single}"
K6_POS_INCLUDE_RAW="${K6_POS_INCLUDE_RAW:-true}"

url_ok() {
  case "$ALLOW_NON_LOCAL" in
    1|true|TRUE|yes|YES) return 0 ;;
  esac
  echo "$K6_BASE_URL" | grep -Eq '(localhost|127\.0\.0\.1|host\.docker\.internal)(/|:|$)' \
    || { echo "Refusing K6_BASE_URL='$K6_BASE_URL' (set ALLOW_NON_LOCALHOST=1 to override)."; return 1; }
}

url_ok || exit 2

if [[ "$SKIP_PREFLIGHT" != "1" ]]; then
  echo "k6 preflight..."
  docker run --rm \
    -v "${ROOT}/load-testing:/work" \
    -w /work/k6 \
    --add-host=host.docker.internal:host-gateway \
    -e "K6_BASE_URL=${K6_BASE_URL}" \
    -e "K6_EMAIL_A=${K6_EMAIL_A}" \
    -e "K6_PASSWORD_A=${K6_PASSWORD_A}" \
    -e "K6_EMAIL_B=${K6_EMAIL_B}" \
    -e "K6_PASSWORD_B=${K6_PASSWORD_B}" \
    grafana/k6:latest run preflight.js
fi

TS="$(date -u +"%Y%m%d-%H%M%S")"
OUT="${ROOT}/load-testing/reports/capacity-discovery-${TS}"
mkdir -p "$OUT"

printf '{"created_utc":"%s","k6_base_url":"%s","rates":"%s","steady_min":%s}\n' \
  "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" "$K6_BASE_URL" "$RATES" "$STEADY_MIN" >"${OUT}/run-meta.json"

CSV="${OUT}/capacity-ladder.csv"
echo "pos_rate_per_s,k6_exit_code,pos_sale_2xx_rate,scen_pos_post_p99_ms,http_req_duration_p99_ms,server_errors_5xx_rate,dropped_iterations" >"$CSV"

IFS=',' read -r -a RATE_ARR <<<"$RATES"
for rate in "${RATE_ARR[@]}"; do
  rate="$(echo "$rate" | tr -d '[:space:]')"
  [[ -n "$rate" ]] || continue
  echo ""
  echo "=== capacity_pos @ ${rate}/s ==="

  set +e
  docker run --rm \
    -v "${ROOT}/load-testing:/work" \
    -w /work/k6 \
    --add-host=host.docker.internal:host-gateway \
    -e "K6_BASE_URL=${K6_BASE_URL}" \
    -e "K6_PROFILE=capacity_pos" \
    -e "K6_CAPACITY_POS_RATE=${rate}" \
    -e "K6_CAPACITY_POS_STEADY_MIN=${STEADY_MIN}" \
    -e "K6_EMAIL_A=${K6_EMAIL_A}" \
    -e "K6_PASSWORD_A=${K6_PASSWORD_A}" \
    -e "K6_EMAIL_B=${K6_EMAIL_B}" \
    -e "K6_PASSWORD_B=${K6_PASSWORD_B}" \
    -e "K6_POS_DISTRIBUTION=${K6_POS_DISTRIBUTION}" \
    -e "K6_POS_INCLUDE_RAW=${K6_POS_INCLUDE_RAW}" \
    grafana/k6:latest run suite.js
  code=$?
  set -euo pipefail

  tag="rate-${rate}ps"
  cp -f "${ROOT}/load-testing/reports/latest.md" "${OUT}/${tag}.md" 2>/dev/null || true
  cp -f "${ROOT}/load-testing/reports/latest-summary.json" "${OUT}/${tag}-summary.json" 2>/dev/null || true

  json="${ROOT}/load-testing/reports/latest-summary.json"
  if command -v jq >/dev/null 2>&1 && [[ -f "$json" ]]; then
    pos2xx="$(jq -r '.metrics.pos_sale_2xx.values.rate // empty' "$json")"
    p99pos="$(jq -r '.metrics.scen_pos_post_http_ms.values["p(99)"] // empty' "$json")"
    p99http="$(jq -r '.metrics.http_req_duration.values["p(99)"] // empty' "$json")"
    s5="$(jq -r '.metrics.server_errors_5xx.values.rate // empty' "$json")"
    dropped="$(jq -r '.metrics.dropped_iterations.values.count // empty' "$json")"
    echo "${rate},${code},${pos2xx},${p99pos},${p99http},${s5},${dropped}" >>"$CSV"
  else
    echo "${rate},${code},,,,," >>"$CSV"
  fi
done

cat >"${OUT}/README.md" <<EOF
# POS capacity discovery

- Directory: \`$OUT\`
- Rates: $RATES (req/s target)
- Steady: ${STEADY_MIN}m per rate

See \`capacity-ladder.csv\` and per-step \`rate-*-summary.json\`.
EOF

echo ""
echo "Done. Artifacts: $OUT"
