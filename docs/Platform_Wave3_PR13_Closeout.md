# WAVE 3 / PR13 — Closeout: Company Profile (Operational Hub)

**التاريخ:** 2026-04-12  
**النتيجة:** PASS — `docker exec saas_app php artisan test tests/Feature/Companies/CompanyProfileTest.php` + `npm run type-check` (frontend)

---

## 1) ملخص

أُضيف **Company operational hub** read-only: **`GET /api/v1/companies/{id}/profile`** مع **`CompanyProfileQuery` + `CompanyProfileAssembler` + `CompanyProfileDTO`**، عزل شركة عبر **`CompanyPolicy::view`**، مؤشرات صحّة rule-based (**healthy / watch / at_risk / inactive**)، لقطات نشاط، علاقات (عملاء/مستخدمون/فروع)، و**عدم إظهار المقاييس المالية** إلا عند **`reports.financial.view`**. الواجهة **`/companies/:companyId`** (مركز مصغّر) مع روابط للتقارير، تدفق العمليات، العملاء، والمستخدمين.

---

## 2) الملفات

### Backend

| الملف |
|-------|
| `backend/app/Companies/Profile/CompanyProfileQuery.php` |
| `backend/app/Companies/Profile/CompanyProfileAssembler.php` |
| `backend/app/Companies/Profile/CompanyProfileDto.php` |
| `backend/app/Http/Controllers/Api/V1/CompanyProfileController.php` |
| `backend/routes/api.php` |
| `backend/tests/Feature/Companies/CompanyProfileTest.php` |

### Frontend

| الملف |
|-------|
| `frontend/src/types/companyProfile.ts` |
| `frontend/src/composables/useCompanyProfile.ts` |
| `frontend/src/components/company-profile/CompanyProfileHeader.vue` |
| `frontend/src/components/company-profile/CompanyProfileStatusBanner.vue` |
| `frontend/src/components/company-profile/CompanyProfileSummaryCards.vue` |
| `frontend/src/components/company-profile/CompanyProfileAttentionPanel.vue` |
| `frontend/src/views/companies/CompanyProfileView.vue` |
| `frontend/src/router/index.ts` |
| `frontend/src/views/settings/SettingsView.vue` |
| `frontend/src/views/DashboardView.vue` (اختصار «مركز الشركة») |

### توثيق

| `docs/Platform_Wave3_PR13_Closeout.md` |

---

## 3) migrations

**لا يوجد.**

---

## 4) العقد (مختصر)

- **`data.company`:** `id`, `name`, `status`, `type` (من `vertical_profile_code`), `created_at`
- **`data.summary`:** أعداد + `work_orders_active` + `invoices_in_period` (null بدون صلاحية مالية) + `last_activity_at` + `activity_window_days`
- **`data.activity_snapshot`:** `last_work_order`, `last_invoice`, `last_payment`, `last_ticket`
- **`data.health_indicators`:** `activity_status`, `inactivity_flag`, `open_tickets`, `possible_risk_flag`
- **`data.relationships`:** `top_customers`, `top_users`, `branches_summary`
- **`data.attention_items`:** `{ code, severity, message }[]`
- **`meta`:** `financial_metrics_included`, `read_only`

---

## 5) الاختبارات

- عزل شركة (شركة أخرى → **403**)
- مالك يرى الملف + لقطة نشاط
- `viewer` بدون مالية → `invoices_in_period` و `last_invoice` / `last_payment` = **null** و `meta.financial_metrics_included` = **false**

---

## 6) GO / NO-GO لـ PR14

**GO** — يمكن البدء في **Customer Profile** على نفس أسلوب الـ hub دون توسيع CRM كامل.
