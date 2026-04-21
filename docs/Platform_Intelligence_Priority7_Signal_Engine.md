# Platform Intelligence — Priority 7: Signal Engine

Official scope document for **PLATFORM INTELLIGENCE SIGNAL ENGINE** (read-only, contract-bound).

## 1) Goal

Produce **operational platform signals** that are unified, explainable, low-noise, and ready for a future **Incident Candidate** layer — without Incident Center, Decision Log, or executable actions.

## 2) Phase boundaries

**Produces**

- `GET /api/v1/platform/intelligence/signals` (permission: `platform.intelligence.signals.read`).
- Server-side pipeline output: **only** `PlatformSignalContract` instances (serialized JSON mirrors the canonical contract).
- Optional SPA panel (overview) listing signals with severity, confidence, why, source, affected companies, and recommended next step (advisory text).

**Does not produce**

- Ledger, journal, wallet, posting, reconciliation, or any financial mutation.
- Any `POST`/`PUT`/`PATCH`/`DELETE` under `platform/intelligence`.
- Incidents, candidates persisted as entities, ownership, escalation, or lifecycle workflows.
- Decision log entries or command execution.

## 3) Signal pipeline

1. **Collect** — `OverviewSnapshotCollector` reads the same cached executive snapshot as `/admin/overview` via `PlatformAdminOverviewService::build()` (read-only).
2. **Normalize** — `OverviewSnapshotNormalizer` validates presence of `generated_at`, `kpis`, `health`, `alerts`, `companies_requiring_attention`, and exposes typed accessors.
3. **Detect** — `OverviewBasedSignalDetector` emits internal `SignalDraft` rows (not API-facing) with explicit `signal_type`, `source`, `affected_scope`, entities, company ids, and evidence factors.
4. **Correlate** — `SignalCorrelationService` appends `correlation_keys` to prepare future grouping (no incidents).
5. **Score** — `SeverityScorer` + `ConfidenceScorer` assign enum severity and numeric confidence from documented evidence.
6. **Assemble** — `PlatformSignalEngine` maps drafts to `PlatformSignalContract` (timestamps from overview `generated_at`).
7. **Dedupe** — `SignalDedupeService` merges fingerprint-identical signals and optionally collapses high-overlap inactive vs low-activity noise.
8. **Explain** — `SignalExplainabilityComposer` appends structured Arabic interpretation lines for severity and confidence.
9. **Recommend (advisory)** — `RecommendedNextStepPolicy` adds **non-executable** guidance strings only.

Trace hooks (non-production, low volume): `SignalDetected`, `SignalDeduped`, `SignalExplained` via `NullPlatformIntelligenceTraceRecorder` in HTTP (tests may swap recorder).

### 3.1) Stable response ordering

`GET /platform/intelligence/signals` returns `data[]` sorted **deterministically** (same inputs → same order):

1. **Severity** descending (`critical` → `info`).
2. **Confidence** descending (numeric).
3. **`last_seen_at`** descending (timestamp).
4. Tie-break: **`signal_key`** ascending (lexicographic).

Implementation: `App\Support\PlatformIntelligence\SignalEngine\SignalResponseOrdering::sortStable()`.

### 3.2) Scoring rules versioning

Canonical version lives in `App\Support\PlatformIntelligence\Scoring\PlatformIntelligenceScoringRulesVersion`:

| Field | Purpose |
|--------|--------|
| `VERSION` | Bump when `SeverityScorer`, `ConfidenceScorer`, dedupe thresholds, or ordering tie-break rules change. |
| `RELEASE_DATE` | ISO date of last `VERSION` change. |
| `CHANGELOG` | One-line summary for auditors / incident-candidate work. |

The HTTP payload includes `meta.scoring_rules_version`, `meta.scoring_rules_release_date`, `meta.scoring_rules_changelog`, and `meta.signal_order` so clients and downstream layers can record which rule set produced a snapshot.

**Incident Candidate note:** when grouping or comparing across time, persist the scoring version alongside candidates so a rules upgrade does not silently reinterpret historical clusters.

### 3.3) Overview cache staleness & freshness (operational clarity)

Signals are derived from the **same cached executive overview** as `/admin/overview`:

- Cache key: `platform:admin:overview:v2`
- TTL: `PlatformAdminOverviewService::EXECUTIVE_OVERVIEW_CACHE_TTL_SECONDS` (currently **60 seconds**), also exposed as `meta.overview_snapshot_ttl_seconds` on the signals endpoint.

**What “fresh” means today:** the snapshot’s `generated_at` is at most one TTL behind real-time aggregates; after TTL expiry, the next request rebuilds the snapshot.

**What is not defined yet (future Incident Candidate layer):**

- Maximum age of a **signal** for auto-inclusion in a candidate batch (staleness threshold per `signal_key` class).
- Whether candidates should require `generated_at` within N minutes of “now”.

Document these thresholds when Candidate Layer ships; until then, operators should treat signals as **aligned with the overview cache**, not sub-second real-time telemetry.

## 4) Severity rules (deterministic)

Base rank from `PlatformSignalType` (metric/anomaly weighted higher than manual/trend). Adjustments:

- +1 rank when affected company count ≥ 20; +0.5 when ≥ 8.
- +0.5 when `supporting_factor_count` ≥ 3.
- +1.5 when `health_critical` (degraded health / API not ok).
- +1.0 when `queue_pressure` (failed_jobs threshold aligned with overview alerts).
- +0.5 when `governance_backlog` (pending financial model reviews).
- +0.5 when worst `last_activity_days_ago` ≥ 30 for tenant inactivity drafts.
- +0.5 when scheduler stamp missing (`scheduler_stale`).

Result clamped to official enum: `info|low|medium|high|critical`.

## 5) Confidence rules

Starts from overview completeness (definitions + attention + health). Adds evidence density, company coverage for company-scoped drafts, and penalties for `sparse_metrics` or `conflict_penalty`. Clamped to **[0.12, 0.96]** and rounded to 3 decimals.

## 6) Correlation & dedupe

**Correlation keys** (examples): `corr:tenant_activity_aggregate`, `corr:platform_runtime_system`, `corr:operator_attention_depth`, `corr:subscription_trial_window`.

**Dedupe**

- Fingerprint = `sha1(signal_type|source|signal_key|sorted_company_ids)`.
- Collisions merge correlation keys, union companies (cap 40), stronger severity, slightly boosted confidence.
- **Near-duplicate**: if both inactive cluster and low-activity cluster exist and company overlap ≥ 60%, merge into one contract to reduce duplicate operational noise.

## 7) Explainability contract

Every emitted signal must include:

- `why_summary` with base rationale plus **explicit** lines explaining severity and confidence (Arabic operator text).
- `summary` augmented with affected company count when companies are present.

Forbidden: generic marketing phrasing without measurable referents.

## 8) Recommended next step policy

Strings are **advisory monitoring / review** only (Arabic). They must not imply automated execution, ledger changes, or incident transitions. Keys map via `RecommendedNextStepPolicy::forSignalKey()`.

## 9) Relationship to Incident Candidate layer

Correlation keys and stable `signal_key` values (`sig.platform.*`) are the handshake fields for a future candidate builder. **No candidate or incident rows are created in Priority 7.**

## 10) Closure criteria (Priority 7)

All must be true:

1. Official engine exists under `App\Support\PlatformIntelligence\SignalEngine` (+ scoring/correlation/dedupe).
2. All public API signals are `PlatformSignalContract` serializations.
3. Severity and confidence follow documented rules and tests.
4. Dedupe demonstrably merges duplicates / high-overlap tenant noise.
5. Explainability and advisory next steps present on every signal.
6. Permission middleware enforced on `GET /platform/intelligence/signals`.
7. SPA (if enabled) gates fetch on `platform.intelligence.signals.read` and handles empty/loading/error.
8. This document exists.
9. Automated tests cover normalization, detection, scoring, dedupe, explainability, policy, and HTTP contract.
10. No financial/ledger code paths touched; no mutations introduced.

## 11) Do not advance to Incident Candidate layer if…

Signals are raw/unmerged, dedupe ineffective, severity/confidence unexplained, weak `why_summary`, decorative recommendations, missing tests/docs, or any operational action UI/API appears.
