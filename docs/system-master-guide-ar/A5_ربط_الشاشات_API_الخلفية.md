# ملحق A5 - مصفوفة ربط الشاشة مع API والخلفية

مستخرج آليًا من `frontend/src` و`backend/routes/api.php`.
الغرض: تمكين الدعم والتشغيل من تتبع رحلة الطلب من الشاشة حتى قيود الخلفية.

- استدعاءات واجهة مفهرسة: **61**
- مسارات خلفية مفهرسة: **412**
- روابط ناتجة: **61**

> `confidence=high`: تطابق قوي بالمسار/المنهج، `medium`: تطابق تقريبي، `unmatched`: لم يوجد ربط واضح آليًا.

| # | frontend file | method | frontend endpoint | backend route | backend middleware/permissions | confidence |
|---|---|---|---|---|---|---|
| 1 | `frontend/src/components/wallet/FleetTransferModal.vue` | `GET` | `/customers/${props.customerId}/vehicles` | `GET /customers/{id}/profile` | `'permission:customers.view' || permission:customers.view` | `medium` |
| 2 | `frontend/src/components/wallet/FleetTransferModal.vue` | `POST` | `/wallet/transfer` | `POST /wallet/transfer` | `'permission:invoices.update' || permission:invoices.update` | `high` |
| 3 | `frontend/src/components/wallet/TopUpModal.vue` | `POST` | `/wallet/top-up` | `POST /wallet/top-up` | `['idempotent', 'permission:fleet.wallet.topup'] || permission:fleet.wallet.topup` | `high` |
| 4 | `frontend/src/stores/walletStore.ts` | `GET` | `/wallet/${customerId}/summary` | `GET /wallet/{customerId}/summary` | `'permission:invoices.view' || permission:invoices.view` | `high` |
| 5 | `frontend/src/views/account/AuthSessionsView.vue` | `GET` | `/api/v1/auth/sessions` | `GET /auth/sessions` | `—` | `high` |
| 6 | `frontend/src/views/account/AuthSessionsView.vue` | `POST` | `/api/v1/auth/sessions/revoke-others` | `POST /auth/sessions/revoke-others` | `—` | `high` |
| 7 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/governance/contracts/${id}` | — | `—` | `unmatched` |
| 8 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/governance/contracts/${id}/service-items` | — | `—` | `unmatched` |
| 9 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/branches` | — | `—` | `unmatched` |
| 10 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/governance/contracts/${contractId.value}/service-items/${row.id}/usage` | — | `—` | `unmatched` |
| 11 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/services` | — | `—` | `unmatched` |
| 12 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/vehicles` | `GET /vehicles` | `—` | `high` |
| 13 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/vehicles/${id}` | `GET /vehicles/resolve-plate` | `'permission:vehicles.view' || permission:vehicles.view` | `medium` |
| 14 | `frontend/src/views/contracts/ContractCatalogView.vue` | `PUT` | `/governance/contracts/${contractId.value}/service-items/${editingId.value}` | — | `—` | `unmatched` |
| 15 | `frontend/src/views/contracts/ContractCatalogView.vue` | `POST` | `/governance/contracts/${contractId.value}/service-items` | — | `—` | `unmatched` |
| 16 | `frontend/src/views/contracts/ContractCatalogView.vue` | `GET` | `/customers` | `GET /customers` | `—` | `high` |
| 17 | `frontend/src/views/contracts/ContractCatalogView.vue` | `POST` | `/governance/contracts/${contractId.value}/service-items/match-preview` | — | `—` | `unmatched` |
| 18 | `frontend/src/views/contracts/ContractsView.vue` | `GET` | `/governance/contracts` | — | `—` | `unmatched` |
| 19 | `frontend/src/views/contracts/ContractsView.vue` | `GET` | `/governance/contracts-expiring` | — | `—` | `unmatched` |
| 20 | `frontend/src/views/contracts/ContractsView.vue` | `PUT` | `/governance/contracts/${editing.value.id}` | — | `—` | `unmatched` |
| 21 | `frontend/src/views/contracts/ContractsView.vue` | `POST` | `/governance/contracts` | — | `—` | `unmatched` |
| 22 | `frontend/src/views/contracts/ContractsView.vue` | `POST` | `/governance/contracts/${sendingContract.value.id}/send-for-signature` | — | `—` | `unmatched` |
| 23 | `frontend/src/views/governance/GovernanceView.vue` | `GET` | `/governance/policies` | — | `—` | `unmatched` |
| 24 | `frontend/src/views/governance/GovernanceView.vue` | `POST` | `/governance/policies` | — | `—` | `unmatched` |
| 25 | `frontend/src/views/governance/GovernanceView.vue` | `DELETE` | `/governance/policies/${id}` | — | `—` | `unmatched` |
| 26 | `frontend/src/views/governance/GovernanceView.vue` | `POST` | `/governance/policies/evaluate` | — | `—` | `unmatched` |
| 27 | `frontend/src/views/governance/GovernanceView.vue` | `GET` | `/governance/workflows?status=${wfStatus.value}` | — | `—` | `unmatched` |
| 28 | `frontend/src/views/governance/GovernanceView.vue` | `POST` | `/governance/workflows/${id}/${action}` | — | `—` | `unmatched` |
| 29 | `frontend/src/views/governance/GovernanceView.vue` | `GET` | `/governance/audit-logs?${params}` | — | `—` | `unmatched` |
| 30 | `frontend/src/views/governance/GovernanceView.vue` | `GET` | `/governance/alerts/me` | — | `—` | `unmatched` |
| 31 | `frontend/src/views/governance/GovernanceView.vue` | `POST` | `/governance/alerts/mark-read` | — | `—` | `unmatched` |
| 32 | `frontend/src/views/ledger/ChartOfAccountsView.vue` | `GET` | `/chart-of-accounts` | — | `—` | `unmatched` |
| 33 | `frontend/src/views/ledger/ChartOfAccountsView.vue` | `GET` | `/ledger/trial-balance` | — | `—` | `unmatched` |
| 34 | `frontend/src/views/ledger/ChartOfAccountsView.vue` | `PUT` | `/chart-of-accounts/${acc.id}` | — | `—` | `unmatched` |
| 35 | `frontend/src/views/ledger/LedgerEntryView.vue` | `GET` | `/ledger/${route.params.id}` | — | `—` | `unmatched` |
| 36 | `frontend/src/views/ledger/LedgerEntryView.vue` | `POST` | `/ledger/${entry.value.id}/reverse` | `POST /transactions/{id}/reverse` | `'permission:invoices.update' || permission:invoices.update` | `medium` |
| 37 | `frontend/src/views/ledger/LedgerView.vue` | `GET` | `/ledger` | — | `—` | `unmatched` |
| 38 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/kpi` | — | `—` | `unmatched` |
| 39 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/sales` | — | `—` | `unmatched` |
| 40 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/sales-by-customer` | — | `—` | `unmatched` |
| 41 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/sales-by-product` | — | `—` | `unmatched` |
| 42 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/overdue-receivables` | — | `—` | `unmatched` |
| 43 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/inventory` | — | `—` | `unmatched` |
| 44 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/vat` | — | `—` | `unmatched` |
| 45 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/cash-flow` | — | `—` | `unmatched` |
| 46 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/purchases` | — | `—` | `unmatched` |
| 47 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/receivables-aging` | — | `—` | `unmatched` |
| 48 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/operations` | — | `—` | `unmatched` |
| 49 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/employees` | — | `—` | `unmatched` |
| 50 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/intelligence-digest` | — | `—` | `unmatched` |
| 51 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/communications` | — | `—` | `unmatched` |
| 52 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/smart-tasks` | — | `—` | `unmatched` |
| 53 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/reports/kpi-dictionary` | — | `—` | `unmatched` |
| 54 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/branches` | — | `—` | `unmatched` |
| 55 | `frontend/src/views/reports/ReportsView.vue` | `GET` | `/suppliers` | — | `—` | `unmatched` |
| 56 | `frontend/src/views/wallet/WalletTransactionsView.vue` | `GET` | `/wallet/transactions` | `GET /wallet/transactions` | `—` | `high` |
| 57 | `frontend/src/views/workshop/TasksView.vue` | `GET` | `/workshop/tasks` | — | `—` | `unmatched` |
| 58 | `frontend/src/views/workshop/TasksView.vue` | `GET` | `/workshop/employees` | — | `—` | `unmatched` |
| 59 | `frontend/src/views/workshop/TasksView.vue` | `GET` | `/workshop/tasks/smart-summary` | — | `—` | `unmatched` |
| 60 | `frontend/src/views/workshop/TasksView.vue` | `GET` | `/workshop/tasks/suggested-assignees` | — | `—` | `unmatched` |
| 61 | `frontend/src/views/workshop/TasksView.vue` | `POST` | `/workshop/tasks` | — | `—` | `unmatched` |
