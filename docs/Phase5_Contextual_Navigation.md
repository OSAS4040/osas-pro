# OSAS Phase 5 — Contextual Drill-Down & Read-Only Navigation

## Principle

**From signal → to context → without mutation.**

The Smart Command Center remains read-only. Phase 5 adds **navigable entity references** so managers can open the relevant operational screen in the SPA using **Vue Router** only (no write APIs, no automation).

## Entity reference format

Each item in `GET /api/v1/internal/intelligence/command-center` may include:

```json
{
  "type": "invoice",
  "id": 42,
  "label": "فاتورة #42",
  "href": "/invoices/42"
}
```

| Field | Description |
|--------|-------------|
| `type` | Logical entity (`invoice`, `work_order`, `vehicle`, `customer`, `wallet_transaction`, static keys like `governance`, `command_center`, …). |
| `id` | Numeric aggregate id when applicable, or a stable string key for static links. |
| `label` | Short Arabic UI label. |
| `href` | **Internal SPA path** (same paths as `vue-router`), not an external URL. |

### How references are populated (backend)

1. **Ref pool** — Distinct `(aggregate_type, aggregate_id)` from `domain_events` in the insights window (numeric `aggregate_id` only). Mapped to SPA paths:
   - `invoice` → `/invoices/{id}`
   - `work_order` → `/work-orders/{id}`
   - `vehicle` → `/vehicles/{id}`
   - `customer` → `/customers` (list; label still shows customer id)
   - `wallet_transaction` → `/wallet`
2. **Scoring** — Up to **3** refs per signal: static contextual links (e.g. governance for ingestion failures) merged with pool refs ranked by keyword match on title/basis/detail; fallback to top pool entries.
3. **Dedup** — By `href`, max 3 refs per command item.

No new persistence; **SELECT-only** on existing intelligence read path.

## Navigation flow

1. User opens **مركز العمليات الذكي** (`/internal/intelligence`).
2. Under **سياق تشغيلي**, user clicks a chip → `RouterLink` to `{ path: href, query: { source: 'command-center' } }`.
3. Target page (e.g. invoice, work order, vehicle) loads **normally**; existing route guards (`requiresAuth`, `requiresManager`, subscription, etc.) apply unchanged.
4. **`NavigationSourceHint`** (optional UI only) appears when `?source=command-center` is present and the route is **not** the command center itself — copy explains contextual read-only entry.

### Drill-down on card (frontend-only)

**تفاصيل إضافية** toggles a panel showing `meta` JSON (read-only). No extra backend call.

## Safety guarantees

* No **POST / PUT / PATCH / DELETE** from Phase 5 navigation or hints.
* No automation or background jobs.
* No changes to financial mutation flows; invoice/work order pages keep their existing actions — Phase 5 does not trigger them.
* Links are **in-app routes**; authorization remains server-side on subsequent GETs as today.
* `source=command-center` is **hint-only**; ignored by APIs.

## Examples

| Signal context | Example `href` |
|----------------|----------------|
| Pool: recent invoice event | `/invoices/12` |
| Pool: work order | `/work-orders/55` |
| Alert: event record failures | `/governance` |
| Alert: zero events while persist on | `/settings/integrations` |
| Recommendation: wallet skew | `/wallet` |
| Recommendation: no events | `/internal/intelligence` |

## Files touched (summary)

* Backend: `Phase4CommandCenterService` — ref pool + composition.
* Frontend: `CommandItemCard.vue`, `NavigationSourceHint.vue`, `EntityReference` types, hints on invoice / work order / vehicle show views.
* Tests: `Phase2ReadonlyApiTest` — structure + sample hrefs.

## Phase 5+ — UX & performance (frontend)

### Progressive loading (`useIntelligenceCommandCenter`)

* All intelligence endpoints (**overview**, **insights**, **recommendations**, **alerts**, **command-center**) are requested **in parallel**.
* **`loading`**: full-page blocking state only on the **first** bootstrap while **no** slice has arrived yet (`!hasAnyData`).
* **`hasAnyData`**: becomes true as soon as **any** endpoint returns (even `null` body on failure for that slice) — the **first completed HTTP round-trip** clears the full skeleton via `unlockSkeletonOnFirstResponse()` in `finally`, so perceived latency tracks the **fastest** route, not the slowest.
* **`refreshing`**: subsequent manual refresh sets `refreshing` (not full-page `loading`); section-level spinners are **not** re-enabled on refresh to avoid flicker — only the summary **shimmer bar** + refresh icon spin.
* **`sectionLoading`**: on **bootstrap only** (`isBootstrap`), each section flag is toggled per request so **Insights** and **Alerts** can show **inline skeletons** until their data exists.

### Partial rendering (`IntelligenceCommandCenterView`)

* **Header** (title, subtitle, quick tip) is **always visible** (including above the first-load skeleton) so the user never lands on an anonymous blank.
* **Summary strip** renders whenever **`hasAnyData`** is true.
* **Summary fallback:** if `command-center` is not yet available, counts default to **0** and **`low_signal`** is inferred from **`insights_snapshot`**, then **`insights.totals.events`**, then **overview-only** presence. When `commandCenter` is still missing but other slices exist, **`summaryDegraded`** shows a short notice on the strip.
* **Zones (Now / Next / Watch):** while `hasAnyData && !commandCenter && sectionLoading.commandCenter`, **`CommandZonesSkeleton`** replaces the three zone columns. After the command-center response (success or failure), real **`CommandZoneCard`**s render (possibly empty).
* **Insights:** internal skeleton when `sectionLoading.insights && !insights`; then KPI cards, **`InsightsSparkline`**, and top event types table.
* **Alerts:** internal skeleton when `sectionLoading.alerts && !alerts`; cards include severity, optional **`detected_at`**, basis in a monospace panel, and a **read-only `RouterLink`** (“متابعة في السياق”) using **heuristic** route from `type`/`message` (no API schema change).

### Command cards & attrs

* **`CommandItemCard`**: `inheritAttrs: false` and **`v-bind="$attrs"`** on the root **`article`** so **`class`** / **`style`** from **`CommandZoneCard`** (e.g. stagger animation) merge predictably with the component’s base classes.

### Navigation hint

* **`NavigationSourceHint`**: clearer Phase 5 copy, visual icon container, gradient border treatment, **dismiss (X)**, and **return** link to **`internal.intelligence`**. Used on **dashboard, wallet, governance, integrations, customers list**, and invoice / work order / vehicle **show** pages.

### Files (Phase 5+)

* `frontend/src/composables/useIntelligenceCommandCenter.ts`
* `frontend/src/views/internal/IntelligenceCommandCenterView.vue`
* `frontend/src/components/intelligence/IntelligenceSummaryStrip.vue`
* `frontend/src/components/intelligence/IntelligenceCommandCenterSkeleton.vue`
* `frontend/src/components/intelligence/CommandZonesSkeleton.vue`
* `frontend/src/components/intelligence/InsightsSparkline.vue`
* `frontend/src/components/intelligence/CommandZoneCard.vue` / `CommandItemCard.vue`
* `frontend/src/components/NavigationSourceHint.vue`

## Related

* Phase 4: `docs/Phase4_Smart_UX_Command_Center.md`, `docs/OSAS_Phase4_Final_Delivery_Report.md`
