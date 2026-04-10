# Phase Next Role-Based UAT Checklist

This checklist validates the new "Smart Reports + Smart Tasks" delivery by role.

## Roles

- Owner/Admin
- Manager
- Staff

## Access Matrix

| Feature | Owner/Admin | Manager | Staff |
|---|---:|---:|---:|
| Reports: KPI/Sales/Customer/Product/VAT/Overdue/Inventory | Yes | Yes | Yes |
| Reports: Operations | Yes | Yes | Yes |
| Reports: Employees | Yes | Yes | No |
| Reports: Intelligence Digest | Yes | Yes | No |
| Workshop Tasks board | Yes | Yes | Yes |
| Smart task summary panel | Yes | Yes | Yes |
| Suggested assignees endpoint | Yes | Yes | Yes |

## UAT Scenarios

### Owner/Admin

1. Open `Reports` and verify all tabs are visible.
2. Verify `Employees` and `Intelligence` tabs load data.
3. Export CSV/Excel/PDF/JSON for each visible tab.
4. Open `Workshop > Tasks` and confirm smart summary + recommendations render.

### Manager

1. Open `Reports` and verify `Employees` and `Intelligence` are visible.
2. Validate period filters update operations + employees metrics.
3. In `Tasks`, create task and check suggested assignee chips in modal.

### Staff

1. Open `Reports` and verify `Employees` and `Intelligence` tabs are hidden.
2. Confirm operational tabs still render and export works.
3. In `Tasks`, update status flow and verify smart summary refreshes.

## Acceptance Criteria

- No route/permission errors for allowed pages.
- Hidden tabs are not selectable through query string for disallowed roles.
- Smart summary and recommendations render without breaking existing task flow.
- Build and type-check pass.
