# المرحلة التالية — تنفيذ آمن (Pilot / ما بعد الأتمتة المحلية)

هذا المستند **لا يستبدل** [`Staging_Deploy_Runbook.md`](./Staging_Deploy_Runbook.md) ولا [`Staging_Manual_Test_Checklist.md`](./Staging_Manual_Test_Checklist.md)؛ يضع **ترتيباً آمناً** لما تفعلونه بعد نجاح الاختبارات في المستودع.

## مبدأ الأمان

| افعل | لا تفعل |
|------|---------|
| Staging أولاً، بيانات تجريبية أو عميل واحد محدود | تشغيل Pilot على إنتاج حقيقي بكامل الفروع دفعة واحدة |
| أسرار و`.env` خارج Git؛ نسخ احتياطي قبل التجارب المالية | مشاركة توكنات أو كلمات مرور في الدردشة |
| `APP_DEBUG=false` على Staging/Prod | ترك `APP_DEBUG=true` علناً |
| مراجعة `failed_jobs` يومياً أثناء Pilot | تجاهل الطوابير الفاشلة |

## الخطوة 1 — فحص ما قبل البدء (قراءة فقط)

من **جذر المشروع** (PowerShell):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/preflight-pilot-readonly.ps1
```

مع التحقق من **OCR داخل Docker** (بعد `docker compose up -d`):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/preflight-pilot-readonly.ps1 -WithOcrVerify
```

**Linux / macOS / Git Bash:** `make preflight-pilot-readonly` أو `bash scripts/preflight-pilot-readonly.sh` (خيارات: `--with-ocr-verify`، `--skip-frontend`، `--api-url=…`، `--frontend-url=…`).

للتحقق من عنوان Staging:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/preflight-pilot-readonly.ps1 `
  -ApiBaseUrl "https://staging.مثال.com" `
  -FrontendBaseUrl "https://staging.مثال.com"
```

- **لا يغيّر** قاعدة البيانات ولا ينشر شيئاً.
- يتحقق من سياسة ملفات env النموذجية + `/api/v1/health` + (اختياري) الواجهة + حالة Docker + (اختياري) **`ocr:verify --fail`** في الحاوية.
- إذا كان الجذر على `http://127.0.0.1` يعيد **502** (nginx بدون SPA جاهز)، اترك `-FrontendBaseUrl` فارغاً أو أضف `-SkipFrontend` للتحقق من الـ API والسياسة فقط.

## الخطوة 2 — أتمتة المستودع (بعد `docker compose up -d`)

| الأمر | الغرض |
|--------|--------|
| `make staging-gate` (أو Windows: `make staging-gate-ps`) | Vitest + PHPUnit مراحل 0–7 + **`ocr:verify --fail`** (Tesseract eng+ara)؛ أو سريع: `make ocr-verify` إن كان المكدس شغّالاً |
| `make fe-phases` أو `pwsh -File scripts/fe-phases.ps1` | نفس **Vitest 0→6** كوظيفة CI `frontend-phase-gates` (بدون Docker) |
| `make verify` | lint + build واجهة + PHPUnit كامل |
| `make integrity-verify` أو `docker compose exec -T app php artisan integrity:verify` | سلامة فواتير/مخزون/محفظة (قراءة تحقق) |

**Windows (بدون `make`):** نفّذ مرة واحدة من جذر المشروع:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/pilot-step2-docker.ps1
```

(نفس ترتيب الجدول أعلاه؛ يتوقف عند أول فشل.)

- فشل ترحيل القيد للواجهة: استجابة **503** مع `code: LEDGER_POST_FAILED` و`trace_id` — الواجهة تعرض تنبيهاً ورسالة إعادة محاولة (POS / إنشاء فاتورة)؛ راقب السجلات `ledger.alert.ledger_posting_failed`.

## الخطوة 3 — قائمة Staging اليدوية (إلزامية للـ Pilot)

نفّذ بالترتيب: [`Staging_Manual_Test_Checklist.md`](./Staging_Manual_Test_Checklist.md)  
على الأقل: **أ، ب، هـ**؛ و**ج، د** إن كانت منصة أو بوابات مفعّلة عندكم.

**قبل أو بجانب Staging (محلي، آلي):** [`scripts/pilot-step3-local-gate.ps1`](../scripts/pilot-step3-local-gate.ps1) — يغطي **A1** + فحص **`/api/v1/health`** والجذر على المكدس المحلي؛ أضف **`-WithOcrVerify`** لـ **`ocr:verify --fail`** داخل Docker؛ أضف **`-WithE2e`** لتشغيل **`npm run test:ci`** (Playwright كما في GitHub). **لا يغني** عن **ب، ج، د، هـ** على عنوان Staging الحقيقي.

**سجل مختصر (انسخه في تذكرة/وثيقة داخلية):**

| البند | PASS / FAIL | ملاحظة |
|------|-------------|--------|
| أ A1–A2 | | |
| ب B1–B3 | | |
| ج C1–C2 (إن انطبق) | | |
| د D1–D3 (إن انطبق) | | |
| هـ H1–H3 | | |
| المنفّذ / التاريخ | | |

## الخطوة 4 — مسار Pilot واحد (حدّدونه أنتم)

قبل لمس بيانات عميل حقيقي، اكتبوا **جملة واحدة** لنطاق الـ Pilot، مثال:

> فرع واحد، موظفان، مسار: عميل → مركبة → أمر عمل → فاتورة (بدون ZATCA في الأسبوع الأول).

ثم نفّذوا ذلك على Staging **كاملاً مرة واحدة** مع توثيق: من نفّذ، التاريخ، PASS/FAIL.

## الخطوة 5 — تشغيل إنتاجي آمن (لاحقاً)

- نسخ احتياطي مجدول + **استعادة تجريبية موثّقة**.
- مطابقة متغيرات [`backend/.env.staging.example`](../backend/.env.staging.example) المنطق مع إنتاج (بدون نسخ ملفات كاملة إن اختلفت الأسرار).
- بناء الواجهة مع `VITE_DEPLOY_ENV=production` و`VITE_PUBLIC_SITE_URL` عند الحاجة لـ SEO/الهبوط.

## مراجع سريعة

- [`Staging_Governance_Policy.md`](./Staging_Governance_Policy.md)
- [`Execution_Order_Asas_Pro.md`](./Execution_Order_Asas_Pro.md)
- `make test-project-gate` — واجهة كاملة (`test:ci`) ثم PHPUnit داخل Docker (على المضيف + Docker).
