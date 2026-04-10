# اختبارات التحميل (k6) — WorkshopOS

منصة **k6** بمعايير قبول ثابتة، وملفات شخصية (**profiles**) تطابق خط أساس متفقاً عليه: دخان، حمل اعتيادي، ذروة، إجهاد، قفزة، ونقع.

## المتطلَبات

1. `docker compose up` (nginx على المنفذ 80).
2. `php artisan migrate --seed` (أو `make fresh`) — يجب وجود مستخدمي `env.example`.
3. Docker لتشغيل k6 (موصى به) أو تثبيت k6 محلياً.

## التشغيل

```bash
# افتراضي: K6_PROFILE=smoke
make load-test

# ملف شخصي محدد
make load-test K6_PROFILE=normal
make load-test K6_PROFILE=peak
make load-test K6_PROFILE=stress
make load-test K6_PROFILE=spike
make load-test K6_PROFILE=soak
```

تشغيل مرحلي مقترح:

```bash
# بوابة سريعة قبل أي دمج كبير
make load-test-matrix

# بوابة إصدار موسعة (تشمل stress + soak)
make load-test-release-gate
```

من المضيف بدون Make (بعد `cd load-testing/k6`):

```bash
K6_BASE_URL=http://localhost/api K6_PROFILE=normal k6 run suite.js
```

من حاوية k6 (كما في Makefile): `K6_BASE_URL=http://host.docker.internal/api`

### تشخيص قبل الضغط

```bash
make load-test-preflight
```

## خط الأساس المعتمد (K6_PROFILE)

| الملف الشخصي | مستخدمون متزامنون (الهدف) | المدة الزمنية | الوصف |
|--------------|---------------------------|---------------|--------|
| **smoke** | **8** VU (ضمن **5–10**) | **4 دقائق** (ضمن **3–5**) | خلط: صحة، قراءة، POS أحياناً، عزل؛ + **3** محاولات idempotency |
| **normal** | **30** VU قراءة + POS بمعدل طلبات (ضمن **20–40**) | **~15 دقيقة** (ضمن **10–20**) | قراءة تشغيلية + POS خفيف + فحص عزل دوري + idempotency |
| **peak** | **75** VU قراءة + POS أعلى (ضمن **50–100**) | **~12 دقيقة** (ضمن **10–15**) | ذروة تشغيلية |
| **stress** | **15 → 130** VU على **6 مراحل × 3 دقائق** | **~18 دقيقة** | تصعيد متدرج **بدون تداخل مراحل**؛ التقرير يحلل **أول تدهور** و**أول انهيار** تقريبي |
| **spike** | قفزة **10 → 95** VU قراءة + ذروة POS قصيرة | **~9 دقائق** | قفزة مفاجئة ثم هبوط |
| **soak** | **22** VU قراءة (ضمن **15–30**) | **90 دقيقة** (ضمن **45–120**) | استقرار زمني + POS نادر + صحة دورية |

التفاصيل البرمجية: `k6/config/profiles.js`  
عتبات القبول: `k6/config/acceptance.js`

## معايير القبول (ملخص)

تُعرَّف كـ **thresholds** في k6 وتظهر في `reports/latest.md`:

- **error rate:** `http_req_failed` — حدود صارمة للدخان/العرضي؛ أكثر تساهلاً للذروة/القفزة؛ واسعة جداً للإجهاد (لإكمال المنحنى).
- **5xx:** مقياس مخصص `server_errors_5xx` (لا يشمل 403 العزل).
- **timeouts / شبكة:** `client_timeout_or_network` (من بينها `status === 0`).
- **زمن الاستجابة:** `http_req_duration` — **p50 / p95 / p99** حسب الملف الشخصي.
- **عزل الشركات:** `tenant_isolation_403` على `GET /v1/companies/{معرف_شركة_أخرى}`.
- **Idempotency:** سيناريو يعيد **نفس المفتاح** مع **حمولة مختلفة** — يُتوقع **409** بعد نجاح أول بيع (`idempotency_409_payload_mismatch`).
- **POS:** مفتاح idempotency **فريد** لكل بيع عادي — تقليل احتمال تكرار غير مقصود على مستوى HTTP.

**سلامة أرصدة DB والمخزون** تحت ضغط مطلق: تُكمّل بـ `php artisan test` أو تدقيق لاحق — انظر `reports/RUNBOOK_ASSESSMENT.md`.

## التقارير

- `reports/latest.md` — **القيم المعتمدة**، **نجاح/فشل العتبات**، **p50/p95/p99**، وفي **stress** جدول مراحل + أول تدهور/انهيار + **تقدير الحد الآمن**.
- `reports/latest-summary.json` — خام k6.
- يتضمن `latest.md` أيضاً:
  - **SLO حسب الرحلة** (قراءات، POS، RAW، عزل tenant)
  - **مناطق اختناق مرشحة** تلقائية عند تجاوز SLO
  - تصنيف أخطاء مفصل (4xx / 5xx / network-timeout)

## الهيكل

```
load-testing/
  k6/
    suite.js              # المدخل
    config/
      profiles.js         # خطوط الأساس (VU، مدة، سيناريوهات)
      acceptance.js       # thresholds لكل عائلة حمل
    lib/
      metrics.js
      api-scenarios.js
      auth.js
      discover.js
      report.js           # handleSummary + تحليل stress
  reports/
```

## البيئة (تذكير)

- **Sentry:** غير مضبوط افتراضياً → تحذيرات Docker فقط.
- **`version` في docker-compose.yml:** مهمل.
- **اختبارات Laravel:** عبر Docker (`make verify`).

## استكشاف الأخطاء

| العرض | الإجراء |
|--------|---------|
| فشل `setup()` | `make load-test-preflight` ، ثم بذور كاملة. |
| فشل عتبة idempotency | تأكد من نجاح أول بيع (`201`) ضمن بيئة المخزون/المنتج. |
| stress لا يظهر جدول مراحل | افتح `latest-summary.json` وابحث عن مفاتيح `http_req_duration{scenario:stress_sNN}`. |

تفاصيل قرار التشغيل: **`reports/RUNBOOK_ASSESSMENT.md`**.

## منهجية V2 (أدق وأكثر واقعية)

1. ابدأ بـ `smoke` ثم `normal` ثم `peak`.
2. ثبّت حدود تشغيلية آمنة منفصلة لكل فئة: قراءات / POS / مسارات حساسة.
3. شغّل `stress` لاستخراج أول تدهور وأول انهيار بدقة.
4. شغّل `soak` (90–180 دقيقة) للتحقق من التدهور التدريجي عبر الزمن.
5. اعتمد القرار التنفيذي فقط إذا اجتازت الرحلات الأساسية SLO المعرفة في التقرير.

## الأمان

لا تستخدم كلمات إنتاج؛ حسابات demo فقط.
