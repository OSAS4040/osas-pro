<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/health', function () {
        $checks = [];
        $healthy = true;

        try {
            \Illuminate\Support\Facades\DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'fail';
            $healthy = false;
        }

        try {
            \Illuminate\Support\Facades\Redis::ping();
            $checks['redis'] = 'ok';
        } catch (\Throwable) {
            $checks['redis'] = 'fail';
            $healthy = false;
        }

        return response()->json([
            'status'   => $healthy ? 'healthy' : 'degraded',
            'checks'   => $checks,
            'version'  => config('app.version', '1.0.0'),
            'trace_id' => app('trace_id'),
        ], $healthy ? 200 : 503);
    });

    Route::post('/auth/login',    [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login']);
    Route::post('/auth/register', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'register']);
    Route::get('/plans',          fn() => response()->json([
        'data'     => \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->get(),
        'trace_id' => app('trace_id'),
    ]));

    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {

        Route::post('/auth/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout']);
        Route::get('/auth/me',      [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me']);

        Route::get('/dashboard/summary', [\App\Http\Controllers\Api\V1\DashboardController::class, 'summary']);

        /** Phase 1 intelligence — inspection only (feature-flagged, admin). */
        Route::prefix('internal')->middleware('intelligent.internal')->group(function () {
            Route::get('/domain-events', [\App\Http\Controllers\Api\V1\Internal\DomainEventInspectionController::class, 'index']);
        });

        /** Phase 2 — read-only analytics (master + per-endpoint flags, admin). */
        Route::prefix('internal')->middleware(['intelligent.internal', 'intelligent.phase2'])->group(function () {
            Route::get('/intelligence/overview', [\App\Http\Controllers\Api\V1\Internal\Phase2IntelligenceController::class, 'overview']);
            Route::get('/intelligence/insights', [\App\Http\Controllers\Api\V1\Internal\Phase2IntelligenceController::class, 'insights']);
            Route::get('/intelligence/recommendations', [\App\Http\Controllers\Api\V1\Internal\Phase2IntelligenceController::class, 'recommendations']);
            Route::get('/intelligence/alerts', [\App\Http\Controllers\Api\V1\Internal\Phase2IntelligenceController::class, 'alerts']);
            Route::get('/intelligence/command-center', [\App\Http\Controllers\Api\V1\Internal\Phase2IntelligenceController::class, 'commandCenter']);
            Route::post('/intelligence/command-center/governance', [\App\Http\Controllers\Api\V1\Internal\CommandCenterGovernanceController::class, 'store']);
            Route::get('/intelligence/command-center/governance/history', [\App\Http\Controllers\Api\V1\Internal\CommandCenterGovernanceController::class, 'history']);
        });

        Route::apiResource('companies', \App\Http\Controllers\Api\V1\CompanyController::class);
        Route::post('/companies/{id}/logo',           [\App\Http\Controllers\Api\V1\CompanyController::class, 'uploadLogo']);
        Route::post('/companies/{id}/signature',      [\App\Http\Controllers\Api\V1\CompanyController::class, 'uploadSignature']);
        Route::delete('/companies/{id}/signature',    [\App\Http\Controllers\Api\V1\CompanyController::class, 'deleteSignature']);
        Route::post('/companies/{id}/stamp',          [\App\Http\Controllers\Api\V1\CompanyController::class, 'uploadStamp']);
        Route::delete('/companies/{id}/stamp',        [\App\Http\Controllers\Api\V1\CompanyController::class, 'deleteStamp']);
        Route::get('/companies/{id}/settings',        [\App\Http\Controllers\Api\V1\CompanyController::class, 'getSettings']);
        Route::patch('/companies/{id}/settings',      [\App\Http\Controllers\Api\V1\CompanyController::class, 'updateSettings']);
        Route::apiResource('branches',   \App\Http\Controllers\Api\V1\BranchController::class);
        Route::apiResource('users',      \App\Http\Controllers\Api\V1\UserController::class);

        // ── Notifications / Sharing ──
        Route::post('/notifications/share-email', [\App\Http\Controllers\Api\V1\NotificationController::class, 'shareEmail']);
        Route::post('/notifications/track-share', [\App\Http\Controllers\Api\V1\NotificationController::class, 'trackShare']);

        Route::get('/roles/{id}/assign',  [\App\Http\Controllers\Api\V1\RoleController::class, 'assign']);
        Route::post('/roles/{id}/assign', [\App\Http\Controllers\Api\V1\RoleController::class, 'assign']);
        Route::apiResource('roles',       \App\Http\Controllers\Api\V1\RoleController::class);

        Route::get('/permissions',     [\App\Http\Controllers\Api\V1\PermissionController::class, 'index']);
        Route::get('/permissions/my',  [\App\Http\Controllers\Api\V1\PermissionController::class, 'my']);

        Route::prefix('subscriptions')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'index']);
            Route::get('/current', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'current']);
            Route::post('/renew',  [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'renew']);
        });

        Route::apiResource('customers', \App\Http\Controllers\Api\V1\CustomerController::class);
        Route::apiResource('vehicles',  \App\Http\Controllers\Api\V1\VehicleController::class);
        Route::get('vehicles/{id}/digital-card', [\App\Http\Controllers\Api\V1\VehicleController::class, 'digitalCard']);
        Route::apiResource('services',  \App\Http\Controllers\Api\V1\ServiceController::class);
        Route::apiResource('bundles',   \App\Http\Controllers\Api\V1\BundleController::class);

        Route::prefix('work-orders')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'index']);
            Route::post('/',             [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'store']);
            Route::get('/{id}',          [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'show']);
            Route::put('/{id}',          [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'update']);
            Route::patch('/{id}/status', [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'updateStatus']);
            Route::delete('/{id}',       [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'destroy']);
        });

        Route::middleware('idempotent')->group(function () {
            Route::post('/invoices',      [\App\Http\Controllers\Api\V1\InvoiceController::class, 'store']);
            Route::post('/invoices/{id}/pay', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'pay']);
            Route::post('/pos/sale',      [\App\Http\Controllers\Api\V1\POSController::class, 'sale']);
            Route::post('/invoices/from-work-order/{workOrderId}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'fromWorkOrder']);
        });

        Route::apiResource('invoices', \App\Http\Controllers\Api\V1\InvoiceController::class)->except(['store']);
        Route::post('/invoices/{id}/media', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'uploadMedia']);

        Route::prefix('wallet')->group(function () {
            Route::get('/',                           [\App\Http\Controllers\Api\V1\WalletController::class, 'show']);
            Route::get('/transactions',               [\App\Http\Controllers\Api\V1\WalletController::class, 'transactions']);
            Route::middleware('idempotent')->group(function () {
                Route::post('/top-up',              [\App\Http\Controllers\Api\V1\WalletController::class, 'topUp']);
                Route::post('/transfer',             [\App\Http\Controllers\Api\V1\WalletController::class, 'transfer']);
            });
            Route::post('/transactions/{id}/reverse', [\App\Http\Controllers\Api\V1\WalletController::class, 'reverse']);
        });

        Route::prefix('payments')->group(function () {
            Route::get('/invoice/{invoiceId}', [\App\Http\Controllers\Api\V1\WalletController::class, 'paymentsByInvoice']);
            Route::post('/{id}/refund',        [\App\Http\Controllers\Api\V1\WalletController::class, 'refundPayment'])->middleware('idempotent');
        });

        Route::apiResource('products',  \App\Http\Controllers\Api\V1\ProductController::class);
        Route::apiResource('quotes',    \App\Http\Controllers\Api\V1\QuoteController::class);
        Route::get('/nps',              [\App\Http\Controllers\Api\V1\NpsController::class, 'index']);
        Route::post('/nps',             [\App\Http\Controllers\Api\V1\NpsController::class, 'store']);
        Route::get('/warranty-items',   fn(\Illuminate\Http\Request $r) => response()->json(['data' => \App\Models\WarrantyItem::where('company_id', $r->user()->company_id)->orderByDesc('warranty_end')->paginate(20)]));
        Route::get('/service-reminders',fn(\Illuminate\Http\Request $r) => response()->json(['data' => \App\Models\ServiceReminder::where('company_id', $r->user()->company_id)->orderBy('next_service_date')->paginate(20)]));

        Route::prefix('units')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Api\V1\UnitController::class, 'index']);
            Route::post('/',               [\App\Http\Controllers\Api\V1\UnitController::class, 'store']);
            Route::put('/{id}',            [\App\Http\Controllers\Api\V1\UnitController::class, 'update']);
            Route::delete('/{id}',         [\App\Http\Controllers\Api\V1\UnitController::class, 'destroy']);
            Route::get('/conversions',     [\App\Http\Controllers\Api\V1\UnitController::class, 'conversions']);
            Route::post('/conversions',    [\App\Http\Controllers\Api\V1\UnitController::class, 'storeConversion']);
        });

        Route::apiResource('suppliers', \App\Http\Controllers\Api\V1\SupplierController::class);

        Route::prefix('purchases')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Api\V1\PurchaseController::class, 'index']);
            Route::post('/',                   [\App\Http\Controllers\Api\V1\PurchaseController::class, 'store']);
            Route::get('/{id}',                [\App\Http\Controllers\Api\V1\PurchaseController::class, 'show']);
            Route::patch('/{id}/status',       [\App\Http\Controllers\Api\V1\PurchaseController::class, 'updateStatus']);
            Route::post('/{id}/receive',       [\App\Http\Controllers\Api\V1\PurchaseController::class, 'receive']);
            Route::get('/{id}/receipts',       [\App\Http\Controllers\Api\V1\GoodsReceiptController::class, 'byPurchase']);
            Route::post('/{id}/receipts',      [\App\Http\Controllers\Api\V1\GoodsReceiptController::class, 'store']);
        });

        Route::prefix('goods-receipts')->group(function () {
            Route::get('/',     [\App\Http\Controllers\Api\V1\GoodsReceiptController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\GoodsReceiptController::class, 'show']);
        });

        Route::prefix('inventory')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Api\V1\InventoryController::class, 'index']);
            Route::get('/movements',  [\App\Http\Controllers\Api\V1\InventoryController::class, 'movements']);
            Route::post('/adjust', [\App\Http\Controllers\Api\V1\InventoryController::class, 'adjust'])->middleware('idempotent');
            Route::get('/{id}',    [\App\Http\Controllers\Api\V1\InventoryController::class, 'show']);

            Route::prefix('reservations')->group(function () {
                Route::get('/',                  [\App\Http\Controllers\Api\V1\InventoryController::class, 'reservations']);
                Route::post('/',                 [\App\Http\Controllers\Api\V1\InventoryController::class, 'createReservation']);
                Route::patch('/{id}/consume',    [\App\Http\Controllers\Api\V1\InventoryController::class, 'consumeReservation']);
                Route::patch('/{id}/release',    [\App\Http\Controllers\Api\V1\InventoryController::class, 'releaseReservation']);
                Route::patch('/{id}/cancel',     [\App\Http\Controllers\Api\V1\InventoryController::class, 'cancelReservation']);
            });
        });

        Route::prefix('wallets')->group(function () {
            Route::get('/{customerId}/summary',          [\App\Http\Controllers\Api\V1\WalletController::class, 'summary']);
            Route::get('/{walletId}/transactions',       [\App\Http\Controllers\Api\V1\WalletController::class, 'transactions']);
            Route::post('/top-up/individual',            [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpIndividual'])->middleware('idempotent');
            Route::post('/top-up/fleet',                 [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpFleet'])->middleware('idempotent');
            Route::post('/transfer',                     [\App\Http\Controllers\Api\V1\WalletController::class, 'transfer'])->middleware('idempotent');
            Route::post('/reversal',                     [\App\Http\Controllers\Api\V1\WalletController::class, 'reverse'])->middleware('idempotent');
        });

        Route::prefix('api-keys')->middleware('permission:api_keys.manage')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'index']);
            Route::post('/',       [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'store']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'revoke']);
        });

        Route::prefix('webhooks')->middleware('permission:webhooks.manage')->group(function () {
            Route::get('/',                 [\App\Http\Controllers\Api\V1\WebhookController::class, 'index']);
            Route::post('/',                [\App\Http\Controllers\Api\V1\WebhookController::class, 'store']);
            Route::delete('/{id}',          [\App\Http\Controllers\Api\V1\WebhookController::class, 'destroy']);
            Route::get('/{id}/deliveries',  [\App\Http\Controllers\Api\V1\WebhookController::class, 'deliveries']);
        });

        Route::get('/api-usage-logs', function (\Illuminate\Http\Request $request) {
            $logs = \App\Models\ApiUsageLog::where('company_id', $request->user()->company_id)
                ->when($request->api_key_id, fn($q) => $q->where('api_key_id', $request->api_key_id))
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 50));
            return response()->json(['data' => $logs, 'trace_id' => app('trace_id')]);
        });

        // Alias for frontend dashboard
        Route::get('/dashboard/kpi', [\App\Http\Controllers\Api\V1\ReportController::class, 'kpi']);

        Route::prefix('reports')->middleware('permission:reports.view')->group(function () {
            Route::get('/sales',                [\App\Http\Controllers\Api\V1\ReportController::class, 'sales']);
            Route::get('/sales-by-customer',    [\App\Http\Controllers\Api\V1\ReportController::class, 'salesByCustomer']);
            Route::get('/sales-by-product',     [\App\Http\Controllers\Api\V1\ReportController::class, 'salesByProduct']);
            Route::get('/overdue-receivables',  [\App\Http\Controllers\Api\V1\ReportController::class, 'overdueReceivables']);
            Route::get('/work-orders',          [\App\Http\Controllers\Api\V1\ReportController::class, 'workOrders']);
            Route::get('/kpi',                  [\App\Http\Controllers\Api\V1\ReportController::class, 'kpi']);
            Route::get('/vat',                  [\App\Http\Controllers\Api\V1\ReportController::class, 'vatReport']);
            Route::get('/inventory',            [\App\Http\Controllers\Api\V1\ReportController::class, 'inventory']);
            Route::get('/financial',            [\App\Http\Controllers\Api\V1\ReportController::class, 'financial']);
        });

        // Financial Core — Ledger & Chart of Accounts
        Route::prefix('ledger')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Api\V1\LedgerController::class, 'index']);
            Route::get('/trial-balance',  [\App\Http\Controllers\Api\V1\LedgerController::class, 'trialBalance']);
            Route::get('/{id}',           [\App\Http\Controllers\Api\V1\LedgerController::class, 'show']);
            Route::post('/{id}/reverse',  [\App\Http\Controllers\Api\V1\LedgerController::class, 'reverse']);
        });

        Route::apiResource('chart-of-accounts', \App\Http\Controllers\Api\V1\ChartOfAccountController::class);

        // Fleet Wallet — read-only
        Route::get('/wallet/{customerId}/summary',    [\App\Http\Controllers\Api\V1\WalletController::class, 'summary']);
        Route::get('/wallet/{walletId}/transactions', [\App\Http\Controllers\Api\V1\WalletController::class, 'transactions']);

        // Fleet Wallet — write (idempotent)
        Route::middleware('idempotent')->group(function () {
            Route::post('/wallet/top-up/individual', [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpIndividual']);
            Route::post('/wallet/top-up/fleet',      [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpFleet']);
            Route::post('/wallet/transfer',          [\App\Http\Controllers\Api\V1\WalletController::class, 'transfer']);
            Route::post('/wallet/reversal',          [\App\Http\Controllers\Api\V1\WalletController::class, 'reverse']);
        });

        // Fleet Operations — Workshop Side (verify-plate only)
        Route::prefix('fleet')->group(function () {
            Route::get('/customers',                          [\App\Http\Controllers\Api\V1\FleetController::class, 'fleetCustomers']);
            Route::post('/verify-plate',                      [\App\Http\Controllers\Api\V1\FleetController::class, 'verifyPlate']);
            // يتم الاعتماد الآن من Fleet Portal — هذا المسار للورشة فقط (طوارئ)
            Route::post('/work-orders/{id}/approve',          [\App\Http\Controllers\Api\V1\FleetController::class, 'approveWorkOrder']);
        });

        // Fleet Portal — Customer Side (fleet_contact / fleet_manager only)
        Route::prefix('fleet-portal')->group(function () {
            Route::get('/dashboard',                          [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'dashboard']);
            Route::get('/vehicles',                           [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'vehicles']);
            Route::get('/wallet/summary',                     [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'walletSummary']);
            Route::get('/wallet/transactions',                [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'transactions']);
            Route::post('/wallet/top-up',                     [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'topUp'])->middleware('idempotent');
            Route::get('/work-orders',                        [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'pendingApproval']);
            Route::post('/work-orders',                       [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'createWorkOrder']);
            Route::get('/work-orders/pending-approval',       [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'pendingApproval']);
            Route::get('/wallet',                             [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'walletSummary']);
            Route::post('/work-orders/{id}/approve-credit',   [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'approveCredit']);
            Route::post('/work-orders/{id}/reject-credit',    [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'rejectCredit']);
        });
    });

    // ── SaaS Plans (Phase 7) ─────────────────────────────────────────
    Route::get('/plans', [\App\Http\Controllers\Api\V1\SaasController::class, 'listPlans']);
    Route::post('/plans/seed', [\App\Http\Controllers\Api\V1\SaasController::class, 'seedPlans']);
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection'])->group(function () {
        Route::get('/subscription',           [\App\Http\Controllers\Api\V1\SaasController::class, 'currentSubscription']);
        Route::post('/subscription/change',   [\App\Http\Controllers\Api\V1\SaasController::class, 'changePlan']);
        Route::get('/subscription/usage',     [\App\Http\Controllers\Api\V1\SaasController::class, 'usageLimits']);
    });

    // ── Bays & Bookings (Phase 6) ────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription'])->group(function () {
        Route::get('/bays',                      [\App\Http\Controllers\Api\V1\BayController::class, 'index']);
        Route::post('/bays',                     [\App\Http\Controllers\Api\V1\BayController::class, 'store']);
        Route::patch('/bays/{id}/status',        [\App\Http\Controllers\Api\V1\BayController::class, 'updateStatus']);
        Route::get('/bookings',                  [\App\Http\Controllers\Api\V1\BayController::class, 'listBookings']);
        Route::post('/bookings',                 [\App\Http\Controllers\Api\V1\BayController::class, 'storeBooking']);
        Route::patch('/bookings/{id}',           [\App\Http\Controllers\Api\V1\BayController::class, 'updateBooking']);
        Route::post('/bookings/availability',    [\App\Http\Controllers\Api\V1\BayController::class, 'checkAvailability']);
        Route::get('/bays/heatmap',              [\App\Http\Controllers\Api\V1\BayController::class, 'heatmap']);
    });

    // ── Workshop Operations (Phase 5) ────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription'])->prefix('workshop')->group(function () {
        // Employees
        Route::get('/employees',              [\App\Http\Controllers\Api\V1\WorkshopController::class, 'listEmployees']);
        Route::post('/employees',             [\App\Http\Controllers\Api\V1\WorkshopController::class, 'storeEmployee']);
        Route::get('/employees/{id}',         [\App\Http\Controllers\Api\V1\WorkshopController::class, 'showEmployee']);
        Route::put('/employees/{id}',         [\App\Http\Controllers\Api\V1\WorkshopController::class, 'updateEmployee']);
        // Attendance
        Route::post('/attendance/check-in',              [\App\Http\Controllers\Api\V1\WorkshopController::class, 'checkIn']);
        Route::post('/attendance/check-out',             [\App\Http\Controllers\Api\V1\WorkshopController::class, 'checkOut']);
        Route::get('/attendance/today',                  [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceTodayAll']);
        Route::get('/attendance/{employeeId}/today',     [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceToday']);
        Route::get('/attendance/{employeeId}/month',     [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceMonth']);
        // Tasks
        Route::get('/tasks',                  [\App\Http\Controllers\Api\V1\WorkshopController::class, 'listTasks']);
        Route::post('/tasks',                 [\App\Http\Controllers\Api\V1\WorkshopController::class, 'storeTask']);
        Route::patch('/tasks/{id}/status',    [\App\Http\Controllers\Api\V1\WorkshopController::class, 'updateTaskStatus']);
        Route::get('/tasks/stats',            [\App\Http\Controllers\Api\V1\WorkshopController::class, 'taskStats']);
        // Commissions
        Route::get('/commissions',            [\App\Http\Controllers\Api\V1\WorkshopController::class, 'listCommissions']);
        Route::post('/commission-rules',      [\App\Http\Controllers\Api\V1\WorkshopController::class, 'storeCommissionRule']);
        Route::post('/commissions/{id}/pay',  [\App\Http\Controllers\Api\V1\WorkshopController::class, 'payCommission']);
    });

    // ── Governance (Phase 4) ──────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription'])->prefix('governance')->group(function () {
        // Policy Rules
        Route::get('/policies',          [\App\Http\Controllers\Api\V1\GovernanceController::class, 'listPolicies']);
        Route::post('/policies',         [\App\Http\Controllers\Api\V1\GovernanceController::class, 'storePolicy']);
        Route::delete('/policies/{id}',  [\App\Http\Controllers\Api\V1\GovernanceController::class, 'deletePolicy']);
        Route::post('/policies/evaluate',[\App\Http\Controllers\Api\V1\GovernanceController::class, 'evaluatePolicy']);

        // Approval Workflows
        Route::get('/workflows',                    [\App\Http\Controllers\Api\V1\GovernanceController::class, 'listWorkflows']);
        Route::post('/workflows/{id}/approve',      [\App\Http\Controllers\Api\V1\GovernanceController::class, 'approveWorkflow']);
        Route::post('/workflows/{id}/reject',       [\App\Http\Controllers\Api\V1\GovernanceController::class, 'rejectWorkflow']);

        // Audit Logs
        Route::get('/audit-logs',                   [\App\Http\Controllers\Api\V1\GovernanceController::class, 'auditLogs']);

        // Alert Rules & Notifications
        Route::get('/alert-rules',                  [\App\Http\Controllers\Api\V1\GovernanceController::class, 'listAlertRules']);
        Route::post('/alert-rules',                 [\App\Http\Controllers\Api\V1\GovernanceController::class, 'storeAlertRule']);
        Route::get('/alerts/me',                    [\App\Http\Controllers\Api\V1\GovernanceController::class, 'myAlerts']);
        Route::post('/alerts/mark-read',            [\App\Http\Controllers\Api\V1\GovernanceController::class, 'markAlertsRead']);

        // Contracts
        Route::apiResource('contracts', \App\Http\Controllers\Api\V1\ContractController::class);
        Route::post('/contracts/{contract}/upload-document', [\App\Http\Controllers\Api\V1\ContractController::class, 'uploadDocument']);
        Route::post('/contracts/{contract}/send-for-signature', [\App\Http\Controllers\Api\V1\ContractController::class, 'sendForSignature']);
        Route::get('/contracts-expiring', [\App\Http\Controllers\Api\V1\ContractController::class, 'expiringContracts']);

        // Excel Import
        Route::post('/products/import',  [\App\Http\Controllers\Api\V1\ImportController::class, 'importProducts']);
        Route::post('/vehicles/import',  [\App\Http\Controllers\Api\V1\ImportController::class, 'importVehicles']);
        Route::get('/products/template', [\App\Http\Controllers\Api\V1\ImportController::class, 'productsTemplate']);
        Route::get('/vehicles/template', [\App\Http\Controllers\Api\V1\ImportController::class, 'vehiclesTemplate']);

        // Fuel Management
        Route::prefix('fuel')->group(function () {
            Route::get('/',         [\App\Http\Controllers\Api\V1\FuelController::class, 'index']);
            Route::post('/',        [\App\Http\Controllers\Api\V1\FuelController::class, 'store'])->middleware('idempotent');
            Route::delete('/{id}',  [\App\Http\Controllers\Api\V1\FuelController::class, 'destroy']);
            Route::get('/stats',    [\App\Http\Controllers\Api\V1\FuelController::class, 'stats']);
        });

        // Vehicle Settings & Documents
        Route::get('/vehicles/{id}/settings',              [\App\Http\Controllers\Api\V1\FuelController::class, 'getSettings']);
        Route::put('/vehicles/{id}/settings',              [\App\Http\Controllers\Api\V1\FuelController::class, 'saveSettings']);
        Route::get('/vehicles/{id}/documents',             [\App\Http\Controllers\Api\V1\FuelController::class, 'getDocuments']);
        Route::post('/vehicles/{id}/documents',            [\App\Http\Controllers\Api\V1\FuelController::class, 'uploadDocument']);
        Route::delete('/vehicles/{vehicleId}/documents/{docId}', [\App\Http\Controllers\Api\V1\FuelController::class, 'deleteDocument']);

        // Bulk Import (Excel / CSV)
        Route::post('/vehicles/import',  [\App\Http\Controllers\Api\V1\ImportController::class, 'importVehicles']);
        Route::post('/employees/import', [\App\Http\Controllers\Api\V1\ImportController::class, 'importEmployees']);

        // OCR
        Route::post('/ocr/plate',   [\App\Http\Controllers\Api\V1\OcrController::class, 'scanPlate']);
        Route::post('/ocr/invoice', [\App\Http\Controllers\Api\V1\OcrController::class, 'scanInvoice']);

        // HR — Leaves
        Route::get('/leaves',                           [\App\Http\Controllers\Api\V1\LeaveController::class, 'index']);
        Route::post('/leaves',                          [\App\Http\Controllers\Api\V1\LeaveController::class, 'store']);
        Route::post('/leaves/{id}/approve',             [\App\Http\Controllers\Api\V1\LeaveController::class, 'approve']);
        Route::post('/leaves/{id}/reject',              [\App\Http\Controllers\Api\V1\LeaveController::class, 'reject']);
        Route::delete('/leaves/{id}',                   [\App\Http\Controllers\Api\V1\LeaveController::class, 'destroy']);

        // HR — Salaries
        Route::get('/salaries',                         [\App\Http\Controllers\Api\V1\SalaryController::class, 'index']);
        Route::post('/salaries',                        [\App\Http\Controllers\Api\V1\SalaryController::class, 'store']);
        Route::post('/salaries/{id}/approve',           [\App\Http\Controllers\Api\V1\SalaryController::class, 'approve']);
        Route::post('/salaries/{id}/pay',               [\App\Http\Controllers\Api\V1\SalaryController::class, 'pay']);
        Route::get('/salaries/summary',                 [\App\Http\Controllers\Api\V1\SalaryController::class, 'summary']);

        // Referral & Loyalty
        Route::get('/referrals',                        [\App\Http\Controllers\Api\V1\ReferralController::class, 'index']);
        Route::post('/referrals/generate',              [\App\Http\Controllers\Api\V1\ReferralController::class, 'generate']);
        Route::get('/referrals/policy',                 [\App\Http\Controllers\Api\V1\ReferralController::class, 'getPolicy']);
        Route::put('/referrals/policy',                 [\App\Http\Controllers\Api\V1\ReferralController::class, 'savePolicy']);
        Route::get('/loyalty/customer/{id}',            [\App\Http\Controllers\Api\V1\ReferralController::class, 'customerPoints']);
        Route::post('/loyalty/redeem',                  [\App\Http\Controllers\Api\V1\ReferralController::class, 'redeemPoints']);
        Route::get('/loyalty/leaderboard',              [\App\Http\Controllers\Api\V1\ReferralController::class, 'leaderboard']);
    });

    // ─── SUPPORT SYSTEM ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection'])->prefix('support')->group(function () {
        // Tickets
        Route::get('/tickets',                              [\App\Http\Controllers\Api\V1\SupportController::class, 'indexTickets']);
        Route::post('/tickets',                             [\App\Http\Controllers\Api\V1\SupportController::class, 'storeTicket']);
        Route::get('/tickets/{id}',                         [\App\Http\Controllers\Api\V1\SupportController::class, 'showTicket']);
        Route::put('/tickets/{id}',                         [\App\Http\Controllers\Api\V1\SupportController::class, 'updateTicket']);
        Route::patch('/tickets/{id}/status',                [\App\Http\Controllers\Api\V1\SupportController::class, 'changeStatus']);
        Route::post('/tickets/{id}/replies',                [\App\Http\Controllers\Api\V1\SupportController::class, 'storeReply']);
        Route::post('/tickets/{id}/rate',                   [\App\Http\Controllers\Api\V1\SupportController::class, 'rateSatisfaction']);
        // Stats & SLA
        Route::get('/stats',                                [\App\Http\Controllers\Api\V1\SupportController::class, 'stats']);
        Route::get('/sla-policies',                         [\App\Http\Controllers\Api\V1\SupportController::class, 'indexSla']);
        Route::post('/sla-policies',                        [\App\Http\Controllers\Api\V1\SupportController::class, 'storeSla']);
        Route::put('/sla-policies/{id}',                    [\App\Http\Controllers\Api\V1\SupportController::class, 'updateSla']);
        Route::post('/sla/check-breaches',                  [\App\Http\Controllers\Api\V1\SupportController::class, 'checkSlaBreaches']);
        // Knowledge Base
        Route::get('/kb',                                   [\App\Http\Controllers\Api\V1\SupportController::class, 'indexKb']);
        Route::post('/kb',                                  [\App\Http\Controllers\Api\V1\SupportController::class, 'storeKb']);
        Route::put('/kb/{id}',                              [\App\Http\Controllers\Api\V1\SupportController::class, 'updateKb']);
        Route::post('/kb/{id}/vote',                        [\App\Http\Controllers\Api\V1\SupportController::class, 'voteKb']);
        Route::get('/kb/search',                            [\App\Http\Controllers\Api\V1\SupportController::class, 'searchKb']);
        Route::get('/kb-categories',                        [\App\Http\Controllers\Api\V1\SupportController::class, 'indexKbCategories']);
        Route::post('/kb-categories',                       [\App\Http\Controllers\Api\V1\SupportController::class, 'storeKbCategory']);
    });

    // ── ZATCA ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription'])->prefix('zatca')->group(function () {
        Route::get('/status',  [\App\Http\Controllers\Api\V1\ZatcaController::class, 'status']);
        Route::get('/logs',    [\App\Http\Controllers\Api\V1\ZatcaController::class, 'logs']);
        Route::post('/submit', [\App\Http\Controllers\Api\V1\ZatcaController::class, 'submit']);
    });

    // ── Notifications ──────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection'])->prefix('notifications')->group(function () {
        Route::get('/',         [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
        Route::put('/{id}/read',[\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead']);
        Route::put('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllRead']);
    });

    // ── Customer Portal ────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection'])->prefix('customer-portal')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'dashboard']);
    });

    Route::middleware(['auth.apikey', 'api.log', 'financial.protection'])->group(function () {
        Route::prefix('external/v1')->group(function () {
            Route::post('/invoices',       [\App\Http\Controllers\Api\V1\External\ExternalInvoiceController::class, 'store'])->middleware('idempotent');
            Route::get('/invoices/{uuid}', [\App\Http\Controllers\Api\V1\External\ExternalInvoiceController::class, 'show']);
        });
    });

    // ── AI Plugins Marketplace ─────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription'])->prefix('plugins')->group(function () {
        Route::get('/',                 [\App\Http\Controllers\Api\V1\PluginController::class, 'index']);
        Route::get('/tenant',           [\App\Http\Controllers\Api\V1\PluginController::class, 'tenantPlugins']);
        Route::get('/{key}',            [\App\Http\Controllers\Api\V1\PluginController::class, 'show']);
        Route::post('/{key}/install',   [\App\Http\Controllers\Api\V1\PluginController::class, 'install']);
        Route::delete('/{key}/uninstall', [\App\Http\Controllers\Api\V1\PluginController::class, 'uninstall']);
        Route::put('/{key}/configure',  [\App\Http\Controllers\Api\V1\PluginController::class, 'configure']);
        Route::post('/{key}/execute',   [\App\Http\Controllers\Api\V1\PluginController::class, 'execute']);
    });
});
