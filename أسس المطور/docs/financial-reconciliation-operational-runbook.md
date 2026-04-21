# Financial Reconciliation Operational Runbook

Date: 2026-04-01
Scope: Daily operational handling for the three reconciliation anomaly types only.

## Roles and ownership

- Finance Operations: first-line triage, evidence collection, status updates.
- Accounting Lead: accounting validation and correction approval.
- Engineering On-Call (Backend): technical root-cause support for system-linked issues.
- Product/Operations Manager: escalation owner for unresolved critical findings.

## Required references

- Latest artifact:
  - `backend/reports/financial-reliability/reconciliation-report.json`
- Operational review APIs:
  - `GET /api/v1/financial-reconciliation/summary`
  - `GET /api/v1/financial-reconciliation/health`
  - `GET /api/v1/financial-reconciliation/findings`
  - `GET /api/v1/financial-reconciliation/findings/{id}`
- Historical store:
  - `financial_reconciliation_runs`
  - `financial_reconciliation_findings`
  - `financial_reconciliation_finding_histories`

## Incident playbooks by anomaly type

### 1) invoice_without_ledger

- Verify:
  - invoice exists and is in a billable/posted path.
  - expected ledger journal entry is absent.
- Action:
  - recreate/repost missing ledger transaction through approved accounting correction flow.
  - update finding status to `acknowledged` during active handling.
- Escalate when:
  - issue affects multiple invoices in same window.
  - repost/correction fails or data mismatch persists.
- Closure condition:
  - ledger entry exists and balances the invoice impact.
  - finding moved to `resolved` with mandatory review note describing correction reference.

### 2) unbalanced_journal_entry

- Verify:
  - journal entry totals (`debit` vs `credit`) differ beyond threshold.
  - source transaction and account mappings are identified.
- Action:
  - post correcting entry or reverse/recreate the invalid entry per accounting policy.
  - set status `acknowledged` while correction is in progress.
- Escalate when:
  - repeated imbalance pattern appears for same source flow.
  - correction requires schema/service intervention.
- Closure condition:
  - resulting journal trail is balanced and validated by accounting lead.
  - finding moved to `resolved` with mandatory note including correction reference.

### 3) anomalous_reversal_settlement

- Verify:
  - reversal/settlement linkage integrity for payment/wallet/journal references.
  - trace references are consistent across involved records.
- Action:
  - reconcile orphan/missing links and restore correct settlement chain.
  - mark as `acknowledged` while investigation is open.
- Escalate when:
  - money movement ambiguity exists.
  - cross-system integration inconsistency is detected.
- Closure condition:
  - reversal and settlement references are consistent and auditable.
  - finding moved to `resolved` with mandatory note documenting remediation path.

## Status workflow and policy

- Allowed statuses:
  - `open`, `acknowledged`, `resolved`, `false_positive`
- Note policy:
  - `resolved`: review note is required.
  - `false_positive`: review note is required.
  - `acknowledged`: note optional.
- Audit evidence per transition:
  - previous/new status, actor, timestamp, `trace_id`, note.

## Escalation and SLA guidance

- Critical health (`critical`): escalate immediately to Accounting Lead + Engineering On-Call.
- Warning health (`warning`): triage in daily finance operations cycle.
- Healthy (`healthy`): no unresolved findings, continue standard monitoring.

## Closure checklist

- Finding status aligned with evidence.
- Required note present for `resolved` / `false_positive`.
- Supporting references captured (invoice/journal/payment/trace).
- History entry exists in `financial_reconciliation_finding_histories`.
