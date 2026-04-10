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
            'version'  => (string) config('deployment.version'),
            'trace_id' => app('trace_id'),
        ], $healthy ? 200 : 503);
    });

    Route::get('/system/version', \App\Http\Controllers\Api\V1\SystemVersionController::class);

    Route::get('/public/landing-plans', [\App\Http\Controllers\Api\V1\PublicContentController::class, 'landingPlans']);
    Route::get('/public/platform-announcement-banner', [\App\Http\Controllers\Api\V1\PublicContentController::class, 'platformAnnouncementBanner']);

    Route::get('/public/vehicle-identity/{token}', [\App\Http\Controllers\Api\V1\VehicleIdentityController::class, 'publicShow'])
        ->where('token', '[a-f0-9]{64}')
        ->middleware('throttle:120,1');

    Route::post('/auth/login',    [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login']);
    Route::post('/auth/register', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'register']);
    Route::post('/auth/forgot-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'resetPassword']);

    /** Identity-only: no tenant/subscription/branch gates (Bearer must resolve). */
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout']);
        Route::get('/auth/me',      [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me']);
    });

    /**
     * QA — نفس حزمة الوسائط الإدارية: اشتراك فوري + نطاق فرع (بدون استثناء صامت).
     */
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription', 'permission:users.update'])->group(function () {
        Route::post('/internal/run-tests', [\App\Http\Controllers\Api\V1\Internal\QaValidationController::class, 'run'])->name('internal.qa.run-tests');
        Route::get('/internal/test-results', [\App\Http\Controllers\Api\V1\Internal\QaValidationController::class, 'results'])->name('internal.qa.test-results');
    });

    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {

        Route::get('/dashboard/summary', [\App\Http\Controllers\Api\V1\DashboardController::class, 'summary']);
        /** قدرات النظام — قراءة فقط، مُقيّد بالمستأجر والدور؛ يحد من إساءة الاستخدام */
        Route::get('/system/capabilities', [\App\Http\Controllers\Api\V1\SystemCapabilitiesController::class, 'index'])
            ->middleware('throttle:120,1');
        Route::get('/onboarding/setup-status', [\App\Http\Controllers\Api\V1\OnboardingController::class, 'setupStatus']);

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
        Route::post('/companies/{id}/settings/test-channel', [\App\Http\Controllers\Api\V1\CompanyController::class, 'testIntegrationChannel']);
        Route::post('/companies/{id}/pos/test-connection', [\App\Http\Controllers\Api\V1\CompanyController::class, 'testPosConnection']);
        Route::get('/companies/{id}/feature-profile', [\App\Http\Controllers\Api\V1\CompanyController::class, 'featureProfile']);
        Route::patch('/companies/{id}/feature-profile', [\App\Http\Controllers\Api\V1\CompanyController::class, 'updateFeatureProfile']);
        Route::patch('/companies/{id}/vertical-profile', [\App\Http\Controllers\Api\V1\CompanyController::class, 'assignVerticalProfile'])
            ->middleware('permission:config_profiles.manage');
        Route::get('/companies/{id}/effective-config', [\App\Http\Controllers\Api\V1\CompanyController::class, 'effectiveConfig'])
            ->middleware('permission:config_profiles.view');
        Route::apiResource('branches',   \App\Http\Controllers\Api\V1\BranchController::class);
        Route::patch('/branches/{id}/vertical-profile', [\App\Http\Controllers\Api\V1\BranchController::class, 'assignVerticalProfile'])
            ->middleware('permission:config_profiles.manage');
        Route::get('/branches/{id}/effective-config', [\App\Http\Controllers\Api\V1\BranchController::class, 'effectiveConfig'])
            ->middleware('permission:config_profiles.view');
        Route::apiResource('users',      \App\Http\Controllers\Api\V1\UserController::class);

        // ── Notifications / Sharing ──
        Route::post('/notifications/share-email', [\App\Http\Controllers\Api\V1\NotificationController::class, 'shareEmail'])
            ->middleware('permission:users.update');
        Route::post('/notifications/track-share', [\App\Http\Controllers\Api\V1\NotificationController::class, 'trackShare'])
            ->middleware('permission:users.update');

        Route::middleware('permission:users.update')->group(function () {
            Route::get('/roles/{id}/assign',  [\App\Http\Controllers\Api\V1\RoleController::class, 'assign']);
            Route::post('/roles/{id}/assign', [\App\Http\Controllers\Api\V1\RoleController::class, 'assign']);
            Route::apiResource('roles',       \App\Http\Controllers\Api\V1\RoleController::class);
            Route::get('/permissions',        [\App\Http\Controllers\Api\V1\PermissionController::class, 'index']);
            Route::get('/permissions/my',     [\App\Http\Controllers\Api\V1\PermissionController::class, 'my']);
        });

        Route::prefix('subscriptions')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'index'])
                ->middleware('permission:subscriptions.view');
            Route::get('/current', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'current'])
                ->middleware('permission:subscriptions.view');
            Route::post('/renew',  [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'renew'])
                ->middleware('permission:subscriptions.manage');
        });

        Route::post('/customers', [\App\Http\Controllers\Api\V1\CustomerController::class, 'store'])
            ->middleware('permission:customers.create');
        Route::put('/customers/{customer}', [\App\Http\Controllers\Api\V1\CustomerController::class, 'update'])
            ->middleware('permission:customers.update');
        Route::delete('/customers/{customer}', [\App\Http\Controllers\Api\V1\CustomerController::class, 'destroy'])
            ->middleware('permission:customers.delete');
        Route::apiResource('customers', \App\Http\Controllers\Api\V1\CustomerController::class)->only(['index', 'show']);

        Route::post('/vehicles', [\App\Http\Controllers\Api\V1\VehicleController::class, 'store'])
            ->middleware('permission:vehicles.create');
        Route::get('/vehicles/resolve-plate', [\App\Http\Controllers\Api\V1\VehicleController::class, 'resolvePlate'])
            ->middleware('permission:vehicles.view');
        Route::put('/vehicles/{vehicle}', [\App\Http\Controllers\Api\V1\VehicleController::class, 'update'])
            ->middleware('permission:vehicles.update');
        Route::delete('/vehicles/{vehicle}', [\App\Http\Controllers\Api\V1\VehicleController::class, 'destroy'])
            ->middleware('permission:vehicles.delete');
        Route::apiResource('vehicles',  \App\Http\Controllers\Api\V1\VehicleController::class)->only(['index', 'show']);
        Route::get('vehicles/{id}/digital-card', [\App\Http\Controllers\Api\V1\VehicleController::class, 'digitalCard']);
        Route::post('/vehicle-identity/resolve', [\App\Http\Controllers\Api\V1\VehicleIdentityController::class, 'resolve']);
        Route::post('/vehicles/{id}/identity/rotate', [\App\Http\Controllers\Api\V1\VehicleIdentityController::class, 'rotate'])
            ->middleware('permission:vehicles.update');
        Route::post('/vehicles/{id}/identity/revoke', [\App\Http\Controllers\Api\V1\VehicleIdentityController::class, 'revoke'])
            ->middleware('permission:vehicles.update');
        Route::post('/vehicles/{id}/identity/issue', [\App\Http\Controllers\Api\V1\VehicleIdentityController::class, 'issue'])
            ->middleware('permission:vehicles.update');
        Route::delete('/services/{service}', [\App\Http\Controllers\Api\V1\ServiceController::class, 'destroy'])
            ->middleware('permission:users.update');
        Route::apiResource('services',  \App\Http\Controllers\Api\V1\ServiceController::class)->except(['destroy']);
        Route::delete('/bundles/{bundle}', [\App\Http\Controllers\Api\V1\BundleController::class, 'destroy'])
            ->middleware('permission:users.update');
        Route::apiResource('bundles',   \App\Http\Controllers\Api\V1\BundleController::class)->except(['destroy']);

        Route::get('/customer-groups', [\App\Http\Controllers\Api\V1\CustomerGroupController::class, 'index'])
            ->middleware('permission:customer_groups.view');
        Route::post('/customer-groups', [\App\Http\Controllers\Api\V1\CustomerGroupController::class, 'store'])
            ->middleware('permission:customer_groups.manage');
        Route::put('/customer-groups/{id}', [\App\Http\Controllers\Api\V1\CustomerGroupController::class, 'update'])
            ->middleware('permission:customer_groups.manage');
        Route::delete('/customer-groups/{id}', [\App\Http\Controllers\Api\V1\CustomerGroupController::class, 'destroy'])
            ->middleware('permission:customer_groups.manage');

        Route::get('/service-pricing-policies', [\App\Http\Controllers\Api\V1\ServicePricingPolicyController::class, 'index'])
            ->middleware('permission:pricing_policies.view');
        Route::post('/service-pricing-policies', [\App\Http\Controllers\Api\V1\ServicePricingPolicyController::class, 'store'])
            ->middleware('permission:pricing_policies.manage');
        Route::put('/service-pricing-policies/{id}', [\App\Http\Controllers\Api\V1\ServicePricingPolicyController::class, 'update'])
            ->middleware('permission:pricing_policies.manage');
        Route::delete('/service-pricing-policies/{id}', [\App\Http\Controllers\Api\V1\ServicePricingPolicyController::class, 'destroy'])
            ->middleware('permission:pricing_policies.manage');

        Route::prefix('work-orders')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'index']);
            Route::post('/',             [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'store'])
                ->middleware('permission:work_orders.create');
            Route::post('/batches',      [\App\Http\Controllers\Api\V1\WorkOrderBatchController::class, 'store'])
                ->middleware('permission:work_orders.create');
            Route::get('/{id}/pdf', [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'downloadPdf'])
                ->middleware('permission:work_orders.view');
            Route::get('/{id}/share-links', [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'shareLinks'])
                ->middleware('permission:work_orders.view');
            Route::post('/{id}/share-email', [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'shareEmail'])
                ->middleware('permission:work_orders.view');
            Route::post('/{id}/share-whatsapp-driver', [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'shareWhatsAppDriver'])
                ->middleware('permission:work_orders.view');
            Route::get('/{id}',          [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'show']);
            Route::put('/{id}',          [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'update'])
                ->middleware('permission:work_orders.update');
            Route::patch('/{id}/status', [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'updateStatus'])
                ->middleware('permission:work_orders.update');
            Route::post('/{id}/cancellation-requests', [\App\Http\Controllers\Api\V1\WorkOrderCancellationRequestController::class, 'store'])
                ->middleware('permission:work_orders.update');
            Route::delete('/{id}',       [\App\Http\Controllers\Api\V1\WorkOrderController::class, 'destroy'])
                ->middleware('permission:work_orders.delete');
        });

        Route::post('/work-order-cancellation-requests/{id}/approve', [\App\Http\Controllers\Api\V1\WorkOrderCancellationRequestController::class, 'approve'])
            ->middleware('permission:work_orders.update');
        Route::post('/work-order-cancellation-requests/{id}/reject', [\App\Http\Controllers\Api\V1\WorkOrderCancellationRequestController::class, 'reject'])
            ->middleware('permission:work_orders.update');

        Route::post('/sensitive-operations/preview', [\App\Http\Controllers\Api\V1\SensitiveOperationPreviewController::class, 'preview'])
            ->middleware('permission:work_orders.view');

        Route::middleware('idempotent')->group(function () {
            Route::post('/invoices',      [\App\Http\Controllers\Api\V1\InvoiceController::class, 'store'])
                ->middleware('permission:invoices.create');
            Route::post('/invoices/{id}/pay', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'pay'])
                ->middleware('permission:invoices.update');
            Route::post('/pos/sale',      [\App\Http\Controllers\Api\V1\POSController::class, 'sale'])
                ->middleware('permission:invoices.create');
            Route::post('/invoices/from-work-order/{workOrderId}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'fromWorkOrder'])
                ->middleware('permission:invoices.create');
        });

        Route::get('/invoices', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'index'])
            ->middleware('permission:invoices.view');
        Route::get('/invoices/{invoice}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'show'])
            ->middleware('permission:invoices.view');
        Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'pdf'])
            ->middleware('permission:invoices.view');
        Route::put('/invoices/{invoice}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'update'])
            ->middleware('permission:invoices.update');
        Route::patch('/invoices/{invoice}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'update'])
            ->middleware('permission:invoices.update');
        Route::delete('/invoices/{invoice}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'destroy'])
            ->middleware('permission:invoices.delete');
        Route::post('/invoices/{id}/media', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'uploadMedia'])
            ->middleware('permission:invoices.update');

        /** استخراج بيانات من صورة فاتورة (OCR) — لمن يُنشئ فواتير دون صلاحية users.update */
        Route::post('/invoices/ocr-extract', [\App\Http\Controllers\Api\V1\OcrController::class, 'scanInvoice'])
            ->middleware('permission:invoices.create');

        Route::prefix('wallet')->group(function () {
            Route::get('/',                           [\App\Http\Controllers\Api\V1\WalletController::class, 'show'])
                ->middleware('permission:invoices.view');
            Route::get('/transactions',               [\App\Http\Controllers\Api\V1\WalletController::class, 'transactions'])
                ->middleware('permission:invoices.view');
            Route::middleware('idempotent')->group(function () {
                Route::post('/top-up',              [\App\Http\Controllers\Api\V1\WalletController::class, 'topUp'])
                    ->middleware('permission:invoices.update');
                Route::post('/transfer',             [\App\Http\Controllers\Api\V1\WalletController::class, 'transfer'])
                    ->middleware('permission:invoices.update');
            });
            Route::post('/transactions/{id}/reverse', [\App\Http\Controllers\Api\V1\WalletController::class, 'reverse'])
                ->middleware('permission:invoices.update');
        });

        /** طلبات شحن المحفظة — اعتماد الإدارة قبل إضافة الرصيد */
        Route::prefix('wallet-top-up-requests')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\WalletTopUpRequestController::class, 'store'])
                ->middleware('permission:wallet.top_up_requests.create');
            Route::get('/my', [\App\Http\Controllers\Api\V1\WalletTopUpRequestController::class, 'my'])
                ->middleware('permission:wallet.top_up_requests.view');
            Route::get('/{id}/receipt', [\App\Http\Controllers\Api\V1\WalletTopUpRequestController::class, 'receipt']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\WalletTopUpRequestController::class, 'show']);
            Route::patch('/{id}', [\App\Http\Controllers\Api\V1\WalletTopUpRequestController::class, 'update'])
                ->middleware('permission:wallet.top_up_requests.create');
            Route::post('/{id}/resubmit', [\App\Http\Controllers\Api\V1\WalletTopUpRequestController::class, 'resubmit'])
                ->middleware('permission:wallet.top_up_requests.create');
        });

        Route::prefix('admin/wallet-top-up-requests')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\AdminWalletTopUpRequestController::class, 'index'])
                ->middleware('permission:wallet.top_up_requests.review');
            Route::post('/{id}/approve', [\App\Http\Controllers\Api\V1\AdminWalletTopUpRequestController::class, 'approve'])
                ->middleware('permission:wallet.top_up_requests.review');
            Route::post('/{id}/reject', [\App\Http\Controllers\Api\V1\AdminWalletTopUpRequestController::class, 'reject'])
                ->middleware('permission:wallet.top_up_requests.review');
            Route::post('/{id}/return', [\App\Http\Controllers\Api\V1\AdminWalletTopUpRequestController::class, 'returnForRevision'])
                ->middleware('permission:wallet.top_up_requests.review');
        });

        Route::prefix('payments')->group(function () {
            Route::get('/invoice/{invoiceId}', [\App\Http\Controllers\Api\V1\WalletController::class, 'paymentsByInvoice'])
                ->middleware('permission:invoices.view');
            Route::post('/{id}/refund',        [\App\Http\Controllers\Api\V1\WalletController::class, 'refundPayment'])
                ->middleware(['idempotent', 'permission:invoices.update']);
        });

        Route::delete('/products/{product}', [\App\Http\Controllers\Api\V1\ProductController::class, 'destroy'])
            ->middleware('permission:products.delete');
        Route::apiResource('products',  \App\Http\Controllers\Api\V1\ProductController::class)->except(['destroy']);
        Route::post('/quotes', [\App\Http\Controllers\Api\V1\QuoteController::class, 'store'])
            ->middleware('permission:users.update');
        Route::put('/quotes/{quote}', [\App\Http\Controllers\Api\V1\QuoteController::class, 'update'])
            ->middleware('permission:users.update');
        Route::delete('/quotes/{quote}', [\App\Http\Controllers\Api\V1\QuoteController::class, 'destroy'])
            ->middleware('permission:users.update');
        Route::apiResource('quotes',    \App\Http\Controllers\Api\V1\QuoteController::class)->only(['index', 'show']);
        Route::get('/nps',              [\App\Http\Controllers\Api\V1\NpsController::class, 'index']);
        Route::post('/nps',             [\App\Http\Controllers\Api\V1\NpsController::class, 'store'])
            ->middleware('permission:users.update');
        Route::get('/warranty-items',   fn(\Illuminate\Http\Request $r) => response()->json(['data' => \App\Models\WarrantyItem::where('company_id', $r->user()->company_id)->orderByDesc('warranty_end')->paginate(20)]));
        Route::get('/service-reminders',fn(\Illuminate\Http\Request $r) => response()->json(['data' => \App\Models\ServiceReminder::where('company_id', $r->user()->company_id)->orderBy('next_service_date')->paginate(20)]));

        Route::prefix('units')->group(function () {
            Route::get('/',                [\App\Http\Controllers\Api\V1\UnitController::class, 'index']);
            Route::post('/',               [\App\Http\Controllers\Api\V1\UnitController::class, 'store']);
            Route::put('/{id}',            [\App\Http\Controllers\Api\V1\UnitController::class, 'update'])
                ->middleware('permission:inventory.adjust');
            Route::delete('/{id}',         [\App\Http\Controllers\Api\V1\UnitController::class, 'destroy'])
                ->middleware('permission:inventory.adjust');
            Route::get('/conversions',     [\App\Http\Controllers\Api\V1\UnitController::class, 'conversions']);
            Route::post('/conversions',    [\App\Http\Controllers\Api\V1\UnitController::class, 'storeConversion'])
                ->middleware('permission:inventory.adjust');
        });

        Route::post('/suppliers', [\App\Http\Controllers\Api\V1\SupplierController::class, 'store'])
            ->middleware('permission:suppliers.create');
        Route::put('/suppliers/{supplier}', [\App\Http\Controllers\Api\V1\SupplierController::class, 'update'])
            ->middleware('permission:suppliers.update');
        Route::delete('/suppliers/{supplier}', [\App\Http\Controllers\Api\V1\SupplierController::class, 'destroy'])
            ->middleware('permission:suppliers.delete');
        Route::apiResource('suppliers', \App\Http\Controllers\Api\V1\SupplierController::class)->only(['index', 'show']);

        Route::middleware(['permission:org_units.view', 'business.feature:org_structure'])->group(function () {
            Route::get('/org-units', [\App\Http\Controllers\Api\V1\OrgUnitController::class, 'index']);
            Route::get('/org-units/tree', [\App\Http\Controllers\Api\V1\OrgUnitController::class, 'tree']);
        });
        Route::post('/org-units', [\App\Http\Controllers\Api\V1\OrgUnitController::class, 'store'])
            ->middleware(['permission:org_units.create', 'business.feature:org_structure']);
        Route::put('/org-units/{id}', [\App\Http\Controllers\Api\V1\OrgUnitController::class, 'update'])
            ->middleware(['permission:org_units.update', 'business.feature:org_structure']);
        Route::delete('/org-units/{id}', [\App\Http\Controllers\Api\V1\OrgUnitController::class, 'destroy'])
            ->middleware(['permission:org_units.delete', 'business.feature:org_structure']);

        Route::middleware(['permission:suppliers.view', 'business.feature:supplier_contract_mgmt'])->group(function () {
            Route::get('/suppliers/{supplierId}/contracts', [\App\Http\Controllers\Api\V1\SupplierContractController::class, 'index']);
            Route::get('/suppliers/{supplierId}/contracts/{contractId}/download', [\App\Http\Controllers\Api\V1\SupplierContractController::class, 'download']);
        });
        Route::post('/suppliers/{supplierId}/contracts', [\App\Http\Controllers\Api\V1\SupplierContractController::class, 'store'])
            ->middleware(['permission:suppliers.update', 'business.feature:supplier_contract_mgmt']);
        Route::delete('/suppliers/{supplierId}/contracts/{contractId}', [\App\Http\Controllers\Api\V1\SupplierContractController::class, 'destroy'])
            ->middleware(['permission:suppliers.update', 'business.feature:supplier_contract_mgmt']);

        Route::prefix('purchases')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Api\V1\PurchaseController::class, 'index']);
            Route::post('/',                   [\App\Http\Controllers\Api\V1\PurchaseController::class, 'store'])
                ->middleware('permission:purchases.create');
            /** OCR لصور فواتير الموردين — دون صلاحية users.update (مسار الحوكمة القديم) */
            Route::post('/ocr-extract',        [\App\Http\Controllers\Api\V1\OcrController::class, 'scanInvoice'])
                ->middleware('permission:purchases.create');
            Route::get('/{id}',                [\App\Http\Controllers\Api\V1\PurchaseController::class, 'show']);
            Route::patch('/{id}/status',       [\App\Http\Controllers\Api\V1\PurchaseController::class, 'updateStatus'])
                ->middleware('permission:purchases.create');
            Route::post('/{id}/receive',       [\App\Http\Controllers\Api\V1\PurchaseController::class, 'receive'])
                ->middleware('permission:purchases.create');
            Route::post('/{id}/documents',     [\App\Http\Controllers\Api\V1\PurchaseController::class, 'uploadDocument'])
                ->middleware('permission:purchases.create');
            Route::delete('/{id}/documents/{index}', [\App\Http\Controllers\Api\V1\PurchaseController::class, 'deleteDocument'])
                ->middleware('permission:purchases.create');
            Route::get('/{id}/receipts',       [\App\Http\Controllers\Api\V1\GoodsReceiptController::class, 'byPurchase']);
            Route::post('/{id}/receipts',      [\App\Http\Controllers\Api\V1\GoodsReceiptController::class, 'store'])
                ->middleware('permission:purchases.create');
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
                Route::post('/',                 [\App\Http\Controllers\Api\V1\InventoryController::class, 'createReservation'])
                    ->middleware('permission:inventory.adjust');
                Route::patch('/{id}/consume',    [\App\Http\Controllers\Api\V1\InventoryController::class, 'consumeReservation'])
                    ->middleware('permission:inventory.adjust');
                Route::patch('/{id}/release',    [\App\Http\Controllers\Api\V1\InventoryController::class, 'releaseReservation'])
                    ->middleware('permission:inventory.adjust');
                Route::patch('/{id}/cancel',     [\App\Http\Controllers\Api\V1\InventoryController::class, 'cancelReservation'])
                    ->middleware('permission:inventory.adjust');
            });
        });

        Route::prefix('wallets')->group(function () {
            Route::get('/{customerId}/summary',          [\App\Http\Controllers\Api\V1\WalletController::class, 'summary'])
                ->middleware('permission:invoices.view');
            Route::get('/{walletId}/transactions',       [\App\Http\Controllers\Api\V1\WalletController::class, 'transactions'])
                ->middleware('permission:invoices.view');
            Route::post('/top-up/individual',            [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpIndividual'])
                ->middleware(['idempotent', 'permission:invoices.update']);
            Route::post('/top-up/fleet',                 [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpFleet'])
                ->middleware(['idempotent', 'permission:invoices.update']);
            Route::post('/transfer',                     [\App\Http\Controllers\Api\V1\WalletController::class, 'transfer'])
                ->middleware(['idempotent', 'permission:invoices.update']);
            Route::post('/reversal',                     [\App\Http\Controllers\Api\V1\WalletController::class, 'reverse'])
                ->middleware(['idempotent', 'permission:invoices.update']);
        });

        Route::prefix('api-keys')->middleware('permission:api_keys.manage')->group(function () {
            Route::get('/',        [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'index']);
            Route::post('/',       [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'store']);
            Route::patch('/{id}',  [\App\Http\Controllers\Api\V1\ApiKeyController::class, 'update']);
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
            Route::get('/sales',                [\App\Http\Controllers\Api\V1\ReportController::class, 'sales'])->middleware('permission:reports.financial.view');
            Route::get('/sales-by-customer',    [\App\Http\Controllers\Api\V1\ReportController::class, 'salesByCustomer'])->middleware('permission:reports.financial.view');
            Route::get('/sales-by-product',     [\App\Http\Controllers\Api\V1\ReportController::class, 'salesByProduct'])->middleware('permission:reports.financial.view');
            Route::get('/overdue-receivables',  [\App\Http\Controllers\Api\V1\ReportController::class, 'overdueReceivables'])->middleware('permission:reports.financial.view');
            Route::get('/work-orders',          [\App\Http\Controllers\Api\V1\ReportController::class, 'workOrders'])->middleware('permission:reports.operations.view');
            Route::get('/kpi',                  [\App\Http\Controllers\Api\V1\ReportController::class, 'kpi'])->middleware('permission:reports.financial.view');
            Route::get('/summary',              [\App\Http\Controllers\Api\V1\ReportController::class, 'kpi'])->middleware('permission:reports.financial.view');
            Route::get('/kpi-dictionary',       [\App\Http\Controllers\Api\V1\ReportController::class, 'kpiDictionary']);
            Route::get('/vat',                  [\App\Http\Controllers\Api\V1\ReportController::class, 'vatReport'])->middleware('permission:reports.accounting.view');
            Route::get('/inventory',            [\App\Http\Controllers\Api\V1\ReportController::class, 'inventory'])->middleware('permission:reports.operations.view');
            Route::get('/financial',            [\App\Http\Controllers\Api\V1\ReportController::class, 'financial'])->middleware('permission:reports.financial.view');
            Route::get('/cash-flow',            [\App\Http\Controllers\Api\V1\ReportController::class, 'cashFlow'])->middleware('permission:reports.financial.view');
            Route::get('/purchases',            [\App\Http\Controllers\Api\V1\ReportController::class, 'purchasesReport'])->middleware('permission:reports.financial.view');
            Route::get('/receivables-aging',    [\App\Http\Controllers\Api\V1\ReportController::class, 'receivablesAging'])->middleware('permission:reports.financial.view');
            Route::get('/business-analytics',   [\App\Http\Controllers\Api\V1\ReportController::class, 'businessAnalytics'])->middleware('permission:reports.intelligence.view');
            Route::get('/employees',            [\App\Http\Controllers\Api\V1\ReportController::class, 'employeeReport'])->middleware('permission:reports.employees.view');
            Route::get('/operations',           [\App\Http\Controllers\Api\V1\ReportController::class, 'operationsReport'])->middleware('permission:reports.operations.view');
            Route::get('/intelligence-digest',  [\App\Http\Controllers\Api\V1\ReportController::class, 'intelligenceDigest'])->middleware('permission:reports.intelligence.view');
            Route::get('/communications',       [\App\Http\Controllers\Api\V1\ReportController::class, 'communicationsReport'])->middleware('permission:reports.operations.view');
            Route::get('/smart-tasks',          [\App\Http\Controllers\Api\V1\ReportController::class, 'smartTasksReport'])->middleware('permission:reports.operations.view');
        });

        // Wave 3 Batch-3: lightweight operational review layer for reconciliation
        Route::prefix('financial-reconciliation')->middleware('permission:reports.financial.view')->group(function () {
            Route::get('/latest', [\App\Http\Controllers\Api\V1\FinancialReconciliationController::class, 'latest']);
            Route::get('/health', [\App\Http\Controllers\Api\V1\FinancialReconciliationController::class, 'health']);
            Route::get('/runs', [\App\Http\Controllers\Api\V1\FinancialReconciliationController::class, 'runs']);
            Route::get('/findings', [\App\Http\Controllers\Api\V1\FinancialReconciliationController::class, 'findings']);
            Route::get('/findings/{id}', [\App\Http\Controllers\Api\V1\FinancialReconciliationController::class, 'show']);
            Route::get('/summary', [\App\Http\Controllers\Api\V1\FinancialReconciliationController::class, 'summary']);
            Route::patch('/findings/{id}/status', [\App\Http\Controllers\Api\V1\FinancialReconciliationController::class, 'updateFindingStatus'])
                ->middleware('permission:users.update');
        });

        // Institutional capabilities Batch-2: Meetings MVP (low risk, no video/calendar integrations)
        Route::prefix('meetings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\MeetingController::class, 'index'])
                ->middleware('permission:meetings.update');
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\MeetingController::class, 'show'])
                ->whereNumber('id')
                ->middleware('permission:meetings.update');
            Route::post('/', [\App\Http\Controllers\Api\V1\MeetingController::class, 'store'])
                ->middleware('permission:meetings.create');
            Route::put('/{id}', [\App\Http\Controllers\Api\V1\MeetingController::class, 'update'])
                ->middleware('permission:meetings.update');
            Route::post('/{id}/participants', [\App\Http\Controllers\Api\V1\MeetingController::class, 'addParticipant'])
                ->middleware('permission:meetings.update');
            Route::delete('/{id}/participants/{participantId}', [\App\Http\Controllers\Api\V1\MeetingController::class, 'removeParticipant'])
                ->middleware('permission:meetings.update');
            Route::post('/{id}/minutes', [\App\Http\Controllers\Api\V1\MeetingController::class, 'addMinutes'])
                ->middleware('permission:meetings.update');
            Route::get('/{id}/minutes', [\App\Http\Controllers\Api\V1\MeetingController::class, 'listMinutes'])
                ->middleware('permission:meetings.view_minutes');
            Route::post('/{id}/decisions', [\App\Http\Controllers\Api\V1\MeetingController::class, 'addDecision'])
                ->middleware('permission:meetings.manage_actions');
            Route::post('/{id}/decisions/{decisionId}/approval/start', [\App\Http\Controllers\Api\V1\MeetingController::class, 'startDecisionApproval'])
                ->middleware('permission:meetings.manage_actions');
            Route::get('/{id}/decisions/{decisionId}/approval-status', [\App\Http\Controllers\Api\V1\MeetingController::class, 'decisionApprovalStatus'])
                ->middleware('permission:meetings.view_minutes');
            Route::post('/{id}/decisions/{decisionId}/approve', [\App\Http\Controllers\Api\V1\MeetingController::class, 'approveDecision'])
                ->middleware(['permission:meetings.manage_actions', 'permission:users.update']);
            Route::post('/{id}/decisions/{decisionId}/reject', [\App\Http\Controllers\Api\V1\MeetingController::class, 'rejectDecision'])
                ->middleware(['permission:meetings.manage_actions', 'permission:users.update']);
            Route::post('/{id}/actions', [\App\Http\Controllers\Api\V1\MeetingController::class, 'addAction'])
                ->middleware('permission:meetings.manage_actions');
            Route::patch('/{id}/actions/{actionId}', [\App\Http\Controllers\Api\V1\MeetingController::class, 'updateAction'])
                ->middleware('permission:meetings.manage_actions');
            Route::post('/{id}/actions/{actionId}/close', [\App\Http\Controllers\Api\V1\MeetingController::class, 'closeAction'])
                ->middleware('permission:meetings.manage_actions');
            Route::post('/{id}/close', [\App\Http\Controllers\Api\V1\MeetingController::class, 'close'])
                ->middleware('permission:meetings.close');
        });

        // Financial Core — Ledger & Chart of Accounts
        Route::prefix('ledger')->middleware('permission:reports.accounting.view')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Api\V1\LedgerController::class, 'index']);
            Route::get('/trial-balance',  [\App\Http\Controllers\Api\V1\LedgerController::class, 'trialBalance']);
            Route::get('/{id}',           [\App\Http\Controllers\Api\V1\LedgerController::class, 'show']);
            Route::post('/{id}/reverse',  [\App\Http\Controllers\Api\V1\LedgerController::class, 'reverse']);
        });

        Route::apiResource('chart-of-accounts', \App\Http\Controllers\Api\V1\ChartOfAccountController::class)
            ->middleware('permission:reports.accounting.view');

        // Fleet Wallet — read-only
        Route::get('/wallet/{customerId}/summary',    [\App\Http\Controllers\Api\V1\WalletController::class, 'summary'])
            ->middleware('permission:invoices.view');
        Route::get('/wallet/{walletId}/transactions', [\App\Http\Controllers\Api\V1\WalletController::class, 'transactions'])
            ->middleware('permission:invoices.view');

        // Fleet Wallet — write (idempotent)
        Route::middleware('idempotent')->group(function () {
            Route::post('/wallet/top-up/individual', [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpIndividual'])
                ->middleware('permission:invoices.update');
            Route::post('/wallet/top-up/fleet',      [\App\Http\Controllers\Api\V1\WalletController::class, 'topUpFleet'])
                ->middleware('permission:invoices.update');
            Route::post('/wallet/transfer',          [\App\Http\Controllers\Api\V1\WalletController::class, 'transfer'])
                ->middleware('permission:invoices.update');
            Route::post('/wallet/reversal',          [\App\Http\Controllers\Api\V1\WalletController::class, 'reverse'])
                ->middleware('permission:invoices.update');
        });

        // Fleet Operations — Workshop Side (verify-plate only)
        Route::prefix('fleet')->group(function () {
            Route::get('/customers',                          [\App\Http\Controllers\Api\V1\FleetController::class, 'fleetCustomers']);
            Route::post('/verify-plate',                      [\App\Http\Controllers\Api\V1\FleetController::class, 'verifyPlate'])
                ->middleware('permission:fleet.plate.verify');
            // يتم الاعتماد الآن من Fleet Portal — هذا المسار لمركز الخدمة فقط (طوارئ)
            Route::post('/work-orders/{id}/approve',          [\App\Http\Controllers\Api\V1\FleetController::class, 'approveWorkOrder'])
                ->middleware('permission:work_orders.update');
        });

        // Fleet Portal — Customer Side (fleet_contact / fleet_manager only)
        Route::prefix('fleet-portal')->group(function () {
            Route::get('/dashboard',                          [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'dashboard']);
            Route::get('/service-catalog',                  [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'serviceCatalog']);
            Route::post('/work-orders/pricing-preview',       [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'previewWorkOrderLinePrice'])
                ->middleware('permission:fleet.workorder.create');
            Route::get('/vehicles',                           [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'vehicles']);
            Route::get('/wallet/summary',                     [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'walletSummary']);
            Route::get('/wallet/transactions',                [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'transactions']);
            Route::post('/wallet/top-up',                     [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'topUp'])
                ->middleware(['idempotent', 'permission:fleet.wallet.topup']);
            Route::get('/work-orders',                        [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'pendingApproval']);
            Route::post('/work-orders',                       [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'createWorkOrder'])
                ->middleware('permission:fleet.workorder.create');
            Route::get('/work-orders/pending-approval',       [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'pendingApproval']);
            Route::get('/wallet',                             [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'walletSummary']);
            Route::post('/work-orders/{id}/approve-credit',   [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'approveCredit'])
                ->middleware('permission:fleet.workorder.approve');
            Route::post('/work-orders/{id}/reject-credit',    [\App\Http\Controllers\Api\V1\FleetPortalController::class, 'rejectCredit'])
                ->middleware('permission:fleet.workorder.approve');
        });
    });

    // ── SaaS Plans (Phase 7) ─────────────────────────────────────────
    Route::get('/plans', [\App\Http\Controllers\Api\V1\SaasController::class, 'listPlans']);
    Route::post('/plans/seed', [\App\Http\Controllers\Api\V1\SaasController::class, 'seedPlans'])
        ->middleware(['auth:sanctum', 'permission:subscriptions.manage']);
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {
        Route::get('/subscription',         [\App\Http\Controllers\Api\V1\SaasController::class, 'currentSubscription']);
        Route::post('/subscription/change',  [\App\Http\Controllers\Api\V1\SaasController::class, 'changePlan'])
            ->middleware('permission:subscriptions.manage');
        Route::get('/subscription/usage',   [\App\Http\Controllers\Api\V1\SaasController::class, 'usageLimits'])
            ->middleware('permission:subscriptions.view');
        Route::put('/plans/{slug}',         [\App\Http\Controllers\Api\V1\SaasController::class, 'updatePlan'])
            ->middleware('permission:subscriptions.manage');
    });

    /** قراءة المشتركين لمشغّلي المنصة (بريد في SAAS_PLATFORM_ADMIN_EMAILS) */
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {
        Route::get('/platform/ops-summary', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'opsSummary'])
            ->middleware('throttle:120,1');
        Route::get('/platform/audit-logs', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'auditLogs'])
            ->middleware('throttle:60,1');
        Route::get('/platform/companies/{id}', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'showCompany']);
        Route::patch('/platform/companies/{id}/operational', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'updateOperational'])
            ->middleware('throttle:30,1');
        Route::patch('/platform/companies/{id}/subscription', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'updateSubscription'])
            ->middleware('throttle:30,1');
        Route::patch('/platform/companies/{id}/vertical-profile', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'assignVerticalProfile'])
            ->middleware('throttle:30,1');
        Route::get('/platform/companies', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'companies']);
        Route::patch('/platform/companies/{id}/financial-model', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'updateFinancialModel']);
        Route::get('/platform/work-order-cancellation-requests', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'workOrderCancellationRequests']);
        Route::post('/platform/work-order-cancellation-requests/{id}/approve', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'approveWorkOrderCancellation']);
        Route::post('/platform/work-order-cancellation-requests/{id}/reject', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'rejectWorkOrderCancellation']);
        Route::get('/admin/companies', [\App\Http\Controllers\Api\V1\PlatformAdminController::class, 'companies']);
        Route::get('/platform/announcement-banner', [\App\Http\Controllers\Api\V1\PlatformAnnouncementBannerController::class, 'show']);
        Route::get('/platform/announcement-banner/admin', [\App\Http\Controllers\Api\V1\PlatformAnnouncementBannerController::class, 'adminShow']);
        Route::put('/platform/announcement-banner', [\App\Http\Controllers\Api\V1\PlatformAnnouncementBannerController::class, 'update']);
    });

    // ── Bays & Bookings (Phase 6) ────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {
        Route::get('/bays',                      [\App\Http\Controllers\Api\V1\BayController::class, 'index']);
        Route::post('/bays',                     [\App\Http\Controllers\Api\V1\BayController::class, 'store'])
            ->middleware('permission:work_orders.update');
        Route::patch('/bays/{id}/status',        [\App\Http\Controllers\Api\V1\BayController::class, 'updateStatus'])
            ->middleware('permission:work_orders.update');
        Route::get('/bookings',                  [\App\Http\Controllers\Api\V1\BayController::class, 'listBookings']);
        Route::post('/bookings',                 [\App\Http\Controllers\Api\V1\BayController::class, 'storeBooking'])
            ->middleware('permission:work_orders.update');
        Route::patch('/bookings/{id}',           [\App\Http\Controllers\Api\V1\BayController::class, 'updateBooking'])
            ->middleware('permission:work_orders.update');
        Route::post('/bookings/availability',    [\App\Http\Controllers\Api\V1\BayController::class, 'checkAvailability'])
            ->middleware('permission:work_orders.update');
        Route::get('/bays/heatmap',              [\App\Http\Controllers\Api\V1\BayController::class, 'heatmap']);
    });

    // ── Workshop Operations (Phase 5) ────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('workshop')->group(function () {
        // Employees
        Route::get('/employees/stats',        [\App\Http\Controllers\Api\V1\WorkshopController::class, 'employeeStats']);
        Route::get('/employees',              [\App\Http\Controllers\Api\V1\WorkshopController::class, 'listEmployees']);
        Route::post('/employees',             [\App\Http\Controllers\Api\V1\WorkshopController::class, 'storeEmployee'])
            ->middleware('permission:users.update');
        Route::get('/employees/{id}',         [\App\Http\Controllers\Api\V1\WorkshopController::class, 'showEmployee']);
        Route::put('/employees/{id}',         [\App\Http\Controllers\Api\V1\WorkshopController::class, 'updateEmployee'])
            ->middleware('permission:users.update');
        // Attendance
        Route::post('/attendance/check-in',              [\App\Http\Controllers\Api\V1\WorkshopController::class, 'checkIn'])
            ->middleware('permission:users.update');
        Route::post('/attendance/check-out',             [\App\Http\Controllers\Api\V1\WorkshopController::class, 'checkOut'])
            ->middleware('permission:users.update');
        Route::get('/attendance/today',                  [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceTodayAll']);
        Route::get('/attendance/month-all',             [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceMonthAll']);
        Route::get('/attendance/{employeeId}/today',     [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceToday']);
        Route::get('/attendance/{employeeId}/month',     [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceMonth']);
        Route::get('/attendance/{employeeId}/logs',      [\App\Http\Controllers\Api\V1\WorkshopController::class, 'attendanceLogs']);
        // Tasks
        Route::get('/tasks',                  [\App\Http\Controllers\Api\V1\WorkshopController::class, 'listTasks']);
        Route::post('/tasks',                 [\App\Http\Controllers\Api\V1\WorkshopController::class, 'storeTask'])
            ->middleware('permission:users.update');
        Route::patch('/tasks/{id}/status',    [\App\Http\Controllers\Api\V1\WorkshopController::class, 'updateTaskStatus'])
            ->middleware('permission:users.update');
        Route::get('/tasks/stats',            [\App\Http\Controllers\Api\V1\WorkshopController::class, 'taskStats']);
        Route::get('/tasks/smart-summary',    [\App\Http\Controllers\Api\V1\WorkshopController::class, 'smartTaskSummary']);
        Route::get('/tasks/suggested-assignees', [\App\Http\Controllers\Api\V1\WorkshopController::class, 'suggestTaskAssignees']);
        // Administrative Communications
        Route::get('/communications', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'index']);
        Route::post('/communications', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'store']);
        Route::put('/communications/{id}', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'update']);
        Route::post('/communications/{id}/submit', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'submit']);
        Route::post('/communications/{id}/transfer', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'transfer']);
        Route::post('/communications/{id}/request-signature', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'requestSignature']);
        Route::post('/communications/{id}/sign', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'sign']);
        Route::post('/communications/{id}/archive', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'archive']);
        Route::post('/communications/{id}/restore', [\App\Http\Controllers\Api\V1\AdminCommunicationController::class, 'restore']);
        // Commissions
        Route::get('/commissions',            [\App\Http\Controllers\Api\V1\WorkshopController::class, 'listCommissions']);
        Route::get('/commission-rules',         [\App\Http\Controllers\Api\V1\WorkshopController::class, 'listCommissionRules']);
        Route::post('/commission-rules',       [\App\Http\Controllers\Api\V1\WorkshopController::class, 'storeCommissionRule'])
            ->middleware('permission:users.update');
        Route::put('/commission-rules/{id}',  [\App\Http\Controllers\Api\V1\WorkshopController::class, 'updateCommissionRule'])
            ->middleware('permission:users.update');
        Route::delete('/commission-rules/{id}', [\App\Http\Controllers\Api\V1\WorkshopController::class, 'deleteCommissionRule'])
            ->middleware('permission:users.update');
        Route::post('/commissions/{id}/pay',  [\App\Http\Controllers\Api\V1\WorkshopController::class, 'payCommission'])
            ->middleware('permission:users.update');
    });

    // بنود الكتالوج التعاقدي — صلاحيات مفصّلة (لا تتطلب `users.update` كي تبقى قابلة للفصل لاحقًا)
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription'])->prefix('governance')->group(function () {
        Route::middleware('permission:contracts.service_items.view')->group(function () {
            Route::get('/contracts/{contract}/service-items', [\App\Http\Controllers\Api\V1\ContractServiceItemController::class, 'index']);
            Route::get('/contracts/{contract}/service-items/{itemId}/usage', [\App\Http\Controllers\Api\V1\ContractServiceItemController::class, 'itemUsage']);
        });
        Route::post('/contracts/{contract}/service-items/match-preview', [\App\Http\Controllers\Api\V1\ContractServiceItemController::class, 'matchPreview'])
            ->middleware('permission:contracts.service_items.match_preview');
        Route::post('/contracts/{contract}/service-items', [\App\Http\Controllers\Api\V1\ContractServiceItemController::class, 'store'])
            ->middleware('permission:contracts.service_items.create');
        Route::put('/contracts/{contract}/service-items/{itemId}', [\App\Http\Controllers\Api\V1\ContractServiceItemController::class, 'update'])
            ->middleware('permission:contracts.service_items.update');
        Route::delete('/contracts/{contract}/service-items/{itemId}', [\App\Http\Controllers\Api\V1\ContractServiceItemController::class, 'destroy'])
            ->middleware('permission:contracts.service_items.delete');
    });

    // ── Governance (Phase 4) ──────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription', 'permission:users.update'])->prefix('governance')->group(function () {
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
        Route::match(['put', 'patch'], '/vehicles/{id}/settings', [\App\Http\Controllers\Api\V1\FuelController::class, 'saveSettings']);
        Route::get('/vehicles/{id}/documents',             [\App\Http\Controllers\Api\V1\FuelController::class, 'getDocuments']);
        Route::post('/vehicles/{id}/documents',            [\App\Http\Controllers\Api\V1\FuelController::class, 'uploadDocument']);
        Route::delete('/vehicles/{vehicleId}/documents/{docId}', [\App\Http\Controllers\Api\V1\FuelController::class, 'deleteDocument']);

        // Bulk Import (Excel / CSV)
        Route::post('/vehicles/import',  [\App\Http\Controllers\Api\V1\ImportController::class, 'importVehicles']);
        Route::post('/employees/import', [\App\Http\Controllers\Api\V1\ImportController::class, 'importEmployees']);

        // OCR
        Route::post('/ocr/plate',   [\App\Http\Controllers\Api\V1\OcrController::class, 'scanPlate']);
        Route::post('/ocr/invoice', [\App\Http\Controllers\Api\V1\OcrController::class, 'scanInvoice']);
        Route::post('/ocr/vehicle-document', [\App\Http\Controllers\Api\V1\OcrController::class, 'scanVehicleDocument'])
            ->middleware('permission:vehicles.update');

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
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('support')->group(function () {
        // Tickets
        Route::get('/tickets',                              [\App\Http\Controllers\Api\V1\SupportController::class, 'indexTickets']);
        Route::post('/tickets',                             [\App\Http\Controllers\Api\V1\SupportController::class, 'storeTicket'])
            ->middleware('permission:users.update');
        Route::get('/tickets/{id}',                         [\App\Http\Controllers\Api\V1\SupportController::class, 'showTicket']);
        Route::put('/tickets/{id}',                         [\App\Http\Controllers\Api\V1\SupportController::class, 'updateTicket'])
            ->middleware('permission:users.update');
        Route::patch('/tickets/{id}/status',                [\App\Http\Controllers\Api\V1\SupportController::class, 'changeStatus'])
            ->middleware('permission:users.update');
        Route::post('/tickets/{id}/replies',                [\App\Http\Controllers\Api\V1\SupportController::class, 'storeReply'])
            ->middleware('permission:users.update');
        Route::post('/tickets/{id}/rate',                   [\App\Http\Controllers\Api\V1\SupportController::class, 'rateSatisfaction'])
            ->middleware('permission:users.update');
        // Stats & SLA
        Route::get('/stats',                                [\App\Http\Controllers\Api\V1\SupportController::class, 'stats']);
        Route::get('/sla-policies',                         [\App\Http\Controllers\Api\V1\SupportController::class, 'indexSla']);
        Route::post('/sla-policies',                        [\App\Http\Controllers\Api\V1\SupportController::class, 'storeSla'])
            ->middleware('permission:users.update');
        Route::put('/sla-policies/{id}',                    [\App\Http\Controllers\Api\V1\SupportController::class, 'updateSla'])
            ->middleware('permission:users.update');
        Route::post('/sla/check-breaches',                  [\App\Http\Controllers\Api\V1\SupportController::class, 'checkSlaBreaches'])
            ->middleware('permission:users.update');
        // Knowledge Base
        Route::get('/kb',                                   [\App\Http\Controllers\Api\V1\SupportController::class, 'indexKb']);
        Route::post('/kb',                                  [\App\Http\Controllers\Api\V1\SupportController::class, 'storeKb'])
            ->middleware('permission:users.update');
        Route::put('/kb/{id}',                              [\App\Http\Controllers\Api\V1\SupportController::class, 'updateKb'])
            ->middleware('permission:users.update');
        Route::post('/kb/{id}/vote',                        [\App\Http\Controllers\Api\V1\SupportController::class, 'voteKb'])
            ->middleware('permission:users.update');
        Route::get('/kb/search',                            [\App\Http\Controllers\Api\V1\SupportController::class, 'searchKb']);
        Route::get('/kb-categories',                        [\App\Http\Controllers\Api\V1\SupportController::class, 'indexKbCategories']);
        Route::post('/kb-categories',                       [\App\Http\Controllers\Api\V1\SupportController::class, 'storeKbCategory'])
            ->middleware('permission:users.update');
    });

    // ── ZATCA ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('zatca')->group(function () {
        Route::get('/status',  [\App\Http\Controllers\Api\V1\ZatcaController::class, 'status']);
        Route::get('/logs',    [\App\Http\Controllers\Api\V1\ZatcaController::class, 'logs']);
        Route::post('/submit', [\App\Http\Controllers\Api\V1\ZatcaController::class, 'submit'])
            ->middleware('permission:invoices.update');
    });

    // ── Notifications ──────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('notifications')->group(function () {
        Route::get('/',         [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
        Route::put('/{id}/read',[\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead'])
            ->middleware('permission:users.update');
        Route::put('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllRead'])
            ->middleware('permission:users.update');
    });

    // ── Customer Portal ────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('customer-portal')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\CustomerPortalController::class, 'dashboard']);
    });

    Route::middleware(['auth.apikey', 'api.log', 'financial.protection', 'subscription'])->group(function () {
        Route::prefix('external/v1')->group(function () {
            Route::post('/invoices',       [\App\Http\Controllers\Api\V1\External\ExternalInvoiceController::class, 'store'])->middleware('idempotent');
            Route::get('/invoices/{uuid}', [\App\Http\Controllers\Api\V1\External\ExternalInvoiceController::class, 'show']);
        });
    });

    // ── AI Plugins Marketplace ─────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('plugins')->group(function () {
        Route::get('/',                 [\App\Http\Controllers\Api\V1\PluginController::class, 'index']);
        Route::get('/tenant',           [\App\Http\Controllers\Api\V1\PluginController::class, 'tenantPlugins']);
        Route::get('/{key}',            [\App\Http\Controllers\Api\V1\PluginController::class, 'show']);
        Route::post('/{key}/install',   [\App\Http\Controllers\Api\V1\PluginController::class, 'install'])
            ->middleware('permission:users.update');
        Route::delete('/{key}/uninstall', [\App\Http\Controllers\Api\V1\PluginController::class, 'uninstall'])
            ->middleware('permission:users.update');
        Route::put('/{key}/configure',  [\App\Http\Controllers\Api\V1\PluginController::class, 'configure'])
            ->middleware('permission:users.update');
        Route::post('/{key}/execute',   [\App\Http\Controllers\Api\V1\PluginController::class, 'execute'])
            ->middleware('permission:users.update');
    });
});
