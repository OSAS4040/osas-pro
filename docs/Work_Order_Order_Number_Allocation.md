# Work order `order_number` allocation (engineering note)

## Why `COUNT(*)+1` was removed

- Under concurrency, two transactions could derive the **same** next suffix before insert, violating the unique index `(company_id, order_number)` or causing intermittent failures.
- `COUNT(*)` is **O(n)** per company as `work_orders` grows, so peak throughput degrades with table size even when allocation was “correct”.

## Current design: `work_order_sequences`

- One row per `company_id` with `last_allocated` (last issued **numeric suffix** in `WO-{company}-{6 digits}`).
- Allocation uses a **single PostgreSQL statement**: `INSERT … ON CONFLICT DO UPDATE … RETURNING last_allocated` (atomic, **O(1)** row operation for updates; the **first** insert per company scans only that company’s `work_orders` rows matching `WO-{id}-######`).
- **Bootstrap:** if no sequence row exists yet but `work_orders` already has formatted order numbers (e.g. seeder ran before the sequence table existed), the insert path sets `last_allocated = max(suffix)+1` so the next create cannot collide with `(company_id, order_number)`.
- Migration also backfills `last_allocated` from existing `order_number` values when the table is first created.

## Transaction / rollback semantics

- Allocation runs **inside the same database transaction** as `WorkOrder` + `WorkOrderItem` inserts.
- If any step fails, PostgreSQL **rolls back the sequence increment** together with the failed work order — no “stuck” increments from aborted creates.

## Operational limits (known)

- **Hot row per tenant:** all concurrent `work_order_create` calls for one company contend on that company’s `work_order_sequences` row — expected; far cheaper than scanning `work_orders`.
- **Multi-tenant:** different companies **do not** contend on the same sequence row (see tests).

## Observability

- Optional slow-path log: set `OBS_WORK_ORDER_CREATE_WARN_MS` (milliseconds). When `WorkOrderService::create` exceeds that wall time, a structured `Log::warning('perf.work_order_create_slow', …)` is emitted (see `config/observability.php`).

## When to revisit

- Very high sustained **create** QPS for a **single** company: consider sharded sequence service or deferred number assignment (only if product accepts gaps/out-of-band assignment — **not** the current contract).
- Watch queue `failed_jobs` clusters: simultaneous `MaxAttemptsExceeded` across queues often indicates **infrastructure** interruptions (worker restarts, Redis/DB blips), not this allocator.

## Long soak verification note (closure)

On a **~35 minute** k6 mixed run (`LOAD_LEVEL=7`, 30 VU, `BASE_URL=http://nginx` on the compose network), `failed_jobs` rose only **659 → 661** (+2). That delta did **not** correlate with API health on the exercised path: **HTTP check failure rate stayed 0%**, and **`integrity:verify` stayed PASS** before and after the run.

**Classification:** treat this as **routine operational follow-up** (occasional inspection of `failed_jobs` / queue workers), **not** a closure blocker for work-order numbering or the mixed load gate above.
