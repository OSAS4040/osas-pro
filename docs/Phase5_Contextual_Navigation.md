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

## Related

* Phase 4: `docs/Phase4_Smart_UX_Command_Center.md`, `docs/OSAS_Phase4_Final_Delivery_Report.md`
