# Platform Subscriptions — Operational Readiness

وحدة اشتراكات إدارة المنصة (قراءة + تشغيل واجهات). هذا المستند يثبّت معايير الإغلاق والتحقق داخل المشروع.

## Status

**Code Complete — Pending Operational Validation**

الكود (قوائم، تفاصيل، مسارات قراءة، تنقل) مُنفَّذ؛ الإعلان الرسمي بـ «Production Ready» يتطلب إكمال قائمة التحقق أدناه على بيئة حقيقية وتعبئة تقرير QA.

## Final Operational Checklist

نسخ مباشر — يُحدَّث أثناء التحقق:

- [ ] Routes available (`admin/subscriptions/*`)
- [ ] Frontend build success
- [ ] Platform permissions verified (`platform.subscription.manage` + `platform.admin` + Sanctum)
- [ ] Real data exists (subscriptions, invoices, payment_orders, bank_transactions)
- [ ] `receipt_url` working (public disk + `APP_URL`)
- [ ] End-to-End scenario completed

## Mandatory Scenario

**Company → Subscription → Payment Order → Invoice → Bank Transaction → Wallet**

يُنفَّذ يدوياً على Staging/Pre-prod مع التقاط لقطات شاشة حسب `docs/qa/platform-subscriptions/VALIDATION_REPORT.md`.

## Closure Rule

**All items must be PASS before declaring Production Ready.**

لا يُعلَن الإطلاق النهائي ولا الدمج/الوسم بحسب سياسة الفريق إلا بعد:

1. نجاح اختبار المسارات `PlatformSubscriptionsRoutesTest` في CI.
2. وجود تقرير تحقق (`docs/qa/platform-subscriptions/VALIDATION_REPORT.md`) مع الأدلة المطلوبة.
3. إتمام السيناريو أعلاه end-to-end.

## Debug health (اختياري)

عند الحاجة لفحص سريع على البيئة، عيّن في `.env`:

`PLATFORM_SUBSCRIPTIONS_DEBUG_HEALTH=true`

ثم: `GET /api/v1/admin/subscriptions/debug/health` (نفس صلاحيات المنصة). عطّل العلم بعد الفحص.

## References

- مسارات API: `routes/api.php` — مجموعة `admin/subscriptions`
- استعلامات القراءة: `PlatformSubscriptionOperationsQueryService`
- واجهات الإدارة (Vue): `frontend/src/modules/subscriptions/pages/Admin*.vue`
