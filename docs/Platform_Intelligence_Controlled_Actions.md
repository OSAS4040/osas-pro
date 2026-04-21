# Platform Intelligence — Controlled Actions

## 1) Goal

Introduce a **strictly allowlisted** layer of **safe operational follow-ups** (create, assign, schedule, link internal references, complete, cancel, reopen) tied to incidents, with **granular permissions**, **idempotency** where required, **full traceability**, and **no automatic incident lifecycle or financial/domain mutations**.

## 2) Phase boundaries

**In scope**

- Persisted `platform_controlled_actions` rows with a stable **contract** (`PlatformControlledActionContract`).
- Dedicated **POST** routes per operation (no generic “execute action” endpoint).
- **Execution** only via `ControlledActionExecutor` (not controllers/components).
- Analytical traces (`controlled_action_*` event types).
- UI panel on incident detail for operators with the right grants.

**Out of scope**

- Remediation, auto-fix, bulk mutation, external automation.
- Ledger, wallets, posting, reconciliation, invoices, inventory, tenant business data.
- Broad rule engines or arbitrary payloads.

## 3) Official allowlist (operations)

Canonical class: `App\Support\PlatformIntelligence\ControlledActions\ControlledActionAllowlist`.

1. `create_follow_up`
2. `assign_follow_up_owner`
3. `request_human_review`
4. `schedule_follow_up_window`
5. `link_incident_to_internal_task_reference`
6. `mark_follow_up_completed`
7. `cancel_follow_up_with_reason`
8. `reopen_follow_up_if_needed`

## 4) Persisted `action_type` (artifact contract field)

Stored values (distinct from operation names):

- `follow_up` — created by `create_follow_up`
- `human_review_request` — created by `request_human_review`
- `internal_task_reference` — created by `link_incident_to_internal_task_reference`

## 5) Action state model

Enum `ControlledActionStatus`: `open`, `assigned`, `scheduled`, `completed`, `canceled`, `blocked` (reserved).

**Separation:** action status does **not** change `platform_incidents.status` or decision log rows automatically.

## 6) Preconditions (high level)

| Rule | Enforcement |
| --- | --- |
| No create / human-review / task-link on **closed** incidents | `ControlledActionExecutor` |
| No duplicate **open** human review for same incident | executor query |
| `cancel` requires `canceled_reason` | validation |
| `mark_completed` requires `completion_reason`; only `follow_up` / `human_review_request` | validation |
| `schedule_follow_up_window` only for `follow_up` artifacts; states `open`/`assigned` | validation |
| `assign` allowed for `open`/`assigned`/`scheduled` | validation |
| `reopen` only from `completed`/`canceled`, and not if incident is **closed** | validation |
| External reference format | regex in executor |

## 7) Permission model

Keys in `ControlledActionPermissionMatrix` (also listed in `config/platform_permissions.php`):

- `platform.intelligence.controlled_actions.view`
- `platform.intelligence.controlled_actions.create_follow_up`
- `platform.intelligence.controlled_actions.request_human_review`
- `platform.intelligence.controlled_actions.link_task_reference`
- `platform.intelligence.controlled_actions.assign_owner`
- `platform.intelligence.controlled_actions.schedule`
- `platform.intelligence.controlled_actions.complete`
- `platform.intelligence.controlled_actions.cancel`
- `platform.intelligence.controlled_actions.reopen`

All mutating executor paths additionally require `platform.intelligence.incidents.read` (routes are nested under the incidents.read middleware group).

**Role defaults:** `platform_admin`, `operations_admin`, `support_agent` — full controlled-action set; `auditor` — **view** only; `finance_admin` — none.

## 8) Idempotency policy

- Optional `idempotency_key` (max 128) on **create** operations.
- Unique index `(incident_key, idempotency_key)` — replays return the **same** `action_id` and do not insert duplicates.

## 9) Traceability

`PlatformIntelligenceTraceEventType` additions:

- `controlled_action_created`
- `controlled_action_assigned`
- `controlled_action_scheduled`
- `controlled_action_completed`
- `controlled_action_canceled`
- `controlled_action_reopened`

Emitter: `ControlledActionTraceEmitter` (source `platform_controlled_actions`).

## 10) API surface

Under `/api/v1` (authenticated platform):

- `GET /platform/intelligence/incidents/{incident_key}/controlled-actions`
- `POST .../controlled-actions/create-follow-up`
- `POST .../controlled-actions/request-human-review`
- `POST .../controlled-actions/link-internal-task-reference`
- `POST /platform/intelligence/controlled-actions/{action_id}/assign-owner`
- `POST .../schedule-follow-up-window`
- `POST .../mark-completed`
- `POST .../cancel`
- `POST .../reopen`

## 11) UI scope

- `PlatformIncidentControlledActionsPanel.vue` on incident detail.
- Composable `usePlatformControlledActions.ts` mirrors permissions and endpoints.

## 12) Out of scope (explicit)

- Financial writes, domain remediation, cross-tenant data access.
- Deleting historical controlled-action rows (MVP keeps audit trail).

## 13) Closure criteria

- Allowlist enforced in code paths + unit test.
- Permissions + preconditions covered by feature tests.
- No incident row mutation in controlled-action flows (asserted in tests).
- Idempotency + duplicate human-review rules tested.
- Guardrails: no generic `execute` route; no POST on correlation/command-surface (existing).
- Documentation (this file) complete.

## 14) Transition blockers

Do not expand to “arbitrary actions”, unbounded PATCH payloads, or silent incident side effects without new policy, tests, and documentation.

---

## 15) Hardening & Final Closure (إعلان إغلاق)

**حالة الإغلاق:** تم تعزيز الطبقة للنشر — هجرة قاعدة البيانات، دخان للـ API، جدولة آمنة من الواجهة، وUX لكل صف دون حقول مشتركة.

### بعد النشر (إلزامي)

1. `php artisan migrate` (يشمل `platform_controlled_actions`).
2. **Smoke / regression — Controlled Actions:**
   - `php artisan test tests/Feature/Platform/PlatformControlledActionsApiTest.php`
   - أو تشغيل مجموعة `tests/Feature/Platform` كاملة في CI.
3. **الجدولة:** الواجهة تُحوّل `datetime-local` عبر `datetimeLocalToIso` ولا ترسل طلباً إذا كان التاريخ غير صالح؛ الخادم يرفض `scheduled_for` غير قابل للتحليل بـ **422** (`invalid_datetime`).

### تحسينات الإغلاق (مطبّقة)

- مسودات **لكل `action_id`** (مالك، جدولة، إكمال، إلغاء) — لا تداخل بين الصفوف.
- رسالة خطأ محلية للعمليات (`localOpError`) مع تلميح تحت حقل الجدولة.
- اختبار وحدة `datetimeLocalToIso.test.ts` + اختبار API `test_schedule_rejects_invalid_datetime`.

### ما يمكن ترحيله لاحقاً

- تنسيق بصري أوسع (خطوط، مسافات) دون تغيير العقود أو المسارات.
