# Platform Permission Matrix

**المصدر الرسمي في الكود:** `backend/config/permissions.php`  
**WAVE 0:** استخراج هيكلي للأدوار المعرفة وقائمة `all_permissions`. أي صلاحية غير مدرجة هنا يجب إضافتها عبر تغيير مركزي على الملف + اختبارات.

---

## 1. الأدوار المعرفة (`roles`)

| الدور | الوصف المختصر | نطاق الصلاحيات |
|-------|----------------|-----------------|
| `owner` | مالك شركة | `*` (الكل ضمن نموذج التطبيق) |
| `manager` | إدارة تشغيلية واسعة | تقارير مالية ومحاسبية، فروع، مستخدمين، عقود بنود، محفظة طلبات شحن، اجتماعات، … |
| `staff` | تشغيل يومي | عملاء، مركبات، مخزون، فواتير، أوامر عمل، تقارير مالية/تشغيلية محدودة، طلبات شحن |
| `cashier` | نقطة بيع | أضيق من staff (بدون تقارير محاسبية كاملة) |
| `accountant` | محاسبة | فواتير عرض/إنشاء/تحديث، تقارير محاسبية، مشتريات عرض، مراجعة طلبات شحن |
| `technician` | فني | مركبات، أوامر عمل تحديث، مخزون عرض |
| `viewer` | قراءة | كيانات تشغيلية بدون تعديل |
| `fleet_contact` | طرف عميل — تشغيل أسطول | محفظة أسطول، أوامر عمل، مركبات |
| `fleet_manager` | طرف عميل — اعتماد | ما سبق + اعتماد WO + تقارير أسطول |
| `phone_onboarding` | تسجيل بالهاتف | `phone_registration.flow` فقط |

> **ملاحظة:** قد توجد أدوار أو صلاحيات إضافية في قاعدة البيانات (`roles` / `permissions` tables) تُدار عبر واجهات؛ يجب مزامنة هذا المستند عند اكتشاف فروق.

---

## 2. قائمة `all_permissions` (مرجع سريع)

الصلاحيات المعرفة مركزياً (غير شاملة لـ `owner:*`):

`phone_registration.flow`, `companies.view`, `companies.update`,  
`branches.*`, `users.*`, `config_profiles.*`,  
`customers.*`, `vehicles.*`, `products.*`, `inventory.*`,  
`invoices.*`, `work_orders.*`,  
`pricing_policies.*`, `customer_groups.*`,  
`suppliers.*`, `org_units.*`, `purchases.*`,  
`reports.view`, `reports.operations.view`, `reports.employees.view`, `reports.financial.view`, `reports.accounting.view`, `reports.intelligence.view`,  
`api_keys.manage`, `webhooks.manage`, `cross_branch_access`,  
`subscriptions.view`, `subscriptions.manage`,  
`fleet.wallet.topup`, `fleet.wallet.view`, `fleet.workorder.create`, `fleet.workorder.view`, `fleet.workorder.approve`, `fleet.vehicles.view`, `fleet.reports.view`, `fleet.plate.verify`,  
`meetings.*`,  
`wallet.top_up_requests.create`, `wallet.top_up_requests.view`, `wallet.top_up_requests.review`,  
`contracts.service_items.view`, `contracts.service_items.create`, `contracts.service_items.update`, `contracts.service_items.delete`, `contracts.service_items.match_preview`,  
`intelligence.governance.record` (مدرجة لدور manager في بداية الملف).

---

## 3. ملاحظات تدقيق WAVE 0

1. **محفظة الشركة الداخلية** تستخدم أحياناً صلاحيات `invoices.view` / `invoices.update` على مسارات `wallet` في `api.php` — يجب توثيق السبب (تبسيط RBAC تاريخي أم متطلب أمني) في Wave 12 أو Wave 7.  
2. **SoD (فصل المهام):** غير مُنمذج بالكامل في هذا الملف؛ يحتاج Wave 12 (موافقات منشئ ≠ معتمد للقيود اليدوية الحساسة).  
3. **`phone_onboarding`** مقصور على تدفق التسجيل — جيد لـ least privilege.

---

## 4. WAVE 1 — مسار داخلي لقراءة إشارات أمن الدخول (PR5)

| المسار | الحماية (من `routes/api.php`) | الملاحظة |
|--------|----------------------------------|-----------|
| `GET /api/v1/internal/auth/suspicious-login-signals` | `auth:sanctum` + `tenant` + `financial.protection` + `branch.scope` + `subscription` + `permission:users.update` | **قراءة فقط**؛ يعرض بصمات موضوع مقنّعة؛ لا يضيف صلاحية جديدة في `permissions.php` — يعيد استخدام حزمة الوصول الإدارية نفسها المستخدمة لمسارات الـ QA الداخلية المجاورة. |

---

## 5. إجراءات التحديث الآمنة

1. إضافة مفتاح permission جديد في `all_permissions`.  
2. ربطه بالأدوار المناسبة في `roles`.  
3. تطبيقه على `routes/api.php` أو السياسات `Policy`.  
4. تحديث الواجهة (gates في Vue router أو composables).  
5. اختبار انعدام تسرّب للدور الأدنى.

---

*آخر تحديث: WAVE 1 إغلاق PR6 — 2026-04-12*
