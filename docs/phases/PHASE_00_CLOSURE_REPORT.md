# تقرير مرحلة 0 — أساس الكتالوج والبوابات والتوثيق الأولي

**حالة الوثيقة:** إغلاق **جزئي** للنطاق المُعرَّف أدناه؛ أي توسعات لاحقة (مفاتيح كتالوج جديدة، مسارات واجهة) تُعاد تقييمها ضمن نفس المرحلة أو مرحلة لاحقة حسب قرار المنتج.

## 1. الهدف

تثبيت طبقة «الحقيقة المرجعية الأولية» التي يبني عليها باقي المراحل:

- كتالوج قدرات النظام (`/api/v1/system/capabilities`) متسق مع `config/system_capabilities.php` و`SystemCapabilitiesService`.
- توافق ملف نشاط المنشأة/الميزات مع الافتراضات (`BusinessFeatureProfileDefaults` واختبارات الجاهزية).
- تعليمات ومسارات محفظة حرجة حيث تُغطّى بالاختبارات.
- توثيق مسارات المستأجر مقابل الـ API حيث يُطبَّق فحص `docs:nav-api-check`.

## 2. قائمة تحقق

| البند | الحالة | الدليل |
|--------|--------|--------|
| كتالوج القدرات يعيد `trace_id` وهيكل `data.items` | مطلوب التحقق عبر CI | `SystemCapabilitiesEndpointTest` |
| مصادقة المستأجر، الجلسات، عقد JSON، التسجيل بالهاتف، الأمان PR5، أجهزة الدفع | مطلوب التحقق عبر CI | `tests/Feature/Auth/*.php` (مجموعة `phase0`) |
| عنصر `fixed_assets` موجود بحالة `planned` و`path` فارغ (لا تسريب مسار قبل الجاهزية) | مطلوب التحقق عبر CI | `test_fixed_assets_catalog_entry_is_planned_without_path` |
| سلوك `supplier_contract_layer` تحت نشاط `retail` (قيود النشاط) | مطلوب التحقق عبر CI | نفس الملف |
| منع `fleet_contact` من القراءة (`403` + `capabilities_staff_only`) | مطلوب التحقق عبر CI | نفس الملف |
| ملف ميزات الشركة الفعّال يعكس `fixed_assets` كما هو متوقع للجاهزية | مطلوب التحقق عبر CI | `ProductionReadinessCompanyFeatureProfileTest` |
| تعليمات التحويل للمحفظة | مطلوب التحقق عبر CI | `WalletTopUpTransferInstructionsTest` |

## 3. الاختبارات المرتبطة

- مجموعة PHPUnit: **`phase0`**
- واجهة (اختياري ضمن نفس المفهوم): **`npm run test:phase0`** في `frontend`

## 4. فجوات معروفة / متابعة

- عناصر **`planned`** تُرجع الآن **`gate`** من `feature_gate` في `SystemCapabilitiesService` (مثل `fixed_assets`) لاستخدام الواجهة دون تخمين المفتاح.
- توسيع قائمة عناصر الكتالوج أو تغيير `rollout` لأي عنصر يستلزم تحديث هذا التقرير واختبارات الكتالوج.

## 5. تشغيل التحقق

```bash
cd backend && composer test:phase0
# أو من جذر المستودع:
docker compose run --rm app php vendor/bin/phpunit --group=phase0
cd frontend && npm run test:phase0
```

**ملاحظة:** في بيئة تطوير بدون `php`/`composer` في PATH، استخدم Docker كما أعلاه أو خط أنابيب CI.

## 6. سجل تحقق حديث (تنفيذ)

- **PDF تعليمات التحويل:** إصلاح تعارض نوع الإرجاع بين `Barryvdh\DomPDF` و`StreamedResponse` — أصبحت `WalletTransferInstructionsPdfService::streamPdf()` و`WalletTopUpRequestController::transferInstructions()` تعيدان `Symfony\Component\HttpFoundation\Response` (تغطي استجابة DomPDF و`JsonResponse` عند الخطأ).
- **كتالوج `planned` + `gate`:** إرجاع `gate` من `feature_gate` لعناصر خارطة الطريق.
- **PHPUnit `phase0`–`phase7`:** يُشغَّل في بوابة Staging (`scripts/staging-gate.sh`) ثم **`ocr:verify --fail`**؛ ويمكن التحقق يدوياً عبر `docker compose run --rm app`.

## 7. نطاق البوابة مقابل الاختبار الكامل

مجموعات المراحل لا تُغني عن تشغيل PHPUnit كاملاً على `tests/Feature` عند الدمج الحساس — انظر [`PHASE_GATE_SCOPE.md`](./PHASE_GATE_SCOPE.md).
