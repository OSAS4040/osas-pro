# Platform Testing Gate

بوابات الاختبار الإلزامية لكل موجة. **لا إغلاق موجة** دون استيفاء الصف المتعلق بها (مع استثناء WAVE 0 الذي توثيقي).

---

## 0. أدوات ومرجعيات المستودع

| البوابة | الأمر / المسار | الغرض |
|---------|----------------|--------|
| Staging gate (محلي) | `bash scripts/staging-gate.sh` أو `make staging-gate` أو Windows: `pwsh -File scripts/staging-gate.ps1` / `make staging-gate-ps` | Vitest واجهة + PHPUnit مراحل 0–7 + **`ocr:verify --fail`** |
| Pilot step 2 (Windows) | `powershell -ExecutionPolicy Bypass -File scripts/pilot-step2-docker.ps1` | Docker + staging-gate (يشمل OCR) + verify |
| Frontend CI-like | `cd frontend && npm run test:ci` | lint + typecheck + unit + e2e |
| Backend | `cd backend && php artisan test` (أو `./vendor/bin/phpunit` حسب الإعداد) | Feature + Unit |

> **ملاحظة WAVE 0:** لم يُشغَّل تشغيل كامل لهذه الأوامر ضمن مهمة التوثيق؛ سجّل نتائجك الفعلية (PASS/FAIL + logs) في نسخة لاحقة من هذا الملف أو في PR.

---

## 1. قالب إغلاق موجة (يُنسخ لكل Wave)

- [ ] **Backend:** feature/integration tests للمسارات المعدّلة  
- [ ] **Frontend:** unit/component حيث يلزم؛ Playwright للتدفقات الحرجة  
- [ ] **Manual QA:** قائمة يدوية من [`Staging_Manual_Test_Checklist.md`](./Staging_Manual_Test_Checklist.md) إن وُجدت  
- [ ] **Permissions:** اختبارات فصل أدوار (least privilege)  
- [ ] **Regression:** لا فشل على مسارات الفاتورة/المحفظة/القيود إن لمس الموجة المالية  
- [ ] **Audit:** تحقق من كتابات التدقيق للأحداث الحساسة  
- [ ] **Print/Export:** عند المسّ بالتقارير أو PDF  
- [ ] **Performance sanity:** على POS / ترحيل قيود إن طُلب  

**نتيجة الموجة:** PASS | FAIL — **GO / NO-GO** للموجة التالية.

---

## 2. متطلبات حسب الموجة (ملخص)

| Wave | تركيز الاختبار الإلزامي |
|------|-------------------------|
| **0** | مراجعة وثائق فقط — لا كود |
| **1** | login success/fail، OTP، حظر/تعليق، rate limit، توجيه حسب نوع الحساب، audit login/logout |
| **2** | CRUD كيانات، سلامة علاقات FK، soft restrictions، بحث/فلترة |
| **3** | دورة lead، تحويل ملكية، تكامل مع customer/company |
| **4** | تذكرة: إنشاء/تعيين/تصعيد/إغلاق/إعادة فتح، SLA، مرفقات، تعليق داخلي vs خارجي |
| **5** | لوحات تشغيل، مهام، فرق، تنبيهات |
| **6** | صلاحيات وثائق، إصدارات، أرشفة، انتهاء صلاحية |
| **7** | **توازن القيود، عدم تعديل posted، إقفال فترة، جسر محفظة–GL، عدم انحدار تدفقات المحفظة الحالية** |
| **8** | VAT mapping، تقارير امتثال، سلامة أرشفة الفواتير |
| **9** | دورة اشتراك، فواتير، دفع، استرداد، تعليق |
| **10** | webhooks، قنوات إشعار، تفضيلات مستخدم، فصل sandbox/prod |
| **11** | هرمية HR، إجازات، وصول وثائق |
| **12** | SoD، موافقات، impersonation audit |
| **13** | اتساق أرقام التقارير، تصدير، explainability للمؤشرات |
| **14** | بحث، اختصارات، مراجعة بصرية شاملة |
| **15** | فهارس، استعلامات، مراقبة، smoke staging، rollback drill |

---

## 3. سياسة النواة المالية (اختبار)

أي PR يمس `WalletService`, `LedgerService`, `FinancialGlMapping`, `WalletGlMapping`, `PostPosLedgerJob`, أو migrations القيود/المحفظة:

1. **يجب** تمرير مجموعة اختبارات تكامل مالية (موجودة أو مُضافة في نفس PR).  
2. **يجب** تشغيل أوامر/تقارير reconciliation المعتمدة في الفريق إن وُجدت.  
3. **ممنوع** الدمج بدون مراجعة ثنائية (محاسب/tech lead).

انظر [`Platform_Financial_Control_Policy.md`](./Platform_Financial_Control_Policy.md).

---

## 4. PASS / FAIL للموجة الحالية

| الموجة | الحالة | ملاحظة |
|--------|--------|--------|
| WAVE 0 | **PASS** (توثيق) | لم يُنفَّذ تغيير كود؛ لم تُشغَّل أوامر الاختبار آلياً في هذه الجلسة |
| **WAVE 1** | **PASS** (إغلاق PR6) | راجع [`Platform_Wave1_Final_Closeout.md`](./Platform_Wave1_Final_Closeout.md) |

### 4.1 WAVE 1 — أوامر regression المعتمدة (PR6)

| الطبقة | الأمر (داخل Docker) | النتيجة المسجّلة (PR6) |
|--------|---------------------|-------------------------|
| Backend — هوية وجلسات وOTP وتقييد | `docker exec saas_app php artisan test tests/Feature/Auth/` | **69 passed** |
| Backend — وحدات أهلية/سياق | `docker exec saas_app php artisan test tests/Unit/Auth/` | **10 passed** |
| Frontend — توجيه ما بعد الدخول ورسائل API | `docker exec saas_frontend sh -lc "cd /app && npx vitest run src/utils/postLoginRedirect.test.ts src/utils/loginApiErrors.test.ts"` | **11 passed** |
| Staging gate (Auth ضمن phase0) | `bash scripts/staging-gate.sh` أو `staging-gate.ps1` | Vitest كامل + PHPUnit `phase0`…`phase7` + **`ocr:verify --fail`** |

**حكم الموجة التالية:** **GO** لبدء **WAVE 2** من ناحية التخطيط والتنفيذ — بشرط عدم توسيع النطاق خارج مواصفات الموجة 2 وعدم لمس النواة المالية دون بوابة مالية.

---
