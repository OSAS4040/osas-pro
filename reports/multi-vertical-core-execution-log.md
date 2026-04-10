# Multi-Vertical Core Execution Log

## Batch-1 (Core vs Config Extraction)

Date: 2026-04-01
Scope: Extract configurable core foundation only (no BI/AI/dashboard expansion).

### Implemented

- Added schema foundation:
  - `backend/database/migrations/2026_04_01_109000_create_configurable_core_tables.php`
  - New tables:
    - `vertical_profiles`
    - `config_settings`
  - Added profile linkage fields:
    - `companies.vertical_profile_code`
    - `branches.vertical_profile_code`
- Added models/services:
  - `backend/app/Models/VerticalProfile.php`
  - `backend/app/Models/ConfigSetting.php`
  - `backend/app/Services/Config/ConfigResolverService.php`
- Added vertical profile seeder:
  - `backend/database/seeders/VerticalProfilesSeeder.php`
  - registered in `backend/database/seeders/DatabaseSeeder.php`
- Added extraction reference doc:
  - `docs/core-vs-config-extraction.md`
- Added tests:
  - `backend/tests/Feature/Config/MultiVerticalConfigCoreBatch1Test.php`
- Added measurement artifacts:
  - `backend/reports/multi-vertical-core/core-config-extraction.batch1.before.json`
  - `backend/reports/multi-vertical-core/core-config-extraction.batch1.after.json`

## Batch-2 (Seed + First Operational Use Cases)

Date: 2026-04-01
Scope: Seed bounded operational settings and wire first live use-cases only.

### Implemented

- Added config settings seeder:
  - `backend/database/seeders/ConfigSettingsSeeder.php`
  - registered in `backend/database/seeders/DatabaseSeeder.php`
- Extended resolver casting + bool helper:
  - `backend/app/Services/Config/ConfigResolverService.php`
- Wired live toggles in bounded operational paths:
  - `backend/app/Http/Controllers/Api/V1/QuoteController.php` (`quotes.enabled`)
  - `backend/app/Http/Controllers/Api/V1/WalletController.php` (`wallet.enabled`)
  - `backend/app/Http/Controllers/Api/V1/POSController.php` (`pos.quick_sale_enabled`)
  - `backend/app/Http/Controllers/Api/V1/WorkOrderController.php` (`work_orders.require_bay_assignment`)
  - `backend/app/Http/Controllers/Api/V1/BayController.php` (`bookings.enabled`)
  - `backend/app/Http/Controllers/Api/V1/FleetController.php` (`fleet.approval_required`)
- Added test coverage:
  - `backend/tests/Feature/Config/MultiVerticalConfigCoreBatch1Test.php` (full precedence chain)
  - `backend/tests/Feature/Config/MultiVerticalConfigCoreBatch2Test.php` (live behavior toggles)
- Added measurement script/artifacts:
  - `backend/scripts/config_core_batch2_measure.php`
  - `backend/reports/multi-vertical-core/core-config-batch2.before.json`
  - `backend/reports/multi-vertical-core/core-config-batch2.after.json`

### Test Result

- `docker compose exec -T app php artisan test tests/Feature/Config/MultiVerticalConfigCoreBatch1Test.php tests/Feature/Config/MultiVerticalConfigCoreBatch2Test.php`
- Result: `7 passed / 0 failed` (`14 assertions`)

## Batch-3 (Profile Assignment + Effective Config Visibility)

Date: 2026-04-01
Scope: assign vertical profiles on company/branch and expose effective config read-only.

### Implemented

- Added assignment/effective-config actions:
  - `backend/app/Http/Controllers/Api/V1/CompanyController.php`
  - `backend/app/Http/Controllers/Api/V1/BranchController.php`
- Added routing for lightweight admin actions:
  - `PATCH /api/v1/companies/{id}/vertical-profile`
  - `PATCH /api/v1/branches/{id}/vertical-profile`
  - `GET /api/v1/companies/{id}/effective-config`
  - `GET /api/v1/branches/{id}/effective-config`
  - file: `backend/routes/api.php`
- Enabled profile persistence through mass assignment:
  - `backend/app/Models/Company.php`
  - `backend/app/Models/Branch.php`
- Added Batch-3 test coverage:
  - `backend/tests/Feature/Config/MultiVerticalConfigCoreBatch3Test.php`
  - Includes: company override, branch override, vertical fallback, and behavior change after assignment.
- Added Batch-3 measurement script/artifacts:
  - `backend/scripts/config_core_batch3_measure.php`
  - `backend/reports/multi-vertical-core/core-config-batch3.before.json`
  - `backend/reports/multi-vertical-core/core-config-batch3.after.json`

## Final Sign-off Gate (Phase Closure)

Date: 2026-04-01
Scope: formal closure evidence only (no new functional scope).

### Executed

- Final regression suite rerun:
  - `docker compose exec -T app php artisan test tests/Feature/Config/MultiVerticalConfigCoreBatch1Test.php tests/Feature/Config/MultiVerticalConfigCoreBatch2Test.php tests/Feature/Config/MultiVerticalConfigCoreBatch3Test.php`
  - Result: `11 passed / 0 failed (23 assertions)`
- Final sign-off gate artifact generated:
  - `backend/scripts/multi_vertical_core_signoff_gate.php`
  - `backend/reports/multi-vertical-core/multi-vertical-core-signoff-gate-final.json`
- Closure docs prepared:
  - `reports/multi-vertical-core-closure-package.md`
  - `reports/multi-vertical-core-signoff-draft.md`
  - `reports/multi-vertical-core-signoff-final-for-circulation.md`

### Gate correction included

- Assignment evidence was added directly into the gate artifact using concrete company/branch fixture assignment.
- Gate metrics now capture assignment impact in artifact itself:
  - `companies_with_vertical_profile: 0 -> 1`
  - `branches_with_vertical_profile: 0 -> 1`
