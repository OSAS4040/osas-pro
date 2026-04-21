# Platform Phase 1 Closure And Phase 2 Kickoff

## Decision

- `Phase 1` is closed and accepted.
- Scope locked for context-resolution logic (no unplanned refactor).
- Next approved phase: `Phase 2 - Executive Dashboard Enhancement`.

## Smoke Verification (Run Immediately)

### UI (manual, required)

- Login as platform operator account.
- Login as tenant/staff account.
- Refresh directly in both sessions.
- Open direct URLs:
  - `/`
  - `/admin`
  - a staff internal page (for example `/work-orders` or `/invoices`)
- Visual acceptance:
  - tenant user never sees platform sidebar
  - platform user never gets wrong tenant sidebar flash
  - only neutral skeleton appears while context resolves
  - no disturbing layout flicker during refresh/navigation

### API (manual + automated)

- Check `GET /api/v1/admin/overview`:
  - `200` for valid platform operator
  - `403` for tenant user
  - response shape is stable (keys always present)
  - empty datasets do not break dashboard rendering

## Execution Freeze Rules (Phase 1 Guardrails)

- Do not add fallback sidebar rendering before context resolution.
- Do not split context logic across random components.
- Any context-related change must be tied to explicit issue + tests.

## Phase 2 Scope (Executive Dashboard Enhancement)

### Priority 1: Metric Definitions Contract

- Define exact meaning for:
  - active company
  - low-activity company
  - requiring-attention company
  - critical alert threshold
  - real `MRR` vs catalog estimate

### Priority 2: Dashboard Maturity Upgrade

- stronger KPI row and trend semantics
- clearer executive/operational sectioning
- richer alerts and attention lists
- improved health block and quick actions
- higher-polish visual hierarchy for admin persona

### Priority 3: Anti-regression E2E

- add E2E scenario to prevent sidebar flash regression on:
  - refresh
  - slow network
  - direct deep-link entry

## Risks To Watch

- `sessionResolved` race/timing on network timeout or delayed `/auth/me`
- health block is informational until expanded data sources are integrated
- catalog-based MRR must remain explicitly labeled as estimate

## Exit Criteria For Phase 2 (Draft)

- definitions documented and reflected in API payload
- executive dashboard blocks upgraded and production-safe
- E2E regression guard in CI for context/sidebar flash
- no behavioral impact on tenant financial/accounting flows
