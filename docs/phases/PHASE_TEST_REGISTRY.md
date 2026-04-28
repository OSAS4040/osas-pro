# سجل اختبارات المراحل (Phase → Tests)

مجموعات PHPUnit: السمة `#[Group('phaseN')]` على مستوى الاختبار أو الصنف.

**نطاق البوابة مقابل المجموعة الكاملة:** انظر [`PHASE_GATE_SCOPE.md`](./PHASE_GATE_SCOPE.md).

## المرحلة 0 — أساس المنتج، الكتالوج، الجاهزية الأولية

| النطاق | الأمر |
|--------|--------|
| Backend PHPUnit `phase0` | `cd backend && composer test:phase0` أو `php vendor/bin/phpunit --group=phase0` |
| Frontend (كتالوج/بوابات/توثيق مسارات + توجيه ما بعد الدخول / onboarding) | `cd frontend && npm run test:phase0` |

**خلفية — ملفات إرشادية:** `tests/Feature/System/SystemCapabilitiesEndpointTest.php`, `tests/Feature/Wallet/WalletTopUpTransferInstructionsTest.php`, `tests/Feature/ProductionReadiness/ProductionReadinessCompanyFeatureProfileTest.php`، و**مجمل** `tests/Feature/Auth/*.php` (مصادقة، جلسات، عقد JSON للـ API، أجهزة الدفع، أمان PR5، أمر التشخيص).  
**واجهة (Vitest):** ملف نشاط المنشأة، بوابات staff، `postLoginRedirect`، `phoneOnboardingRedirect`، عقد `resolveViteApiProxyTarget`، تكافؤ `pwaInstallLocales`.

## المرحلة 1 — SaaS والباقات

| النطاق | الأمر |
|--------|--------|
| Backend | `composer test:phase1` |
| Frontend (تنقل إدارة المنصة / تلميحات ترحيبية — طبقة إدارية) | `cd frontend && npm run test:phase1` |

**خلفية:** `SaasPlanCatalogGateTest.php`, `PlanAddonSubscriptionTest.php`, `SaasControllerContainerTest.php`, `tests/Unit/Support/SaasPlatformAccessTest.php`.  
**واجهة (Vitest):** `platformAdminNav`, `platformAdminInPageNav`, `platformCompanyDetailInPageNav`, `usePlatformWelcomeHint`.

## المرحلة 2 — محفظة، اجتماعات، تدفقات تشغيلية

| النطاق | الأمر |
|--------|--------|
| Backend | `composer test:phase2` |
| Frontend (دفعات/عمليات/سياق تنقل — جزء من التدفق التشغيلي) | `cd frontend && npm run test:phase2` |

**خلفية:** `WalletTopUpRequestWorkflowTest.php`, `MeetingsMvpBatch2Test.php`.  
**واجهة:** `invoiceCreatePayment.test.ts`, `groupOperationsFeedByDay.test.ts`, `useNavigationContext.test.ts`.

## المرحلة 3 — أمان وعقود أخطاء

| النطاق | الأمر |
|--------|--------|
| Backend | `composer test:phase3` |
| Frontend (رموز أخطاء API + أخطاء تسجيل الدخول) | `cd frontend && npm run test:phase3` |

**خلفية:** `tests/Feature/Security/ConflictErrorContractTest.php`.  
**واجهة:** `apiErrorCodes.test.ts`, `loginApiErrors.test.ts`.

## المرحلة 4 — محاسبة ودفتر

| النطاق | الأمر |
|--------|--------|
| Backend | `composer test:phase4` |
| Frontend (عرض الفواتير، وقت محلي، تسميات حقول) | `cd frontend && npm run test:phase4` |

**خلفية:** `tests/Feature/Accounting/LedgerPostingRollbackTest.php`.  
**واجهة:** `invoicePrintDisplay.test.ts`, `datetimeLocalToIso.test.ts`, `friendlyFieldLabel.test.ts`.

## المرحلة 5 — حوكمة وصلاحيات

| النطاق | الأمر |
|--------|--------|
| Backend | `composer test:phase5` |
| Frontend (بوابات البورتالات + تكافؤ محلي فريق/هيكل) | `cd frontend && npm run test:phase5` |

**خلفية:** `tests/Feature/Governance/ContractServiceItemPermissionsTest.php`.  
**واجهة:** `portalAccess.test.ts`, `localeParity.teamOrg.test.ts`.

## المرحلة 6 — ذكاء المنصة

| النطاق | الأمر |
|--------|--------|
| Backend | `composer test:phase6` |
| Frontend (عقود/مسارات الذكاء) | `cd frontend && npm run test:phase6` |

**خلفية:** مجلد `tests/Feature/Platform/` (الاختبارات الموسومة `phase6`). **واجهة:** `src/types/platform-admin`, `src/composables/platform-admin` (بما فيها الذكاء وسياسات الحوادث والإشعارات)، `src/components/platform-admin`, عقد مسارات المنصة في `src/router`, و`platformViewsLoad` — انظر `test:phase6` في `frontend/package.json`.

## المرحلة 7 — مسار كامل ما قبل الإنتاج

| النطاق | الأمر |
|--------|--------|
| Backend | `composer test:phase7` |
| Frontend (Playwright — جمهور + مسار اختياري ببيانات دخول) | `cd frontend && npm run test:phase7` |

**خلفية:** `tests/Feature/PreProduction/RealWorkflowApiTest.php` (يحمل أيضاً مجموعات `pre-production` و`production-readiness`).  
**واجهة:** بالترتيب `readiness-gate-public.spec.ts`, `auth-public.spec.ts`, ثم `production-real-flow.spec.ts` (الأخير يتخطى إن لم تُضبط `PW_LOGIN_EMAIL` / `PW_LOGIN_PASSWORD`). سلسلة Vitest ثم Playwright: `npm run test:phases:fe:with-e2e` (يتطلب `npx playwright install chromium` في البيئة الأولى).

## ملاحظة بيئة التشغيل

إذا لم يكن `php` أو `composer` متاحاً في PATH على الجهاز، استخدم خدمة التطبيق في Docker (من جذر المستودع، مع تشغيل `postgres` و`redis`):

```bash
docker compose run --rm app php vendor/bin/phpunit --group=phase0
```

أو نفّذ نفس الأوامر من بيئة التطوير الكاملة أو من CI حيث تُثبَّت الأدوات.

## بوابة Staging (CI)

بعد `docker compose up` والترحيل، يشغّل `scripts/staging-gate.sh` بالترتيب: Vitest كاملاً، ثم **PHPUnit مرحلة مرحلة** (`phase0` … `phase7`) حيث **`phase0` يشمل اختبارات Auth**، ثم **`php artisan ocr:verify --fail`** (Tesseract eng+ara).

**واجهة من PowerShell (بدون `make`):** من جذر المستودع `pwsh -File scripts/fe-phases.ps1` أو `fe-phases-with-e2e.ps1` (أو `powershell -File ...` على Windows PowerShell 5.1).

وظيفة **`frontend-phase-gates`** في `.github/workflows/staging-gate.yml` تشغّل `npm run test:phases:fe` (**Vitest للواجهة من المرحلة 0 إلى 6**) بالتوازي مع بقية البوابة (مهلة الوظيفة 30 دقيقة). مرحلة **7** على الواجهة تبقى ضمن **`npm run test:ci`** (Playwright كاملاً) أو `npm run test:phases:fe:with-e2e` / **`make fe-phases-with-e2e`** يدوياً.
