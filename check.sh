#!/usr/bin/env bash
# =============================================================================
# WorkshopOS — Quick Monitoring Gate (≈0–5 min بعد restart / deploy)
# -----------------------------------------------------------------------------
# الاستخدام (من جذر المشروع، حيث يوجد docker-compose.yml):
#   chmod +x check.sh && ./check.sh
#
# متغيرات اختيارية:
#   CHECK_BASE_URL       افتراضي: http://localhost
#   LOG_ERROR_THRESHOLD  افتراضي: 15 (عدد أسطر تطابق error/critical في لوج التطبيق)
#   REDIS_PASSWORD       افتراضي: redis_password (مطابق لـ docker-compose)
#   FAIL_ON_FAILED_JOBS  إذا كانت 1 يفشل البوابة عند وجود jobs فاشلة في الجدول
#
# رموز الخروج (لـ GitHub Actions / GitLab وغيرها — بدون التباس):
#   0  PASS — كل فحوصات البوابة الحاسمة ناجحة
#   1  FAIL — فشل فحص واحد أو أكثر من الفحوص الحاسمة
# =============================================================================

set +e
cd "$(dirname "$0")"

readonly EXIT_OK=0
readonly EXIT_FAIL=1
FAIL=0
CHECK_BASE_URL="${CHECK_BASE_URL:-http://localhost}"
LOG_ERROR_THRESHOLD="${LOG_ERROR_THRESHOLD:-15}"
REDIS_PASS="${REDIS_PASSWORD:-redis_password}"
FAIL_ON_FAILED_JOBS="${FAIL_ON_FAILED_JOBS:-0}"

echo "🔍 Quick Monitoring Gate — $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "----------------------------------------------"

# --- 1) Logs (app container) -------------------------------------------------
echo "📄 Checking app container logs (last ~5m, ERROR/CRITICAL)..."
LOG_ERRORS=$(docker compose logs app --since 5m 2>/dev/null | grep -Eci 'error|critical' || true)
LOG_ERRORS=${LOG_ERRORS:-0}
if [ "$LOG_ERRORS" -gt "$LOG_ERROR_THRESHOLD" ]; then
  echo "❌ Log noise high: ~$LOG_ERRORS ERROR/CRITICAL lines (threshold $LOG_ERROR_THRESHOLD)"
  FAIL=1
else
  echo "✅ Logs acceptable (~$LOG_ERRORS matches, threshold $LOG_ERROR_THRESHOLD)"
fi

# --- 2) Failed jobs (database table) ----------------------------------------
echo "📬 Checking failed_jobs count..."
FAILED_OUT=$(docker compose exec -T app php artisan queue:failed 2>/dev/null || true)
FAILED_COUNT=$(echo "$FAILED_OUT" | grep -E '^\|[[:space:]]+[0-9]+' | wc -l | tr -d ' \r')
FAILED_COUNT=${FAILED_COUNT:-0}
if [ -z "$FAILED_OUT" ]; then
  echo "⚠️  Could not run queue:failed (container / artisan)"
elif [ "$FAILED_COUNT" -gt 0 ]; then
  echo "⚠️  Failed jobs listed: $FAILED_COUNT (راجع: docker compose exec app php artisan queue:failed)"
  if [ "$FAIL_ON_FAILED_JOBS" = "1" ]; then
    FAIL=1
  fi
else
  echo "✅ No failed jobs in queue:failed output"
fi

# --- 3) Redis queue depth (Laravel: مفتاح قائمة الانتظار قد يكون مسبوقاً بـ REDIS_PREFIX) --
echo "📊 Checking Redis queue lengths..."
# يجمع LLEN لكل المفاتيح المطابقة *queues:<name>؛ إن وُجد أكثر من مفتاح يُطبع تحذير على stderr.
redis_queue_depth_total() {
  local qname="$1"
  local keys sum n len k
  keys=$(
    docker compose exec -T redis redis-cli -a "$REDIS_PASS" --no-auth-warning --scan --pattern "*queues:${qname}" 2>/dev/null |
      tr -d '\r'
  )
  if [ -z "$keys" ]; then
    echo "0"
    return
  fi
  sum=0
  n=0
  while IFS= read -r k || [ -n "$k" ]; do
    [ -z "$k" ] && continue
    n=$((n + 1))
    len=$(docker compose exec -T redis redis-cli -a "$REDIS_PASS" --no-auth-warning LLEN "$k" 2>/dev/null | tr -d '\r')
    if [[ "$len" =~ ^[0-9]+$ ]]; then
      sum=$((sum + len))
    fi
  done <<< "$keys"

  if [ "$n" -gt 1 ]; then
    echo "⚠️  queue '$qname': $n Redis keys matched *queues:${qname} — total LLEN = $sum (keys aggregated)" >&2
    # عرض المفاتيح يساعد التشخيص دون إطالة السطر كثيراً
    echo "$keys" | sed "s/^/      ↳ /" >&2
  fi
  echo "$sum"
}

QH=$(redis_queue_depth_total "high_priority")
QD=$(redis_queue_depth_total "default")
QL=$(redis_queue_depth_total "low_priority")
QH=${QH:-0}
QD=${QD:-0}
QL=${QL:-0}

echo "   high_priority total depth = $QH  (fail if >50)"
echo "   default total depth       = $QD  (fail if >100)"
echo "   low_priority total depth  = $QL  (fail if >100)"

if ! [[ "$QH" =~ ^[0-9]+$ && "$QD" =~ ^[0-9]+$ && "$QL" =~ ^[0-9]+$ ]]; then
  echo "⚠️  Could not parse Redis LLEN (check service 'redis' / REDIS_PASSWORD)"
else
  if [ "$QH" -gt 50 ] || [ "$QD" -gt 100 ] || [ "$QL" -gt 100 ]; then
    echo "❌ Queue backlog too high"
    FAIL=1
  else
    echo "✅ Queue depths within thresholds"
  fi
fi

# --- 4) Container restart storms --------------------------------------------
echo "⚙️  Checking compose containers for Restarting state..."
RESTARTING=$(docker compose ps 2>/dev/null | grep -ci 'restarting' || true)
if [ "${RESTARTING:-0}" -gt 0 ]; then
  echo "❌ Some containers in Restarting state: $RESTARTING"
  FAIL=1
else
  echo "✅ No Restarting containers (snapshot)"
fi

# --- 5) Health endpoint ------------------------------------------------------
echo "🌐 Health: ${CHECK_BASE_URL}/api/v1/health"
STATUS=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 15 "${CHECK_BASE_URL}/api/v1/health" 2>/dev/null || echo "000")
if [ "$STATUS" != "200" ]; then
  echo "❌ Health HTTP status: $STATUS"
  FAIL=1
else
  echo "✅ Health OK (200)"
fi

# --- 6) Latency (health) — بدون bc ------------------------------------------
echo "⏱️  Health response time..."
TIME=$(curl -sS -o /dev/null -w "%{time_total}" --max-time 15 "${CHECK_BASE_URL}/api/v1/health" 2>/dev/null || echo "999")
echo "   time_total: ${TIME}s"
if awk -v t="$TIME" 'BEGIN { exit !(t > 2.0) }'; then
  echo "❌ Slow health response: ${TIME}s (limit 2s)"
  FAIL=1
else
  echo "✅ Latency OK (≤ 2s)"
fi

# --- 7) Deployment proof (اختياري) -------------------------------------------
echo "📌 Deployment metadata: ${CHECK_BASE_URL}/api/v1/system/version"
VSTATUS=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 10 "${CHECK_BASE_URL}/api/v1/system/version" 2>/dev/null || echo "000")
if [ "$VSTATUS" != "200" ]; then
  echo "⚠️  system/version returned $VSTATUS (غير مانع للبوابة إن لم تُفعّل بعد)"
else
  echo "✅ system/version OK (200)"
fi

echo "----------------------------------------------"
if [ "$FAIL" -eq 0 ]; then
  echo "🎉 STATUS: PASS — Quick gate clean (exit ${EXIT_OK})"
  exit "$EXIT_OK"
else
  echo "🚨 STATUS: FAIL — راجع اللوائح أعلاه + monitoring checklist (exit ${EXIT_FAIL})"
  exit "$EXIT_FAIL"
fi
