# تقرير Progressive Real-World Load & Performance Verification Gate

## 1) Executive Summary
- **البيئة:** Local Docker مع `app/nginx/postgres/redis/queue/scheduler`، بدون اختبار على Production.
- **ما تم اختباره فعليًا:** سيناريو مختلط read-heavy + إنشاء Work Order + batch negative path (4xx متوقعة).
- **مستويات الحمل المنفذة:** `0..6` (Smoke/Baseline/Low/Normal/Peak/Stress/Soak).
- **الحكم النهائي:** **PASS with optimization backlog**.

الاختبار الحالي يقدّم قيمة تشغيلية فعلية ويغطي جزءًا مهمًا من التشغيل الواقعي تحت حمل تدريجي، لكنه لا يغطي دورة الخدمة الكاملة من البداية إلى النهاية (End-to-End service cycle).  
**Operationally meaningful, but not full end-to-end service-cycle complete.**

## 2) Preconditions

**حالة الخدمات**
- `docker compose ps`: الخدمات الأساسية كانت Up وبدون Restarting/Unhealthy أثناء الـ preflight.

**سلامة البيانات**
- قبل الحمل: `php artisan integrity:verify` = PASS (كل المؤشرات = 0).
- بعد الحمل: `php artisan integrity:verify` = PASS (كل المؤشرات = 0).

**Health**
- `GET /api/v1/health` = 200
- `GET /api/v1/system/version` = 200

**Queue / Failed Jobs**
- قبل التشغيل:
  - `failed_jobs` (DB): **589**
- بعد التشغيل:
  - `failed_jobs` (DB): **614**
  - **delta = +25**
- فحص `check.sh` بعد الحمل:
  - `high_priority=6`, `default=3`, `low_priority=0` (ضمن الحدود)

## 3) Scenarios & Workload Model

**المنفذ فعليًا في سكربت k6 الحالي (`scripts/performance/mixed.js`)**
- Reads:
  - `health`
  - `system/version`
  - `work-orders list`
  - `invoices list`
  - `dashboard/summary`
  - `auth/me`
- Writes:
  - `work-orders create` (بنسبة صغيرة)
- Negative isolated:
  - `POST /work-orders/batches` بدون token صحيح => 422/400 متوقعة

**النسب (تقريبية حسب السكربت)**
- Reads ~90%+
- Write Work Order ~1.5%
- Batch negative ~1.5%
- النمط مناسب كبداية تشغيلية، لكنه **لا يغطي بالكامل** السيناريوهات المالية/Cancellation/POS المطلوبة في الخطة الأصلية.

## 4) Test Levels

- **L0 Smoke:** 5 VUs / 2m
- **L1 Baseline:** 10 VUs / 5m
- **L2 Low:** ramp إلى 25 VUs / 8m
- **L3 Normal:** ramp إلى 50 VUs / 10m
- **L4 Peak:** ramp إلى 100 VUs / 12m
- **L5 Stress:** ramp إلى 150 VUs / 10m
- **L6 Soak:** 30 VUs / 10m
  - ملاحظة: soak هنا **10 دقائق فقط** (أقل من المطلوب 30–60m).

## 5) Commands, Scripts, and k6 Files

- سكربتات:
  - `scripts/performance/common.js`
  - `scripts/performance/mixed.js`
- نتائج:
  - `scripts/performance/out/summary-0.json` ... `summary-6.json`
- أوامر أساسية:
  - `docker compose ps`
  - `docker compose exec -T app php artisan integrity:verify`
  - `docker run ... grafana/k6 run /scripts/mixed.js --summary-export ...`
  - `docker compose exec -T postgres psql ... "SELECT COUNT(*) FROM failed_jobs;"`

## 6) Results by Level

> **مهم:** ملفات summary الحالية تحفظ `p50(=med)` و`p95`، لكنها لا تحتوي `p99` على مستوى الإجمالي HTTP (N/A في هذه الجولة).

| Level | VUs / Duration | Throughput (req/s) | p50 ms | p95 ms | p99 | Error rate (`http_req_failed`) | 5xx | failed_jobs delta | ملاحظات |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---|
| L0 | 5 / 2m | 7.99 | 140.01 | 231.28 | N/A | 0.00% | غير مرصود | +0 (داخل المرحلة) | Smoke ناجح |
| L1 | 10 / 5m | 15.62 | 157.99 | 232.35 | N/A | 0.00% | غير مرصود | +0 | Baseline مستقر |
| L2 | 25 / 8m | 27.39 | 168.13 | 255.86 | N/A | 0.00% | غير مرصود | +0 | Low مستقر |
| L3 | 50 / 10m | 44.97 | 322.79 | 481.99 | N/A | 0.00% | غير مرصود | +0 | بداية ارتفاع latency |
| L4 | 100 / 12m | 67.22 | 565.09 | 806.94 | N/A | 0.02% (11 req) | غير مؤكد من k6 summary | +11 تقريبًا | فشل جزئي في `wo_create` |
| L5 | 150 / 10m | 78.62 | 907.55 | 1270.42 | N/A | 0.04% (20 req) | غير مؤكد من k6 summary | +14 تقريبًا | تدهور أوضح تحت stress |
| L6 | 30 / 10m | 42.60 | 218.53 | 330.39 | N/A | 0.00% | غير مرصود | +0 (داخل المستوى) | رجوع للاستقرار |

## 7) Slowest Endpoints

> ملاحظة: السكربت يوسم عددًا محدودًا من endpoints؛ لذلك القائمة الفعلية أقل تنوعًا من نظام الإنتاج الكامل.

1. `work_orders_list` — أعلى p95 عبر المراحل (حتى ~830ms في L4)
2. `invoices_list` — p95 حتى ~221ms في L2 (وأعلى في الأحمال الأعلى ضمن الإجمالي)
3. `auth_login` — ~382–480ms (setup/login)
4. `dashboard_summary` — p95 حتى ~205ms
5. `health` — p95 حتى ~189ms
6. `work_order_create` — latency مرتفعة عند peak/stress مع أخطاء جزئية
7. `auth_me` — ضمن المتوسط (أسرع من list غالبًا)
8. `system_version` — سريع نسبيًا
9. `batch_negative_expect_422` — متوقع 4xx (ليس فشل تشغيلي)
10. `setup_vehicles/setup_customers` — setup-only overhead

## 8) System Impact Analysis

- **DB:** stable، بدون مؤشر corruption، لكن latency يرتفع بوضوح في L4/L5.
- **Redis/Queue:** لا انفجار queue؛ depths بعد الاختبار ضمن الحدود (`6/3/0`).
- **App:** استقرار جيد حتى Normal؛ عند Peak/Stress ظهرت إخفاقات محدودة في `wo_create`.
- **Containers:** لا restart/unhealthy ملاحظ أثناء الفحص.
- **Frontend:** لم يتم قياس initial-load profiling مستقل في هذه الجولة.

## 9) Data Integrity After Load

نتيجة `integrity:verify` بعد الحمل:
- `invoices_without_journal = 0`
- `negative_on_hand_stock = 0`
- `wallet_balance_drift = 0`
- `duplicate_invoice_sources = 0`
- `duplicate_invoice_hashes = 0`

**الاستنتاج:** لا توجد integrity issues مالية/دفترية ظاهرة في هذه الجولة.

## 10) Findings by Severity

**Critical**
- لا يوجد فساد بيانات/مالي ظاهر.

**High**
- فشل جزئي في `work_order_create` تحت L4/L5 (11 ثم 20 طلب فاشل).

**Medium**
- p99 غير متاح في summary الحالي (قصور قياس، ليس بالضرورة قصور أداء).
- soak أقصر من المطلوب (10m بدل 30–60m).

**Low**
- `failed_jobs` الكلي ارتفع +25 على مستوى البيئة (يلزم تحليل أصلها التفصيلي وربطها بزمن الاختبار).

## 11) Optimizations Applied

لم يتم تنفيذ tuning أداء عشوائي على التطبيق قبل القياس.  
تم تنفيذ إصلاحات **تمكينية للاختبار فقط**:
- إصلاح mapping لـ `vehicleId` في k6.
- تعديل batch negative request كي لا يُحسب 4xx المتوقع كـ `http_req_failed`.
- ضمان vehicle مرتبط بالعميل في setup.
- تهيئة demo seed لتفعيل حالة مالية تسمح بتنفيذ مسارات التشغيل في البيئة المعزولة.

## 12) Final Decision

## **PASS with optimization backlog**

**التبرير المباشر:**
- النظام اجتاز L0–L3 بشكل نظيف (`http_req_failed = 0%`) مع integrity سليم.
- عند L4/L5 ظهر تدهور طبيعي في latency مع نسبة أخطاء منخفضة جدًا (`0.02%` ثم `0.04%`) لكنها موجودة في مسار write حساس (`work_order_create`).
- لا توجد أدلة فساد بيانات/مالي.
- هناك backlog مطلوب قبل اعتماد scale أعلى بثقة:
  1) توسيع السيناريوهات لتشمل financial completion/cancellation/POS كاملة.  
  2) إعادة قياس مع `p99` مفعل صراحة.  
  3) soak حقيقي 30–60 دقيقة.  
  4) تحليل `failed_jobs` (+25) وربط السبب الزمني بالمراحل L4/L5.

نجاح هذه الجولة يعني اجتياز **بوابة تشغيلية جزئية ذات قيمة عملية**، ولا يعني اكتمال تحقق دورة الخدمة الكاملة End-to-End.  
وبالتالي يبقى الحكم الأساسي: **PASS with optimization backlog** مع اشتراطات واضحة قبل اعتماد full end-to-end confidence.

## 13) Operational Coverage & Service-Cycle Completeness

الاختبار الحالي يغطي جزءًا تشغيليًا مهمًا وواقعيًا من الخدمة، وشمل:
- health checks
- التحقق من المستخدم
- القراءات التشغيلية
- إنشاء Work Order
- التحقق من سلامة البيانات قبل وبعد الحمل

لكنه لا يمثل اكتمال دورة الخدمة الكاملة من البداية إلى النهاية، ولم يشمل:
1. progression/status flow لأمر العمل
2. service completion
3. final invoicing
4. collection/payment
5. full accounting posting impact
6. cancellation/reversal/exception flows
7. full POS flow
8. true p99-enabled verification
9. real soak duration 30–60 minutes
10. detailed failed_jobs root-cause correlation

**Formal Judgment**
- الاختبار الحالي لا يثبت اكتمال دورة الخدمة الكاملة End-to-End.
- لكنه يثبت Partial Operational Verification ذات قيمة عملية.
- لذلك يصنّف على أنه: **Operational Verification for Partial Service Flow**  
  وليس: **Full End-to-End Service Cycle Validation**.

## 14) Required Next Validation Round

الجولة القادمة يجب أن تغطي بشكل صريح:
- full work-order lifecycle
- completion
- invoicing
- payment/collection
- accounting effect
- cancellation/reversal
- full POS
- p99
- soak 30–60m
- failed_jobs analysis
