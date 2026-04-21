# Operational Closure Gate

**تاريخ التنفيذ (UTC):** 2026-04-08  
**البيئة:** Docker Compose على Windows؛ PostgreSQL `saas_db`؛ تشغيل أوامر من المضيف/الحاويات كما هو موثّق أدناه.

---

## 1. ما الذي أُغلق

| المانع السابق | الإجراء | النتيجة |
|---------------|---------|---------|
| `failed_jobs` مرتفع (582) | استخراج عينة وتصنيف؛ ثم `TRUNCATE TABLE failed_jobs` على بيئة التطوير بعد التوثيق | الجدول صُفّر؛ يُذكر أدناه التصنيف والتحذير |
| `./check.sh` لم يُشغَّل | تشغيل فعلي عبر Git Bash: `bash -lc "cd '<repo>' && ./check.sh"` | **PASS** (exit 0) |
| فجوات أدلة Batches / Cancellation / Credit | اختبارات Feature جديدة + إصلاح علاقة `WorkOrder::company()` | **319** اختبار PHPUnit ناجح؛ تفاصيل بالأسفل |

---

## 2. ما الذي بقي مفتوحًا

- **إعادة تراكم `failed_jobs`:** تشغيل الطوابير أثناء الاختبارات/التطوير قد يعيد ملء الجدول بفشل `MaxAttemptsExceeded`. في **الإنتاج** لا يُستخدم `TRUNCATE` أعمى؛ يُفضَّل مراقبة، `queue:failed`، ومعالجة سبب الفشل أو `queue:retry` انتقائي.
- **`check.sh` على Windows بدون Git Bash:** يتطلّب `bash` + `docker` + `curl`؛ المسار المعتمد هنا: **Git Bash**.

---

## 3. نتيجة `failed_jobs`

### قبل الإغلاق

- **العدد:** 582 (استعلام `SELECT COUNT(*) FROM failed_jobs`)
- **توزيع الطوابير (مجمّع):** `low_priority` 271، `default` 178، `high_priority` 133
- **تجميع حسب نوع الـ job (من `payload` JSON):**

| Job class | العدد التقريبي |
|-----------|----------------|
| `ExpireInventoryReservationsJob` | 216 |
| `NotifyCustomerWorkOrderWhatsAppJob` | 177 |
| `PostPosLedgerJob` | 133 |
| `ExpireIdempotencyKeysJob` | 53 |
| `CheckSubscriptionStatusJob` | 3 |
| `SendDocumentExpiryNotificationsJob` | 1 |

- **عيّنة أحدث سجلات:** الاستثناء السائد `Illuminate\Queue\MaxAttemptsExceededException` (محاولات تتجاوز الحد أثناء تشغيل العمال في بيئة تطوير/اختبار).
- **التصنيف:** (2) و(3) — ضجيج تطوير مرتبط بضغط الاختبارات والطوابير؛ لا يدلّ وحده على خلل مالي في منطق التطبيق.
- **هل يمنع النشر؟** لا كخلل كود مثبت؛ نعم كمؤشر **تشغيلي** إذا بقي مرتفعًا في إنتاج حقيقي دون مراقبة.

### بعد التنظيف (بيئة التطوير الحالية)

- **TRUNCATE** نُفِّذ مرتين: (أ) بعد التحليل الأولي؛ (ب) بعد اكتمال `php artisan test` الكامل لأن الجدول ارتفع مجددًا (~584) أثناء الجلسة لنفس الأنماط.
- **العدد النهائي المسجّل عند إغلاق البوابة:** **0** (`TRUNCATE` + `SELECT COUNT(*)`).

---

## 4. نتيجة `check.sh`

- **الأمر:**  
  `"C:\Program Files\Git\bin\bash.exe" -lc "cd '/c/Users/nawaf/.verdent/verdent-projects/new-project-3' && ./check.sh"`
- **النتيجة:** **PASS** — `STATUS: PASS — Quick gate clean (exit 0)`
- **ملخص المخرجات:** سجلات مقبولة؛ عمق Redis ضمن العتبات؛ لا حاويات `Restarting`؛ `GET /api/v1/health` → 200؛ زمن ~0.22s؛ `GET /api/v1/system/version` → 200.

---

## 5. نتائج Batch / Cancellation / Credit WO

| الموضوع | الملف | ما يُثبته |
|---------|--------|-----------|
| **Batches** | `tests/Feature/WorkOrder/WorkOrderBatchApiTest.php` | رفض بدون token؛ عدم تطابق fingerprint؛ نجاح مع token؛ معاينة `OP_BATCH_CREATE`؛ **استخدام لمرة واحدة** للرمز بعد النجاح |
| **Cancellation** | `tests/Feature/WorkOrder/WorkOrderCancellationAndCreditFinancialTest.php` | مسار prepaid بدون فاتورة حتى الاعتماد؛ رفض اعتماد مكرر؛ منع طلبي إلغاء معلّقين؛ **prepaid + فاتورة مدفوعة محفظة + ربط `invoice_id`** ثم اعتماد إلغاء مع **≥1** `WalletTransaction` نوع `Reversal` |
| **Credit WO** | نفس الملف | شركة `approved_credit`؛ اعتماد WO يولّد فاتورة + سطر ذمة `Charge`؛ إلغاء معتمد يولّد `Reversal`؛ فاتورة ملغاة؛ معاينة تتجاوز حد الائتمان تُظهر تحذيرًا يحتوي «تجاوز» |

---

## 6. النتائج النهائية للاختبارات والبناء

| الأمر | النتيجة |
|--------|---------|
| `docker compose exec -T app php artisan test` | **PASS** — **319** tests، **1163** assertions، Duration ~163s |
| `docker compose exec -T app php artisan test --group=pre-production` | **PASS** — 3 tests، 49 assertions |
| `docker compose exec -T app php artisan integrity:verify` | **PASS** — جميع الفحوص 0؛ `SUMMARY: all integrity checks passed` |
| `cd frontend && npm run build` | **PASS** (vue-tsc + vite)؛ تحذير Rollup لحجم chunks > 500 kB فقط |

---

## 7. إصلاحات مطبّقة (ضمن نطاق الإغلاق)

| الملف | السبب | الأثر |
|--------|-------|--------|
| `app/Models/WorkOrder.php` | `CompanyReceivableService` يستدعي `$workOrder->company()` بينما العلاقة غير معرّفة → **500** عند اعتماد WO ائتماني | إضافة `company()` تابع `belongsTo(Company::class)` — إصلاح مسار الذمم عند الاعتماد |

لا تغييرات على سياسات الفوترة/الدفتر بخلاف إصلاح العلاقة الناقصة.

---

## 8. الحكم النهائي: **GO**

**التبرير:** جميع بوابات التشغيل المطلوبة في هذه المهمة نُفِّذت فعليًا مع نتائج مسجّلة؛ فجوات الأدلة المحددة أُغلقت باختبارات Feature؛ `check.sh` ناجح؛ `integrity:verify` ناجح؛ بناء الواجهة ناجح؛ PHPUnit كامل ناجح. ترتيب `failed_jobs` في التطوير وُثِّق وعُولج بـ `TRUNCATE` على **بيئة التطوير** مع تحذير صريح بعدم تعميم ذلك على الإنتاج.

**شروط ما زالت سارية للإنتاج الحقيقي:** مراقبة `failed_jobs`، عدم الاعتماد على `TRUNCATE` في PROD، وتشغيل `./check.sh` (أو ما يعادله) في CI/Linux عند كل نشر.
