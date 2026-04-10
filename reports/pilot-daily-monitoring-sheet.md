# Pilot Daily Monitoring Sheet (14 Days)

Use this sheet daily during pilot execution.

---

## Day 1

### Active Clients

- Client A: `Demo Auto Center (owner@demo.sa)` — active
- Client B: `Not started`
- Client C: `Not started`

### Operations Executed Today

| Operation | Count |
|---|---:|
| Login | 15 |
| Customers created | 1 (pilot flow) |
| Vehicles created | 1 (pilot flow) |
| Work Orders | 3 (create + status updates in flow) |
| Invoices | 0 (blocking at issue step) |
| Payments | 0 (dependent on invoice issue) |
| Inventory actions | 0 (validation blocked) |

### Issues

#### P0 (Blocking) 🔴

- Problem: `Issue invoice from work order failed (422)`
- Impact: `Pilot flow cannot complete billing stage`
- Fix: `Pending P0 fix (blocking flow completion)`
- Status: `Open`

- Problem: `Execute payment failed (422)`
- Impact: `Payment stage not reachable due to upstream invoice block`
- Fix: `Blocked by invoice issue fix`
- Status: `Open`

- Problem: `Final completion check failed`
- Impact: `Client A flow end-to-end = incomplete`
- Fix: `Repeat flow after P0 fixes only`
- Status: `Open`

#### P1 (Severe) 🟡

- Problem: `Inventory action failed (422)`
- Impact: `Inventory part of flow not validated in this run`
- Decision: `Fix in nearest window after P0`

#### P2/P3 (Deferred) ⚪

- Problem: `10 inventory simulation operations returned 422`
- Notes: `Deferred until blocking path is stabilized`

### Performance

- p95 latency: `584 ms`
- errors (5xx): `0`
- queue delay: `Not directly sampled (workers running)`
- DB status: `Healthy (active_connections=25, failed_jobs=177)`

### UX Feedback (Most Important)

- Where did users stop? `At invoice issuance from work order (blocking 422).`
- Where did users need explanation? `Flow continuity from completed work order -> invoice -> payment.`
- What was unclear? `Operational next-step when invoice issue fails during pilot run.`
- What was slow? `No severe latency issue; p95 remained within light-load acceptable range.`

### Behavior Validation

- Vertical changed behavior? `❌ (not observed in this run)`
- `behavior_applied` visible? `❌ (not observed in responses)`

### Daily Decision

- ✅ Continue as-is
- 🔧 Fix P0
- 🔧 Fix P1
- ⏳ Defer the rest

Applied today:

- 🔧 Fix P0: `Required`
- 🔧 Fix P1: `Required after P0`
- ⏳ Defer the rest: `Yes`

---

## Daily Focus (Top 3)

1. Did the client complete the task?
2. Where did the client stop?
3. Is the system stable?

## Hard Constraints

- ❌ No new features
- ❌ No broad system improvements
- ✅ Run, monitor, and fix only blockers

