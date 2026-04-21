# Phase 6 — Explainability Layer (Why Engine + Signal Transparency)

## Purpose

Every signal in the command center carries a **structured explanation** so operational intelligence is not a black box. Managers see *what* the system suggests and *why* it fired, using the same read-only Phase 2 data the backend already computes.

## Why Engine structure

Each command-center item (alerts and recommendations) includes:

| Field | Type | Meaning |
| --- | --- | --- |
| `why_details` | `string[]` | Short, plain-language bullets (Arabic where applicable) |
| `signals_used` | `string[]` | Logical metric / signal identifiers that fed the rule |
| `thresholds` | `object` | Named parameters and limits evaluated (includes `rule_id` when applicable) |
| `confidence` | `number \| null` | Optional 0–1 score; `null` when not meaningful |

These keys are merged at the **top level** of each zone item alongside existing fields (`title`, `why_now`, `related_entity_references`, etc.).

### Backend location

- `App\Services\Intelligence\Phase6\CommandCenterExplainability` — rule-specific builders.
- `App\Services\Intelligence\Phase4\Phase4CommandCenterService` — attaches explainability after Phase 2 normalization; response `phase` is **6**.

## Example payloads

### Alert (volume spike)

```json
{
  "id": "event_volume_spike",
  "source": "alert",
  "severity": "warning",
  "title": "…",
  "why_now": "…",
  "why_details": [
    "العدد الأخير: 120، العدد السابق: 40.",
    "نقارن عدد أحداث المجال المسجّلة في آخر 24 ساعة مع الـ 24 ساعة التي قبلها.",
    "لا يُعتبر هذا تنبيهًا ماليًا — مراقبة تشغيلية فقط."
  ],
  "signals_used": [
    "domain_events.count_24h_rolling_last",
    "domain_events.count_24h_rolling_prior"
  ],
  "thresholds": {
    "prior_24h_minimum": 5,
    "last_must_exceed_prior_by_factor": 2,
    "rule_id": "event_volume_spike"
  },
  "confidence": 0.9
}
```

### Recommendation (no events in window)

```json
{
  "id": "no_events_in_window",
  "source": "recommendation",
  "why_details": [ "…" ],
  "signals_used": [ "domain_events.total_in_window" ],
  "thresholds": { "min_events_for_activity": 1, "rule_id": "no_events_in_window" },
  "confidence": null
}
```

## UI behavior

- In `CommandItemCard`, an expandable section labeled **«لماذا؟»** shows:
  - **شرح مبسّط** — bullets from `why_details`
  - **المؤشرات المستخدمة** — compact tags from `signals_used`
  - **العتبات / القواعد** — key/value grid from `thresholds` (excluding duplicate display of `rule_id` in the grid; `rule` shown in small monospace line when present)
  - **درجة الثقة التقديرية** — progress bar when `confidence` is set

Copy is aimed at **managers**, not engineers: minimal jargon, Arabic-first labels where it helps.

## Safety guarantees

- **Read-only:** Explainability is computed when building the command-center JSON. No extra HTTP verbs, no writes, no automation side effects.
- **No business mutations:** Phase 6 does not change domain state, schedules, or integrations.
- **Data sources:** Only existing Phase 2 insights, alerts, and recommendations payloads — no new ingestion paths.

The intelligence API envelope remains read-only; Phase 6 only adds transparency fields to the response.
