# خريطة المسارات والصلاحيات — إدارة المنصة

## واجهة Frontend (`/platform/*`)

| Route Name | Path | الغرض | من يراه | نوع الوصول |
|---|---|---|---|---|
| `platform-overview` | `/platform/overview` | الملخص التنفيذي | أدوار المنصة | قراءة |
| `platform-incidents` | `/platform/intelligence/incidents` | Incident Center | من يملك incidents.read | قراءة |
| `platform-incident-detail` | `/platform/intelligence/incidents/:incidentKey` | تفاصيل الحادث + panels | حسب صلاحيات الذكاء | قراءة + تنفيذ آمن |
| `platform-intelligence-command` | `/platform/intelligence/command` | Command Surface | incidents.read | قراءة |
| `platform-notifications` | `/platform/notifications` | جميع التنبيهات | notifications.read | قراءة |
| `platform-support` | `/platform/support` | تذاكر الدعم | support.read | قراءة/إدارة حسب الدور |
| `platform-finance` | `/platform/finance` | نموذج مالي للشركات | financial_model.manage (الإدارة) | تنفيذ آمن |
| `platform-cancellations` | `/platform/cancellations` | طلبات الإلغاء | cancellations.read/manage | تنفيذ آمن |

## API رئيسية (Backend) وصلاحياتها

| API | الصلاحية المطلوبة | طبيعة العملية |
|---|---|---|
| `GET /api/v1/platform/notifications` | `platform.notifications.read` | قراءة |
| `GET /api/v1/platform/intelligence/incidents` | `platform.intelligence.incidents.read` | قراءة |
| `GET /api/v1/platform/intelligence/incidents/{incident_key}` | `platform.intelligence.incidents.read` | قراءة |
| `GET /api/v1/platform/intelligence/incidents/{incident_key}/decisions` | `platform.intelligence.decisions.read` | قراءة |
| `POST /api/v1/platform/intelligence/incidents/{incident_key}/decisions` | `platform.intelligence.decisions.write` | تنفيذ آمن |
| `GET /api/v1/platform/intelligence/command-surface` | `platform.intelligence.incidents.read` | قراءة |
| `GET /api/v1/platform/intelligence/incidents/{incident_key}/controlled-actions` | `platform.intelligence.controlled_actions.view` | قراءة |
| `POST /api/v1/platform/intelligence/controlled-actions/{action_id}/schedule-follow-up-window` | `platform.intelligence.controlled_actions.schedule` | تنفيذ آمن |
| `POST /api/v1/platform/intelligence/incidents/{incident_key}/workflows/execute` | `platform.intelligence.guided_workflows.execute` | تنفيذ آمن |
| `GET /api/v1/platform/support/tickets` | `platform.support.read` | قراءة |
| `PATCH /api/v1/platform/support/tickets/{id}` | `platform.support.manage` | تنفيذ آمن |
| `PATCH /api/v1/platform/companies/{id}/financial-model` | `platform.financial_model.manage` | تنفيذ آمن |

## ملاحظات حساسية

- مسارات الذكاء (`incidents/workflows/controlled-actions`) ليست مالية مباشرة، لكنها تشغيلية حساسة.
- أي فشل في حارس الصلاحيات أو ظهور زر تنفيذ لغير مخول = مانع إطلاق.
- منع الوصول غير المصرح يجب أن يُظهر رسالة آمنة وواضحة.

