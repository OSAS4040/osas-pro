# Institutional Capabilities Execution Log

## Batch-1 (Unified Approval Engine foundation)

Date: 2026-04-01
Scope: Shared multi-level approval layer for high-sensitivity flows only (`governance`, `leaves`, `salaries`, `fleet/work-order approvals`).

### Implemented

- Wave-3 sign-off circulation prerequisite file added:
  - `reports/wave3-signoff-final-for-circulation.md`
- Approval engine schema expansion:
  - `backend/database/migrations/2026_04_01_106000_expand_approval_engine_for_shared_capabilities.php`
  - Added to `approval_workflows`:
    - `current_step`, `total_steps`, `acted_at`, `trace_id`
  - Added `approval_workflow_actions` table (immutable action trail):
    - `old_status`, `new_status`, `approval_step`, `acted_by`, `approval_note`, `acted_at`, `trace_id`
- Shared service enhancement:
  - `backend/app/Services/ApprovalWorkflowService.php`
  - Added:
    - `ensurePendingWorkflow(...)`
    - `transitionBySubject(...)`
  - Extended transition handling with strict status map and action logging.
- High-sensitivity route integrations:
  - `backend/app/Http/Controllers/Api/V1/GovernanceController.php` (already wired through service; now benefits from unified action trail/steps/trace fields)
  - `backend/app/Http/Controllers/Api/V1/LeaveController.php`
  - `backend/app/Http/Controllers/Api/V1/SalaryController.php`
  - `backend/app/Http/Controllers/Api/V1/FleetController.php`

### States and fields covered

- States:
  - `pending`, `approved`, `rejected`, `cancelled`
- Shared metadata:
  - `approver` (`acted_by`)
  - `approval_step`
  - `approval_note`
  - `acted_at`
  - `trace_id`

### Tests (Batch-1)

- Added:
  - `backend/tests/Feature/Approvals/UnifiedApprovalEngineBatch1Test.php`
- Coverage:
  - approve path
  - reject path
  - invalid transition
  - unauthorized action
  - audit trail persistence

### Measurement artifacts (Batch-1)

- `backend/reports/institutional-capabilities/approval-engine.batch1.before.json`
- `backend/reports/institutional-capabilities/approval-engine.batch1.after.json`

## Batch-2 (Meetings MVP low-risk)

Date: 2026-04-01
Scope: Meetings MVP only (no video/calendar/recording/AI/dashboard expansion).

### Implemented

- Added Meetings MVP schema:
  - `backend/database/migrations/2026_04_01_107000_create_meetings_mvp_tables.php`
  - Tables:
    - `meetings`
    - `meeting_participants`
    - `meeting_minutes`
    - `meeting_decisions`
    - `meeting_actions`
    - `meeting_attachments`
- Added API controller:
  - `backend/app/Http/Controllers/Api/V1/MeetingController.php`
  - Operations:
    - create meeting
    - update meeting
    - add/remove participants
    - add minutes
    - add decisions
    - add actions
    - close meeting
  - States:
    - `draft`, `scheduled`, `in_progress`, `closed`, `cancelled`
  - Limited linking:
    - `governance_item`, `work_order`, `support_ticket`, `employee`
- Added explicit permissions and routing:
  - `backend/config/permissions.php`
  - `backend/routes/api.php`
  - Permissions:
    - `meetings.create`
    - `meetings.update`
    - `meetings.close`
    - `meetings.view_minutes`
    - `meetings.manage_actions`

### Tests (Batch-2)

- Added:
  - `backend/tests/Feature/Meetings/MeetingsMvpBatch2Test.php`
- Minimum required coverage:
  - create meeting
  - invalid transition
  - add decision/action
  - unauthorized action
  - close meeting
  - basic audit trail

### Measurement artifacts (Batch-2)

- `backend/reports/institutional-capabilities/meetings-mvp.batch2.before.json`
- `backend/reports/institutional-capabilities/meetings-mvp.batch2.after.json`

## Batch-3 (Meetings + Approvals + Execution bridge)

Date: 2026-04-01
Scope: Bridge decisions to approval workflows and execution actions, with bounded linking only (`governance_item`, `work_order`, `support_ticket`).

### Implemented

- Added bridge schema:
  - `backend/database/migrations/2026_04_01_108000_link_meeting_decisions_and_actions_to_approval_and_execution.php`
  - Decision fields:
    - `requires_approval`
    - `approval_workflow_id`
    - `approval_status`
  - Action fields:
    - `decision_id`
    - `owner_user_id` / `owner_employee_id`
    - `status` (`open|in_progress|done|cancelled`)
    - `due_date`
    - `closed_at`
    - `trace_id` (existing field reused)
- Extended meeting controller:
  - `backend/app/Http/Controllers/Api/V1/MeetingController.php`
  - Decision operations:
    - create decision with `requires_approval`
    - start approval workflow via unified approval engine
    - read decision approval status
    - approve/reject decision (via existing unified approval engine)
  - Action operations:
    - create action from meeting or linked decision
    - assign owner
    - update action status (guarded transitions)
    - close action
  - Added audit trail events for decision/action lifecycle with `trace_id`.
- Extended routes and protections:
  - `backend/routes/api.php`
  - Uses existing `meetings.*` plus existing `users.update` gate for approval actions.

### Tests (Batch-3)

- Added:
  - `backend/tests/Feature/Meetings/MeetingsApprovalExecutionBatch3Test.php`
- Coverage:
  - decision requires approval and links workflow
  - unauthorized approval action rejected
  - approve/reject reflected in decision approval status
  - create action + assign owner
  - invalid action transition rejected
  - close action
  - audit trail exists

### Measurement artifacts (Batch-3)

- `backend/reports/institutional-capabilities/meetings-approval-execution.batch3.before.json`
- `backend/reports/institutional-capabilities/meetings-approval-execution.batch3.after.json`

## Final Closure Gate (Institutional Phase)

Date: 2026-04-01
Scope: Closure validation only (no new batches).

### Executed closure checks

- Re-ran institutional reference test suite:
  - `docker compose exec -T app php artisan test tests/Feature/Approvals/UnifiedApprovalEngineBatch1Test.php tests/Feature/Meetings/MeetingsMvpBatch2Test.php tests/Feature/Meetings/MeetingsApprovalExecutionBatch3Test.php`
  - Result: `12 passed / 0 failed (31 assertions)`
- Generated final phase gate artifact:
  - `backend/reports/institutional-capabilities/institutional-phase-signoff-gate-final.json`
- Prepared closure/sign-off docs:
  - `reports/institutional-capabilities-closure-package.md`
  - `reports/institutional-capabilities-signoff-draft.md`
  - `reports/institutional-capabilities-signoff-final-for-circulation.md`

### Closure recommendation

- Institutional capabilities phase is closure-ready: **Go for formal sign-off**.
