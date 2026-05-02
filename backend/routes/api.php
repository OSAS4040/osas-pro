<?php

use App\Http\Controllers\Api\V1\AdminCommunicationController;
use App\Http\Controllers\Api\V1\AdminWalletTopUpRequestController;
use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\AuthSessionController;
use App\Http\Controllers\Api\V1\Auth\PhoneOtpAuthController;
use App\Http\Controllers\Api\V1\Auth\PhoneRegistrationFlowController;
use App\Http\Controllers\Api\V1\BayController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BundleController;
use App\Http\Controllers\Api\V1\ChartOfAccountController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CompanyProfileController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\ContractServiceItemController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerGroupController;
use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\CustomerPortalOrgUnitsController;
use App\Http\Controllers\Api\V1\CustomerPortalReportsController;
use App\Http\Controllers\Api\V1\CustomerPortalTeamUsersController;
use App\Http\Controllers\Api\V1\CustomerProfileController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\External\ExternalInvoiceController;
use App\Http\Controllers\Api\V1\FinancialReconciliationController;
use App\Http\Controllers\Api\V1\FleetController;
use App\Http\Controllers\Api\V1\FleetPortalController;
use App\Http\Controllers\Api\V1\FuelController;
use App\Http\Controllers\Api\V1\GoodsReceiptController;
use App\Http\Controllers\Api\V1\GovernanceController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\Internal\AuthSuspiciousLoginSignalsController;
use App\Http\Controllers\Api\V1\Internal\CommandCenterGovernanceController;
use App\Http\Controllers\Api\V1\Internal\DomainEventInspectionController;
use App\Http\Controllers\Api\V1\Internal\Phase2IntelligenceController;
use App\Http\Controllers\Api\V1\Internal\QaValidationController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NpsController;
use App\Http\Controllers\Api\V1\OcrController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\OrgUnitController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\Platform\PlatformCustomerPriceVersionController;
use App\Http\Controllers\Api\V1\Platform\PlatformPricingRequestController;
use App\Http\Controllers\Api\V1\Platform\PlatformPurchaseClaimsController;
use App\Http\Controllers\Api\V1\Platform\PlatformServiceProviderController;
use App\Http\Controllers\Api\V1\PlatformAdminController;
use App\Http\Controllers\Api\V1\PlatformAnnouncementBannerController;
use App\Http\Controllers\Api\V1\PlatformControlledActionController;
use App\Http\Controllers\Api\V1\PlatformDecisionLogController;
use App\Http\Controllers\Api\V1\PlatformIncidentLifecycleController;
use App\Http\Controllers\Api\V1\PlatformIncidentWorkflowController;
use App\Http\Controllers\Api\V1\PlatformIntelligenceCommandSurfaceController;
use App\Http\Controllers\Api\V1\PlatformIntelligenceIncidentCandidatesController;
use App\Http\Controllers\Api\V1\PlatformIntelligenceIncidentsController;
use App\Http\Controllers\Api\V1\PlatformIntelligenceSignalsController;
use App\Http\Controllers\Api\V1\PlatformNotificationCenterController;
use App\Http\Controllers\Api\V1\PlatformPhoneRegistrationReviewController;
use App\Http\Controllers\Api\V1\PlatformSupportController;
use App\Http\Controllers\Api\V1\PlatformTenantNavHideController;
use App\Http\Controllers\Api\V1\PluginController;
use App\Http\Controllers\Api\V1\POSController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PublicContentController;
use App\Http\Controllers\Api\V1\PurchaseClaimController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\Reporting\CompanyReportingController;
use App\Http\Controllers\Api\V1\Reporting\CustomerReportingController;
use App\Http\Controllers\Api\V1\Reporting\GlobalOperationsFeedController;
use App\Http\Controllers\Api\V1\Reporting\PlatformReportingController;
use App\Http\Controllers\Api\V1\Reporting\ReportingController;
use App\Http\Controllers\Api\V1\Reporting\ReportingExportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SaasController;
use App\Http\Controllers\Api\V1\SalaryController;
use App\Http\Controllers\Api\V1\SensitiveOperationPreviewController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServicePricingPolicyController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformBankTransactionImportController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformPaymentOrderController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformReviewQueueController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformSubscriptionOperationsController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformSubscriptionOverviewController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformSubscriptionsDebugHealthController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\TenantPaymentOrderController;
use App\Http\Controllers\Api\V1\SubscriptionsV2\TenantSubscriptionPortalController;
use App\Http\Controllers\Api\V1\SupplierContractController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\SystemCapabilitiesController;
use App\Http\Controllers\Api\V1\SystemVersionController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VehicleIdentityController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletTopUpRequestController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\WorkOrderBatchController;
use App\Http\Controllers\Api\V1\WorkOrderBulkController;
use App\Http\Controllers\Api\V1\WorkOrderCancellationRequestController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use App\Http\Controllers\Api\V1\WorkshopController;
use App\Http\Controllers\Api\V1\ZatcaController;
use App\Models\ApiUsageLog;
use App\Models\ServiceReminder;
use App\Models\WarrantyItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/health', function () {
        $checks = [];
        $healthy = true;

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'fail';
            $healthy = false;
        }

        try {
            Redis::ping();
            $checks['redis'] = 'ok';
        } catch (Throwable) {
            $checks['redis'] = 'fail';
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'version' => (string) config('deployment.version'),
            'trace_id' => app('trace_id'),
        ], $healthy ? 200 : 503);
    });

    Route::get('/system/version', SystemVersionController::class);

    Route::get('/public/landing-plans', [PublicContentController::class, 'landingPlans']);
    Route::get('/public/platform-announcement-banner', [PublicContentController::class, 'platformAnnouncementBanner']);

    Route::get('/public/vehicle-identity/{token}', [VehicleIdentityController::class, 'publicShow'])
        ->where('token', '[a-f0-9]{64}')
        ->middleware('throttle:120,1');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:register');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset-request');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset-confirm');

    Route::post('/auth/phone/request-otp', [PhoneOtpAuthController::class, 'requestOtp'])
        ->middleware('throttle:otp-resend');
    Route::post('/auth/phone/verify-otp', [PhoneOtpAuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify');

    /** Identity-only: no tenant/subscription/branch gates (Bearer must resolve). */
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/auth/push-device', [AuthController::class, 'registerPushDevice']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/sessions', [AuthSessionController::class, 'index']);
        Route::delete('/auth/sessions/{id}', [AuthSessionController::class, 'destroy'])
            ->whereNumber('id');
        Route::post('/auth/sessions/revoke-others', [AuthSessionController::class, 'revokeOthers']);
        Route::get('/auth/registration-status', [PhoneRegistrationFlowController::class, 'registrationStatus']);
    });

    /**
     * مشغّلو المنصة — Bearer يمر عبر EnsurePlatformAdmin (SaasPlatformAccess).
     * لا يُطبَّق GlobalTenantGuard هنا؛ يمكن للحساب المرتبط بشركة استدعاء هذه المسارات إن كان مشغّل منصة.
     */
    Route::middleware(['auth:sanctum', 'platform.admin'])->group(function () {
        Route::middleware(['platform.permission:platform.ops.read'])->group(function () {
            Route::get('/platform/ops-summary', [PlatformAdminController::class, 'opsSummary'])
                ->middleware('throttle:120,1');
        });

        Route::middleware(['platform.permission:platform.audit.read'])->group(function () {
            Route::get('/platform/audit-logs', [PlatformAdminController::class, 'auditLogs'])
                ->middleware('throttle:60,1');
        });

        Route::middleware(['platform.permission:platform.companies.read'])->group(function () {
            Route::get('/platform/companies/{id}', [PlatformAdminController::class, 'showCompany']);
            Route::get('/platform/companies/{id}/entity-snapshot', [PlatformAdminController::class, 'companyEntitySnapshot']);
            Route::get('/platform/companies/{id}/contracts-bridge', [PlatformAdminController::class, 'companyContractsBridge'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
            Route::get('/platform/companies/{id}/services-bridge', [PlatformAdminController::class, 'companyServicesBridge'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
            Route::get('/platform/companies', [PlatformAdminController::class, 'companies']);
            Route::get('/platform/provider-invoice-attachments', [PlatformAdminController::class, 'providerInvoiceAttachments']);
            Route::get('/platform/search', [PlatformAdminController::class, 'globalSearch']);
            Route::get('/platform/plans', [SaasController::class, 'listPlans']);
            Route::get('/platform/customers', [PlatformAdminController::class, 'platformCustomers'])
                ->middleware('throttle:60,1');
            Route::get('/admin/companies', [PlatformAdminController::class, 'companies']);
            Route::get('/admin/overview', [PlatformAdminController::class, 'dashboardOverview'])
                ->middleware('throttle:60,1');
            Route::get('/platform/navigation-visibility', [PlatformAdminController::class, 'navigationVisibility']);
        });

        Route::middleware(['platform.permission:platform.companies.operational'])->group(function () {
            Route::patch('/platform/navigation-visibility', [PlatformAdminController::class, 'updateNavigationVisibility'])
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.intelligence.signals.read'])->group(function () {
            Route::get('/platform/intelligence/signals', [PlatformIntelligenceSignalsController::class, 'index'])
                ->middleware('throttle:60,1');
        });

        Route::middleware(['platform.permission:platform.intelligence.candidates.read'])->group(function () {
            Route::get('/platform/intelligence/incident-candidates', [PlatformIntelligenceIncidentCandidatesController::class, 'index'])
                ->middleware('throttle:60,1');
        });

        Route::middleware(['platform.permission:platform.intelligence.incidents.read'])->group(function () {
            Route::get('/platform/intelligence/incidents', [PlatformIntelligenceIncidentsController::class, 'index'])
                ->middleware('throttle:60,1');
            Route::get('/platform/intelligence/incidents/{incident_key}', [PlatformIntelligenceIncidentsController::class, 'show'])
                ->where('incident_key', '[A-Za-z0-9_.]+')
                ->middleware('throttle:60,1');
        });

        Route::middleware(['platform.permission:platform.intelligence.incidents.materialize'])->group(function () {
            Route::post('/platform/intelligence/incidents/materialize', [PlatformIncidentLifecycleController::class, 'materialize'])
                ->middleware('throttle:30,1');
        });

        Route::post('/platform/intelligence/incidents/{incident_key}/acknowledge', [PlatformIncidentLifecycleController::class, 'acknowledge'])
            ->middleware(['platform.permission:platform.intelligence.incidents.acknowledge', 'throttle:60,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');
        Route::post('/platform/intelligence/incidents/{incident_key}/move-under-review', [PlatformIncidentLifecycleController::class, 'moveUnderReview'])
            ->middleware(['platform.permission:platform.intelligence.incidents.acknowledge', 'throttle:60,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');
        Route::post('/platform/intelligence/incidents/{incident_key}/escalate', [PlatformIncidentLifecycleController::class, 'escalate'])
            ->middleware(['platform.permission:platform.intelligence.incidents.escalate', 'throttle:60,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');
        Route::post('/platform/intelligence/incidents/{incident_key}/move-monitoring', [PlatformIncidentLifecycleController::class, 'moveMonitoring'])
            ->middleware(['platform.permission:platform.intelligence.incidents.acknowledge', 'throttle:60,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');
        Route::post('/platform/intelligence/incidents/{incident_key}/resolve', [PlatformIncidentLifecycleController::class, 'resolve'])
            ->middleware(['platform.permission:platform.intelligence.incidents.resolve', 'throttle:30,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');
        Route::post('/platform/intelligence/incidents/{incident_key}/close', [PlatformIncidentLifecycleController::class, 'close'])
            ->middleware(['platform.permission:platform.intelligence.incidents.close', 'throttle:30,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');
        Route::post('/platform/intelligence/incidents/{incident_key}/assign-owner', [PlatformIncidentLifecycleController::class, 'assignOwner'])
            ->middleware(['platform.permission:platform.intelligence.incidents.assign_owner', 'throttle:60,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');
        Route::post('/platform/intelligence/incidents/{incident_key}/notes', [PlatformIncidentLifecycleController::class, 'appendNote'])
            ->middleware(['platform.permission:platform.intelligence.incidents.acknowledge', 'throttle:60,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');

        Route::middleware(['platform.permission:platform.intelligence.decisions.read'])->group(function () {
            Route::get('/platform/intelligence/decisions', [PlatformDecisionLogController::class, 'index'])
                ->middleware('throttle:60,1');
            Route::get('/platform/intelligence/incidents/{incident_key}/decisions', [PlatformDecisionLogController::class, 'indexForIncident'])
                ->where('incident_key', '[A-Za-z0-9_.]+')
                ->middleware('throttle:60,1');
        });

        Route::post('/platform/intelligence/incidents/{incident_key}/decisions', [PlatformDecisionLogController::class, 'store'])
            ->middleware(['platform.permission:platform.intelligence.decisions.write', 'throttle:30,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');

        Route::middleware(['platform.permission:platform.intelligence.incidents.read'])->group(function () {
            Route::get('/platform/intelligence/incidents/{incident_key}/workflows', [PlatformIncidentWorkflowController::class, 'index'])
                ->where('incident_key', '[A-Za-z0-9_.]+')
                ->middleware('throttle:60,1');
        });

        Route::post('/platform/intelligence/incidents/{incident_key}/workflows/execute', [PlatformIncidentWorkflowController::class, 'execute'])
            ->middleware(['platform.permission:platform.intelligence.guided_workflows.execute', 'throttle:20,1'])
            ->where('incident_key', '[A-Za-z0-9_.]+');

        Route::middleware(['platform.permission:platform.intelligence.incidents.read'])->group(function () {
            Route::get('/platform/intelligence/command-surface', [PlatformIntelligenceCommandSurfaceController::class, 'commandSurface'])
                ->middleware('throttle:60,1');
            Route::get('/platform/intelligence/incidents/{incident_key}/correlation', [PlatformIntelligenceCommandSurfaceController::class, 'incidentCorrelation'])
                ->where('incident_key', '[A-Za-z0-9_.]+')
                ->middleware('throttle:60,1');

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.view'])->group(function () {
                Route::get('/platform/intelligence/incidents/{incident_key}/controlled-actions', [PlatformControlledActionController::class, 'index'])
                    ->where('incident_key', '[A-Za-z0-9_.]+')
                    ->middleware('throttle:60,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.create_follow_up'])->group(function () {
                Route::post('/platform/intelligence/incidents/{incident_key}/controlled-actions/create-follow-up', [PlatformControlledActionController::class, 'createFollowUp'])
                    ->where('incident_key', '[A-Za-z0-9_.]+')
                    ->middleware('throttle:30,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.request_human_review'])->group(function () {
                Route::post('/platform/intelligence/incidents/{incident_key}/controlled-actions/request-human-review', [PlatformControlledActionController::class, 'requestHumanReview'])
                    ->where('incident_key', '[A-Za-z0-9_.]+')
                    ->middleware('throttle:20,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.link_task_reference'])->group(function () {
                Route::post('/platform/intelligence/incidents/{incident_key}/controlled-actions/link-internal-task-reference', [PlatformControlledActionController::class, 'linkInternalTaskReference'])
                    ->where('incident_key', '[A-Za-z0-9_.]+')
                    ->middleware('throttle:20,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.assign_owner'])->group(function () {
                Route::post('/platform/intelligence/controlled-actions/{action_id}/assign-owner', [PlatformControlledActionController::class, 'assignOwner'])
                    ->where('action_id', '[0-9a-fA-F\\-]{36}')
                    ->middleware('throttle:40,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.schedule'])->group(function () {
                Route::post('/platform/intelligence/controlled-actions/{action_id}/schedule-follow-up-window', [PlatformControlledActionController::class, 'schedule'])
                    ->where('action_id', '[0-9a-fA-F\\-]{36}')
                    ->middleware('throttle:30,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.complete'])->group(function () {
                Route::post('/platform/intelligence/controlled-actions/{action_id}/mark-completed', [PlatformControlledActionController::class, 'complete'])
                    ->where('action_id', '[0-9a-fA-F\\-]{36}')
                    ->middleware('throttle:30,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.cancel'])->group(function () {
                Route::post('/platform/intelligence/controlled-actions/{action_id}/cancel', [PlatformControlledActionController::class, 'cancel'])
                    ->where('action_id', '[0-9a-fA-F\\-]{36}')
                    ->middleware('throttle:30,1');
            });

            Route::middleware(['platform.permission:platform.intelligence.controlled_actions.reopen'])->group(function () {
                Route::post('/platform/intelligence/controlled-actions/{action_id}/reopen', [PlatformControlledActionController::class, 'reopen'])
                    ->where('action_id', '[0-9a-fA-F\\-]{36}')
                    ->middleware('throttle:20,1');
            });
        });

        Route::middleware(['platform.permission:platform.companies.operational'])->group(function () {
            Route::patch('/platform/companies/{id}/operational', [PlatformAdminController::class, 'updateOperational'])
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.tenant_nav.manage'])->group(function () {
            Route::get('/platform/tenant-nav-hides', [PlatformTenantNavHideController::class, 'index'])
                ->middleware('throttle:60,1');
            Route::post('/platform/tenant-nav-hides', [PlatformTenantNavHideController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::delete('/platform/tenant-nav-hides/{id}', [PlatformTenantNavHideController::class, 'destroy'])
                ->whereNumber('id')
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.subscription.manage'])->group(function () {
            Route::post('/platform/plans', [SaasController::class, 'createPlan']);
            Route::patch('/platform/companies/{id}/subscription', [PlatformAdminController::class, 'updateSubscription'])
                ->middleware('throttle:30,1');
            Route::put('/platform/plans/{slug}', [SaasController::class, 'updatePlan']);
            Route::post('/platform/companies/{id}/subscription-addons', [PlatformAdminController::class, 'syncCompanySubscriptionAddon'])
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.subscription.manage'])->prefix('admin/subscriptions')->group(function () {
            Route::get('/attention-summary', [PlatformSubscriptionOperationsController::class, 'attentionSummary'])
                ->middleware('throttle:120,1');
            Route::get('/list', [PlatformSubscriptionOperationsController::class, 'subscriptionList'])
                ->middleware('throttle:60,1');
            Route::get('/subscription/{subscription}', [PlatformSubscriptionOperationsController::class, 'subscriptionShow'])
                ->whereNumber('subscription')
                ->middleware('throttle:60,1');
            Route::get('/invoices', [PlatformSubscriptionOperationsController::class, 'invoiceList'])
                ->middleware('throttle:60,1');
            Route::get('/invoices/{invoice}', [PlatformSubscriptionOperationsController::class, 'invoiceShow'])
                ->whereNumber('invoice')
                ->middleware('throttle:60,1');
            Route::get('/payment-orders/{id}', [PlatformSubscriptionOperationsController::class, 'paymentOrderShow'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
            Route::get('/bank-transactions/{id}', [PlatformSubscriptionOperationsController::class, 'bankTransactionShow'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
            Route::get('/debug/health', PlatformSubscriptionsDebugHealthController::class)
                ->middleware('throttle:30,1');
            Route::get('/overview', [PlatformSubscriptionOverviewController::class, 'overview'])
                ->middleware('throttle:60,1');
            Route::get('/review-queue', [PlatformReviewQueueController::class, 'index'])
                ->middleware('throttle:60,1');
            Route::get('/transactions', [PlatformSubscriptionOverviewController::class, 'transactions'])
                ->middleware('throttle:60,1');
            Route::get('/wallets', [PlatformSubscriptionOverviewController::class, 'wallets'])
                ->middleware('throttle:60,1');
            Route::get('/notifications', [PlatformSubscriptionOverviewController::class, 'notifications'])
                ->middleware('throttle:120,1');
            Route::get('/insights', [PlatformSubscriptionOverviewController::class, 'insights'])
                ->middleware('throttle:60,1');
            Route::post('/review-queue/{id}/match', [PlatformReviewQueueController::class, 'match'])
                ->whereNumber('id')
                ->middleware('throttle:30,1');
            Route::post('/review-queue/{id}/reject', [PlatformReviewQueueController::class, 'reject'])
                ->whereNumber('id')
                ->middleware('throttle:30,1');
            Route::post('/bank-transactions/import', [PlatformBankTransactionImportController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::post('/payment-orders/{id}/approve', [PlatformPaymentOrderController::class, 'approve'])
                ->whereNumber('id')
                ->middleware('throttle:30,1');
            Route::post('/payment-orders/{id}/reject', [PlatformPaymentOrderController::class, 'reject'])
                ->whereNumber('id')
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.pricing.view'])->prefix('platform/pricing')->group(function () {
            Route::get('/requests', [PlatformPricingRequestController::class, 'index'])
                ->middleware('throttle:60,1');
            Route::get('/requests/{uuid}', [PlatformPricingRequestController::class, 'show'])
                ->whereUuid('uuid')
                ->middleware('throttle:60,1');
            Route::get('/customer-price-versions', [PlatformCustomerPriceVersionController::class, 'index'])
                ->middleware('throttle:60,1');
        });

        Route::middleware(['platform.permission:platform.pricing.create'])->prefix('platform/pricing')->group(function () {
            Route::post('/requests', [PlatformPricingRequestController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::post('/requests/{uuid}/submit-for-review', [PlatformPricingRequestController::class, 'submitForReview'])
                ->whereUuid('uuid')
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.pricing.review'])->prefix('platform/pricing')->group(function () {
            Route::post('/requests/{uuid}/begin-review', [PlatformPricingRequestController::class, 'beginReview'])
                ->whereUuid('uuid')
                ->middleware('throttle:30,1');
            Route::post('/requests/{uuid}/complete-review', [PlatformPricingRequestController::class, 'completeReview'])
                ->whereUuid('uuid')
                ->middleware('throttle:30,1');
            Route::post('/requests/{uuid}/escalate', [PlatformPricingRequestController::class, 'escalate'])
                ->whereUuid('uuid')
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.pricing.approve'])->prefix('platform/pricing')->group(function () {
            Route::post('/requests/{uuid}/approve', [PlatformPricingRequestController::class, 'approve'])
                ->whereUuid('uuid')
                ->middleware('throttle:20,1');
            Route::post('/requests/{uuid}/reject', [PlatformPricingRequestController::class, 'reject'])
                ->whereUuid('uuid')
                ->middleware('throttle:20,1');
            Route::post('/requests/{uuid}/return-for-edit', [PlatformPricingRequestController::class, 'returnForEdit'])
                ->whereUuid('uuid')
                ->middleware('throttle:20,1');
        });

        Route::middleware(['platform.permission:platform.providers.manage'])->prefix('platform/providers')->group(function () {
            Route::get('/', [PlatformServiceProviderController::class, 'index'])
                ->middleware('throttle:60,1');
            Route::post('/', [PlatformServiceProviderController::class, 'store'])
                ->middleware('throttle:30,1');
            Route::get('/{id}/costs', [PlatformServiceProviderController::class, 'costs'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
            Route::post('/{id}/costs', [PlatformServiceProviderController::class, 'storeCost'])
                ->whereNumber('id')
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.purchase_claims.read'])->group(function () {
            Route::get('/platform/purchase-claims', [PlatformPurchaseClaimsController::class, 'index'])
                ->middleware('throttle:60,1');
        });

        Route::middleware(['platform.permission:platform.purchase_claims.review'])->group(function () {
            Route::patch('/platform/purchase-claims/{id}/review', [PlatformPurchaseClaimsController::class, 'review'])
                ->whereNumber('id')
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.vertical.assign'])->group(function () {
            Route::patch('/platform/companies/{id}/vertical-profile', [PlatformAdminController::class, 'assignVerticalProfile'])
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.financial_model.manage'])->group(function () {
            Route::patch('/platform/companies/{id}/financial-model', [PlatformAdminController::class, 'updateFinancialModel']);
        });

        Route::middleware(['platform.permission:platform.catalog.manage'])->group(function () {
            Route::put('/platform/plans/{slug}', [PlatformAdminController::class, 'updatePlanCatalog'])
                ->middleware('throttle:30,1');
            Route::post('/platform/plan-addons', [PlatformAdminController::class, 'storePlanAddonCatalog'])
                ->middleware('throttle:30,1');
            Route::put('/platform/plan-addons/{slug}', [PlatformAdminController::class, 'updatePlanAddonCatalog'])
                ->middleware('throttle:30,1');
        });

        Route::middleware(['platform.permission:platform.cancellations.read'])->group(function () {
            Route::get('/platform/work-order-cancellation-requests', [PlatformAdminController::class, 'workOrderCancellationRequests']);
        });

        Route::middleware(['platform.permission:platform.cancellations.manage'])->group(function () {
            Route::post('/platform/work-order-cancellation-requests/{id}/approve', [PlatformAdminController::class, 'approveWorkOrderCancellation']);
            Route::post('/platform/work-order-cancellation-requests/{id}/reject', [PlatformAdminController::class, 'rejectWorkOrderCancellation']);
        });

        Route::middleware(['platform.permission:platform.announcement.read'])->group(function () {
            Route::get('/platform/announcement-banner/admin', [PlatformAnnouncementBannerController::class, 'adminShow']);
        });

        Route::middleware(['platform.permission:platform.announcement.manage'])->group(function () {
            Route::put('/platform/announcement-banner', [PlatformAnnouncementBannerController::class, 'update']);
        });

        Route::middleware(['platform.permission:platform.registration.read'])->group(function () {
            Route::get('/platform/registration-profiles', [PlatformPhoneRegistrationReviewController::class, 'index']);
        });

        Route::middleware(['platform.permission:platform.registration.manage'])->group(function () {
            Route::post('/platform/registration-profiles/{id}/approve', [PlatformPhoneRegistrationReviewController::class, 'approve']);
            Route::post('/platform/registration-profiles/{id}/reject', [PlatformPhoneRegistrationReviewController::class, 'reject']);
            Route::post('/platform/registration-profiles/{id}/request-more-info', [PlatformPhoneRegistrationReviewController::class, 'requestMoreInfo']);
            Route::post('/platform/registration-profiles/{id}/suspend', [PlatformPhoneRegistrationReviewController::class, 'suspend']);
        });

        Route::middleware(['platform.permission:platform.support.read'])->group(function () {
            Route::get('/platform/support/tickets', [PlatformSupportController::class, 'indexTickets'])
                ->middleware('throttle:120,1');
            Route::get('/platform/support/tickets/{id}', [PlatformSupportController::class, 'showTicket'])
                ->whereNumber('id')
                ->middleware('throttle:120,1');
            Route::get('/platform/support/stats', [PlatformSupportController::class, 'stats'])
                ->middleware('throttle:60,1');
        });

        Route::middleware(['platform.permission:platform.notifications.read|platform.subscription.manage'])->group(function () {
            Route::get('/platform/notifications', [PlatformNotificationCenterController::class, 'index'])
                ->middleware('throttle:120,1');
        });

        Route::middleware(['platform.permission:platform.support.manage'])->group(function () {
            Route::patch('/platform/support/tickets/{id}', [PlatformSupportController::class, 'updateTicket'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
            Route::patch('/platform/support/tickets/{id}/status', [PlatformSupportController::class, 'changeStatus'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
            Route::post('/platform/support/tickets/{id}/replies', [PlatformSupportController::class, 'storeReply'])
                ->whereNumber('id')
                ->middleware('throttle:60,1');
        });

        Route::prefix('reporting')->group(function () {
            Route::middleware(['platform.permission:platform.reporting.read'])->group(function () {
                Route::get('/v1/platform/pulse-summary', [PlatformReportingController::class, 'pulseSummary']);
            });
            Route::middleware(['platform.permission:platform.reporting.export'])->group(function () {
                Route::get('/v1/platform/pulse-summary/export', [ReportingExportController::class, 'platformPulseSummary']);
            });
        });
    });

    Route::middleware(['auth:sanctum', 'permission:phone_registration.flow'])->group(function () {
        Route::post('/auth/complete-account-type', [PhoneRegistrationFlowController::class, 'completeAccountType']);
        Route::post('/auth/complete-individual-profile', [PhoneRegistrationFlowController::class, 'completeIndividualProfile']);
        Route::post('/auth/complete-company-profile', [PhoneRegistrationFlowController::class, 'completeCompanyProfile']);
    });

    /**
     * QA — نفس حزمة الوسائط الإدارية: اشتراك فوري + نطاق فرع (بدون استثناء صامت).
     */
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription', 'permission:users.update'])->group(function () {
        Route::post('/internal/run-tests', [QaValidationController::class, 'run'])->name('internal.qa.run-tests');
        Route::get('/internal/test-results', [QaValidationController::class, 'results'])->name('internal.qa.test-results');
        Route::get('/internal/auth/suspicious-login-signals', [AuthSuspiciousLoginSignalsController::class, 'index'])
            ->name('internal.auth.suspicious-login-signals');
    });

    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {

        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        /** قدرات النظام — قراءة فقط، مُقيّد بالمستأجر والدور؛ يحد من إساءة الاستخدام */
        Route::get('/system/capabilities', [SystemCapabilitiesController::class, 'index'])
            ->middleware('throttle:120,1');
        Route::get('/onboarding/setup-status', [OnboardingController::class, 'setupStatus']);

        /** Phase 1 intelligence — inspection only (feature-flagged, admin). */
        Route::prefix('internal')->middleware('intelligent.internal')->group(function () {
            Route::get('/domain-events', [DomainEventInspectionController::class, 'index']);
        });

        /** Phase 2 — read-only analytics (master + per-endpoint flags, admin). */
        Route::prefix('internal')->middleware(['intelligent.internal', 'intelligent.phase2'])->group(function () {
            Route::get('/intelligence/overview', [Phase2IntelligenceController::class, 'overview']);
            Route::get('/intelligence/insights', [Phase2IntelligenceController::class, 'insights']);
            Route::get('/intelligence/recommendations', [Phase2IntelligenceController::class, 'recommendations']);
            Route::get('/intelligence/alerts', [Phase2IntelligenceController::class, 'alerts']);
            Route::get('/intelligence/command-center', [Phase2IntelligenceController::class, 'commandCenter']);
            Route::post('/intelligence/command-center/governance', [CommandCenterGovernanceController::class, 'store']);
            Route::get('/intelligence/command-center/governance/history', [CommandCenterGovernanceController::class, 'history']);
        });

        Route::get('/companies/{id}/profile', [CompanyProfileController::class, 'show'])
            ->whereNumber('id');
        Route::get('/platform/announcement-banner', [PlatformAnnouncementBannerController::class, 'show']);
        Route::apiResource('companies', CompanyController::class);
        Route::post('/companies/{id}/logo', [CompanyController::class, 'uploadLogo']);
        Route::post('/companies/{id}/signature', [CompanyController::class, 'uploadSignature']);
        Route::delete('/companies/{id}/signature', [CompanyController::class, 'deleteSignature']);
        Route::post('/companies/{id}/stamp', [CompanyController::class, 'uploadStamp']);
        Route::delete('/companies/{id}/stamp', [CompanyController::class, 'deleteStamp']);
        Route::get('/companies/{id}/settings', [CompanyController::class, 'getSettings']);
        Route::patch('/companies/{id}/settings', [CompanyController::class, 'updateSettings']);
        Route::post('/companies/{id}/settings/test-channel', [CompanyController::class, 'testIntegrationChannel'])
            ->middleware('permission:users.update');
        Route::post('/companies/{id}/pos/test-connection', [CompanyController::class, 'testPosConnection'])
            ->middleware(['permission:users.update', 'throttle:20,1']);
        Route::get('/companies/{id}/feature-profile', [CompanyController::class, 'featureProfile']);
        Route::patch('/companies/{id}/feature-profile', [CompanyController::class, 'updateFeatureProfile']);
        Route::get('/companies/{id}/navigation-visibility', [CompanyController::class, 'navigationVisibility']);
        Route::patch('/companies/{id}/navigation-visibility', [CompanyController::class, 'updateNavigationVisibility']);
        Route::patch('/companies/{id}/vertical-profile', [CompanyController::class, 'assignVerticalProfile'])
            ->middleware('permission:config_profiles.manage');
        Route::get('/companies/{id}/effective-config', [CompanyController::class, 'effectiveConfig'])
            ->middleware('permission:config_profiles.view');
        Route::apiResource('branches', BranchController::class);
        Route::patch('/branches/{id}/vertical-profile', [BranchController::class, 'assignVerticalProfile'])
            ->middleware('permission:config_profiles.manage');
        Route::get('/branches/{id}/effective-config', [BranchController::class, 'effectiveConfig'])
            ->middleware('permission:config_profiles.view');
        Route::apiResource('users', UserController::class);

        // ── Notifications / Sharing ──
        Route::post('/notifications/share-email', [NotificationController::class, 'shareEmail'])
            ->middleware('permission:users.update');
        Route::post('/notifications/track-share', [NotificationController::class, 'trackShare'])
            ->middleware('permission:users.update');

        Route::middleware('permission:users.update')->group(function () {
            Route::get('/roles/{id}/assign', [RoleController::class, 'assign']);
            Route::post('/roles/{id}/assign', [RoleController::class, 'assign']);
            Route::apiResource('roles', RoleController::class);
            Route::get('/permissions', [PermissionController::class, 'index']);
            Route::get('/permissions/my', [PermissionController::class, 'my']);
        });

        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])
                ->middleware('permission:subscriptions.view');
            Route::get('/current', [SubscriptionController::class, 'current'])
                ->middleware('permission:subscriptions.view');
            Route::post('/renew', [SubscriptionController::class, 'renew'])
                ->middleware('permission:subscriptions.manage');
        });

        Route::post('/customers', [CustomerController::class, 'store'])
            ->middleware('permission:customers.create');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])
            ->middleware('permission:customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
            ->middleware('permission:customers.delete');
        Route::get('/customers/{id}/profile', [CustomerProfileController::class, 'show'])
            ->whereNumber('id')
            ->middleware('permission:customers.view');
        Route::apiResource('customers', CustomerController::class)->only(['index', 'show']);

        Route::post('/vehicles', [VehicleController::class, 'store'])
            ->middleware('permission:vehicles.create');
        Route::get('/vehicles/resolve-plate', [VehicleController::class, 'resolvePlate'])
            ->middleware('permission:vehicles.view');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])
            ->middleware('permission:vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])
            ->middleware('permission:vehicles.delete');
        Route::apiResource('vehicles', VehicleController::class)->only(['index', 'show']);
        Route::get('vehicles/{id}/digital-card', [VehicleController::class, 'digitalCard']);
        Route::post('/vehicle-identity/resolve', [VehicleIdentityController::class, 'resolve']);
        Route::post('/vehicles/{id}/identity/rotate', [VehicleIdentityController::class, 'rotate'])
            ->middleware('permission:vehicles.update');
        Route::post('/vehicles/{id}/identity/revoke', [VehicleIdentityController::class, 'revoke'])
            ->middleware('permission:vehicles.update');
        Route::post('/vehicles/{id}/identity/issue', [VehicleIdentityController::class, 'issue'])
            ->middleware('permission:vehicles.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])
            ->middleware('permission:users.update');
        Route::apiResource('services', ServiceController::class)->except(['destroy']);
        Route::delete('/bundles/{bundle}', [BundleController::class, 'destroy'])
            ->middleware('permission:users.update');
        Route::apiResource('bundles', BundleController::class)->except(['destroy']);

        Route::get('/customer-groups', [CustomerGroupController::class, 'index'])
            ->middleware('permission:customer_groups.view');
        Route::post('/customer-groups', [CustomerGroupController::class, 'store'])
            ->middleware('permission:customer_groups.manage');
        Route::put('/customer-groups/{id}', [CustomerGroupController::class, 'update'])
            ->middleware('permission:customer_groups.manage');
        Route::delete('/customer-groups/{id}', [CustomerGroupController::class, 'destroy'])
            ->middleware('permission:customer_groups.manage');

        Route::get('/service-pricing-policies', [ServicePricingPolicyController::class, 'index'])
            ->middleware('permission:pricing_policies.view');
        Route::post('/service-pricing-policies', [ServicePricingPolicyController::class, 'store'])
            ->middleware('permission:pricing_policies.manage');
        Route::put('/service-pricing-policies/{id}', [ServicePricingPolicyController::class, 'update'])
            ->middleware('permission:pricing_policies.manage');
        Route::delete('/service-pricing-policies/{id}', [ServicePricingPolicyController::class, 'destroy'])
            ->middleware('permission:pricing_policies.manage');

        Route::prefix('work-orders')->group(function () {
            Route::get('/intake-lookup', [WorkOrderController::class, 'intakeLookup'])
                ->middleware('permission:work_orders.view');
            Route::post('/intake-lookup-camera', [WorkOrderController::class, 'intakeLookupCamera'])
                ->middleware('permission:work_orders.view');
            Route::post('/intake-odometer-ocr', [WorkOrderController::class, 'intakeOdometerOcr'])
                ->middleware('permission:work_orders.view');
            Route::post('/line-pricing-preview', [WorkOrderController::class, 'linePricingPreview'])
                ->middleware('permission:work_orders.view');
            Route::get('/', [WorkOrderController::class, 'index'])
                ->middleware('permission:work_orders.view');
            Route::post('/', [WorkOrderController::class, 'store'])
                ->middleware('permission:work_orders.create');
            Route::post('/bulk', [WorkOrderBulkController::class, 'store'])
                ->middleware('permission:work_orders.create');
            Route::get('/batches/{batchUuid}', [WorkOrderBulkController::class, 'showBatch'])
                ->where('batchUuid', '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$')
                ->middleware('permission:work_orders.view');
            Route::post('/batches', [WorkOrderBatchController::class, 'store'])
                ->middleware('permission:work_orders.create');
            Route::get('/{id}/pdf', [WorkOrderController::class, 'downloadPdf'])
                ->middleware('permission:work_orders.view');
            Route::get('/{id}/share-links', [WorkOrderController::class, 'shareLinks'])
                ->middleware('permission:work_orders.view');
            Route::post('/{id}/share-email', [WorkOrderController::class, 'shareEmail'])
                ->middleware('permission:work_orders.view');
            Route::post('/{id}/share-whatsapp-driver', [WorkOrderController::class, 'shareWhatsAppDriver'])
                ->middleware('permission:work_orders.view');
            Route::post('/{id}/service-media', [WorkOrderController::class, 'uploadServiceMedia'])
                ->middleware('permission:work_orders.update');
            Route::patch('/{id}/execution-report', [WorkOrderController::class, 'updateExecutionReport'])
                ->middleware('permission:work_orders.update');
            Route::get('/{id}', [WorkOrderController::class, 'show'])
                ->middleware('permission:work_orders.view');
            Route::put('/{id}', [WorkOrderController::class, 'update'])
                ->middleware('permission:work_orders.update');
            Route::patch('/{id}/status', [WorkOrderController::class, 'updateStatus'])
                ->middleware('permission:work_orders.update');
            Route::post('/{id}/cancellation-requests', [WorkOrderCancellationRequestController::class, 'store'])
                ->middleware('permission:work_orders.update');
            Route::delete('/{id}', [WorkOrderController::class, 'destroy'])
                ->middleware('permission:work_orders.delete');
        });

        Route::post('/work-order-cancellation-requests/{id}/approve', [WorkOrderCancellationRequestController::class, 'approve'])
            ->middleware('permission:work_orders.update');
        Route::post('/work-order-cancellation-requests/{id}/reject', [WorkOrderCancellationRequestController::class, 'reject'])
            ->middleware('permission:work_orders.update');

        Route::post('/sensitive-operations/preview', [SensitiveOperationPreviewController::class, 'preview'])
            ->middleware('permission:work_orders.view');

        Route::middleware('idempotent')->group(function () {
            Route::post('/invoices', [InvoiceController::class, 'store'])
                ->middleware('permission:invoices.create');
            Route::post('/invoices/{id}/pay', [InvoiceController::class, 'pay'])
                ->middleware('permission:invoices.update');
            Route::post('/pos/sale', [POSController::class, 'sale'])
                ->middleware('permission:invoices.create');
            Route::post('/invoices/from-work-order/{workOrderId}', [InvoiceController::class, 'fromWorkOrder'])
                ->middleware('permission:invoices.create');
        });

        Route::get('/invoices', [InvoiceController::class, 'index'])
            ->middleware('permission:invoices.view');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
            ->middleware('permission:invoices.view');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
            ->middleware('permission:invoices.view');
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])
            ->middleware('permission:invoices.update');
        Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update'])
            ->middleware('permission:invoices.update');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])
            ->middleware('permission:invoices.delete');
        Route::post('/invoices/{id}/media', [InvoiceController::class, 'uploadMedia'])
            ->middleware('permission:invoices.update');

        /** استخراج بيانات من صورة فاتورة (OCR) — لمن يُنشئ فواتير دون صلاحية users.update */
        Route::post('/invoices/ocr-extract', [OcrController::class, 'scanInvoice'])
            ->middleware('permission:invoices.create');

        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletController::class, 'show'])
                ->middleware('permission:invoices.view');
            Route::get('/transactions', [WalletController::class, 'transactions'])
                ->middleware('permission:invoices.view');
            Route::middleware('idempotent')->group(function () {
                Route::post('/top-up', [WalletController::class, 'topUp'])
                    ->middleware('permission:invoices.update');
                Route::post('/transfer', [WalletController::class, 'transfer'])
                    ->middleware('permission:invoices.update');
            });
            Route::post('/transactions/{id}/reverse', [WalletController::class, 'reverse'])
                ->middleware('permission:invoices.update');
        });

        /** طلبات شحن المحفظة — اعتماد الإدارة قبل إضافة الرصيد */
        Route::prefix('wallet-top-up-requests')->group(function () {
            Route::post('/', [WalletTopUpRequestController::class, 'store'])
                ->middleware('permission:wallet.top_up_requests.create');
            Route::get('/my', [WalletTopUpRequestController::class, 'my'])
                ->middleware('permission:wallet.top_up_requests.view');
            Route::get('/{id}/receipt', [WalletTopUpRequestController::class, 'receipt']);
            Route::get('/{id}/transfer-instructions', [WalletTopUpRequestController::class, 'transferInstructions']);
            Route::get('/{id}', [WalletTopUpRequestController::class, 'show']);
            Route::patch('/{id}', [WalletTopUpRequestController::class, 'update'])
                ->middleware('permission:wallet.top_up_requests.create');
            Route::post('/{id}/resubmit', [WalletTopUpRequestController::class, 'resubmit'])
                ->middleware('permission:wallet.top_up_requests.create');
        });

        Route::prefix('admin/wallet-top-up-requests')->group(function () {
            Route::get('/', [AdminWalletTopUpRequestController::class, 'index'])
                ->middleware('permission:wallet.top_up_requests.review');
            Route::post('/{id}/approve', [AdminWalletTopUpRequestController::class, 'approve'])
                ->middleware('permission:wallet.top_up_requests.review');
            Route::post('/{id}/reject', [AdminWalletTopUpRequestController::class, 'reject'])
                ->middleware('permission:wallet.top_up_requests.review');
            Route::post('/{id}/return', [AdminWalletTopUpRequestController::class, 'returnForRevision'])
                ->middleware('permission:wallet.top_up_requests.review');
        });

        Route::prefix('payments')->group(function () {
            Route::get('/invoice/{invoiceId}', [WalletController::class, 'paymentsByInvoice'])
                ->middleware('permission:invoices.view');
            Route::post('/{id}/refund', [WalletController::class, 'refundPayment'])
                ->middleware(['idempotent', 'permission:invoices.update']);
        });

        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:products.delete');
        Route::apiResource('products', ProductController::class)->except(['destroy']);
        Route::post('/quotes', [QuoteController::class, 'store'])
            ->middleware('permission:users.update');
        Route::put('/quotes/{quote}', [QuoteController::class, 'update'])
            ->middleware('permission:users.update');
        Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])
            ->middleware('permission:users.update');
        Route::apiResource('quotes', QuoteController::class)->only(['index', 'show']);
        Route::get('/nps', [NpsController::class, 'index']);
        Route::post('/nps', [NpsController::class, 'store'])
            ->middleware('permission:users.update');
        Route::get('/warranty-items', fn (Request $r) => response()->json(['data' => WarrantyItem::where('company_id', $r->user()->company_id)->orderByDesc('warranty_end')->paginate(20)]));
        Route::get('/service-reminders', fn (Request $r) => response()->json(['data' => ServiceReminder::where('company_id', $r->user()->company_id)->orderBy('next_service_date')->paginate(20)]));

        Route::prefix('units')->group(function () {
            Route::get('/', [UnitController::class, 'index']);
            Route::post('/', [UnitController::class, 'store']);
            Route::put('/{id}', [UnitController::class, 'update'])
                ->middleware('permission:inventory.adjust');
            Route::delete('/{id}', [UnitController::class, 'destroy'])
                ->middleware('permission:inventory.adjust');
            Route::get('/conversions', [UnitController::class, 'conversions']);
            Route::post('/conversions', [UnitController::class, 'storeConversion'])
                ->middleware('permission:inventory.adjust');
        });

        Route::post('/suppliers', [SupplierController::class, 'store'])
            ->middleware('permission:suppliers.create');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
            ->middleware('permission:suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware('permission:suppliers.delete');
        Route::apiResource('suppliers', SupplierController::class)->only(['index', 'show']);

        Route::middleware(['permission:org_units.view', 'business.feature:org_structure'])->group(function () {
            Route::get('/org-units', [OrgUnitController::class, 'index']);
            Route::get('/org-units/tree', [OrgUnitController::class, 'tree']);
        });
        Route::post('/org-units', [OrgUnitController::class, 'store'])
            ->middleware(['permission:org_units.create', 'business.feature:org_structure']);
        Route::put('/org-units/{id}', [OrgUnitController::class, 'update'])
            ->middleware(['permission:org_units.update', 'business.feature:org_structure']);
        Route::delete('/org-units/{id}', [OrgUnitController::class, 'destroy'])
            ->middleware(['permission:org_units.delete', 'business.feature:org_structure']);

        Route::middleware(['permission:suppliers.view', 'business.feature:supplier_contract_mgmt'])->group(function () {
            Route::get('/suppliers/{supplierId}/contracts', [SupplierContractController::class, 'index']);
            Route::get('/suppliers/{supplierId}/contracts/{contractId}/download', [SupplierContractController::class, 'download']);
        });
        Route::post('/suppliers/{supplierId}/contracts', [SupplierContractController::class, 'store'])
            ->middleware(['permission:suppliers.update', 'business.feature:supplier_contract_mgmt']);
        Route::delete('/suppliers/{supplierId}/contracts/{contractId}', [SupplierContractController::class, 'destroy'])
            ->middleware(['permission:suppliers.update', 'business.feature:supplier_contract_mgmt']);

        Route::prefix('purchase-claims')->group(function () {
            Route::get('/', [PurchaseClaimController::class, 'index'])
                ->middleware('permission:purchases.claims.view');
            Route::post('/', [PurchaseClaimController::class, 'store'])
                ->middleware('permission:purchases.claims.create');
            Route::patch('/{id}/review', [PurchaseClaimController::class, 'review'])
                ->middleware('permission:purchases.claims.review');
        });

        Route::prefix('purchases')->group(function () {
            Route::get('/', [PurchaseController::class, 'index']);
            Route::post('/', [PurchaseController::class, 'store'])
                ->middleware('permission:purchases.create');
            /** OCR لصور فواتير الموردين — دون صلاحية users.update (مسار الحوكمة القديم) */
            Route::post('/ocr-extract', [OcrController::class, 'scanInvoice'])
                ->middleware('permission:purchases.create');
            Route::get('/{id}', [PurchaseController::class, 'show']);
            Route::patch('/{id}/status', [PurchaseController::class, 'updateStatus'])
                ->middleware('permission:purchases.create');
            Route::post('/{id}/receive', [PurchaseController::class, 'receive'])
                ->middleware('permission:purchases.create');
            Route::post('/{id}/documents', [PurchaseController::class, 'uploadDocument'])
                ->middleware('permission:purchases.create');
            Route::delete('/{id}/documents/{index}', [PurchaseController::class, 'deleteDocument'])
                ->middleware('permission:purchases.create');
            Route::get('/{id}/receipts', [GoodsReceiptController::class, 'byPurchase']);
            Route::post('/{id}/receipts', [GoodsReceiptController::class, 'store'])
                ->middleware('permission:purchases.create');
        });

        Route::prefix('goods-receipts')->group(function () {
            Route::get('/', [GoodsReceiptController::class, 'index']);
            Route::get('/{id}', [GoodsReceiptController::class, 'show']);
        });

        Route::prefix('inventory')->group(function () {
            Route::get('/', [InventoryController::class, 'index']);
            Route::get('/movements', [InventoryController::class, 'movements']);
            Route::post('/adjust', [InventoryController::class, 'adjust'])->middleware('idempotent');
            Route::get('/{id}', [InventoryController::class, 'show']);

            Route::prefix('reservations')->group(function () {
                Route::get('/', [InventoryController::class, 'reservations']);
                Route::post('/', [InventoryController::class, 'createReservation'])
                    ->middleware('permission:inventory.adjust');
                Route::patch('/{id}/consume', [InventoryController::class, 'consumeReservation'])
                    ->middleware('permission:inventory.adjust');
                Route::patch('/{id}/release', [InventoryController::class, 'releaseReservation'])
                    ->middleware('permission:inventory.adjust');
                Route::patch('/{id}/cancel', [InventoryController::class, 'cancelReservation'])
                    ->middleware('permission:inventory.adjust');
            });
        });

        Route::prefix('wallets')->group(function () {
            Route::get('/{customerId}/summary', [WalletController::class, 'summary'])
                ->middleware('permission:invoices.view');
            Route::get('/{walletId}/transactions', [WalletController::class, 'transactions'])
                ->middleware('permission:invoices.view');
            Route::post('/top-up/individual', [WalletController::class, 'topUpIndividual'])
                ->middleware(['idempotent', 'permission:invoices.update']);
            Route::post('/top-up/fleet', [WalletController::class, 'topUpFleet'])
                ->middleware(['idempotent', 'permission:invoices.update']);
            Route::post('/transfer', [WalletController::class, 'transfer'])
                ->middleware(['idempotent', 'permission:invoices.update']);
            Route::post('/reversal', [WalletController::class, 'reverse'])
                ->middleware(['idempotent', 'permission:invoices.update']);
        });

        Route::prefix('api-keys')->middleware('permission:api_keys.manage')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index']);
            Route::post('/', [ApiKeyController::class, 'store']);
            Route::patch('/{id}', [ApiKeyController::class, 'update']);
            Route::delete('/{id}', [ApiKeyController::class, 'revoke']);
        });

        Route::prefix('webhooks')->middleware('permission:webhooks.manage')->group(function () {
            Route::get('/', [WebhookController::class, 'index']);
            Route::post('/', [WebhookController::class, 'store']);
            Route::delete('/{id}', [WebhookController::class, 'destroy']);
            Route::get('/{id}/deliveries', [WebhookController::class, 'deliveries']);
        });

        Route::get('/api-usage-logs', function (Request $request) {
            $logs = ApiUsageLog::where('company_id', $request->user()->company_id)
                ->when($request->api_key_id, fn ($q) => $q->where('api_key_id', $request->api_key_id))
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 50));

            return response()->json(['data' => $logs, 'trace_id' => app('trace_id')]);
        });

        // Alias for frontend dashboard
        Route::get('/dashboard/kpi', [ReportController::class, 'kpi']);

        Route::prefix('reports')->middleware('permission:reports.view')->group(function () {
            Route::get('/sales', [ReportController::class, 'sales'])->middleware('permission:reports.financial.view');
            Route::get('/sales-by-customer', [ReportController::class, 'salesByCustomer'])->middleware('permission:reports.financial.view');
            Route::get('/sales-by-product', [ReportController::class, 'salesByProduct'])->middleware('permission:reports.financial.view');
            Route::get('/overdue-receivables', [ReportController::class, 'overdueReceivables'])->middleware('permission:reports.financial.view');
            Route::get('/work-orders', [ReportController::class, 'workOrders'])->middleware('permission:reports.operations.view');
            Route::get('/kpi', [ReportController::class, 'kpi'])->middleware('permission:reports.financial.view');
            Route::get('/summary', [ReportController::class, 'kpi'])->middleware('permission:reports.financial.view');
            Route::get('/kpi-dictionary', [ReportController::class, 'kpiDictionary']);
            Route::get('/vat', [ReportController::class, 'vatReport'])->middleware('permission:reports.accounting.view');
            Route::get('/inventory', [ReportController::class, 'inventory'])->middleware('permission:reports.operations.view');
            Route::get('/financial', [ReportController::class, 'financial'])->middleware('permission:reports.financial.view');
            Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->middleware('permission:reports.financial.view');
            Route::get('/purchases', [ReportController::class, 'purchasesReport'])->middleware('permission:reports.financial.view');
            Route::get('/receivables-aging', [ReportController::class, 'receivablesAging'])->middleware('permission:reports.financial.view');
            Route::get('/business-analytics', [ReportController::class, 'businessAnalytics'])->middleware('permission:reports.intelligence.view');
            Route::get('/employees', [ReportController::class, 'employeeReport'])->middleware('permission:reports.employees.view');
            Route::get('/operations', [ReportController::class, 'operationsReport'])->middleware('permission:reports.operations.view');
            Route::get('/intelligence-digest', [ReportController::class, 'intelligenceDigest'])->middleware('permission:reports.intelligence.view');
            Route::get('/communications', [ReportController::class, 'communicationsReport'])->middleware('permission:reports.operations.view');
            Route::get('/smart-tasks', [ReportController::class, 'smartTasksReport'])->middleware('permission:reports.operations.view');
        });

        /** WAVE 2 / PR7–PR8 — read-only reporting API (query layer; not legacy /reports/*). */
        Route::prefix('reporting')->group(function () {
            Route::middleware(['permission:reports.view', 'permission:reports.operations.view'])->group(function () {
                Route::get('/v1/operations/work-order-summary', [ReportingController::class, 'workOrderSummary']);
                Route::get('/v1/operations/work-order-summary/export', [ReportingExportController::class, 'workOrderSummary']);
                Route::get('/v1/company/pulse-summary', [CompanyReportingController::class, 'pulseSummary']);
                Route::get('/v1/company/pulse-summary/export', [ReportingExportController::class, 'companyPulseSummary']);
                Route::get('/v1/customer/pulse-summary', [CustomerReportingController::class, 'pulseSummary']);
                Route::get('/v1/customer/pulse-summary/export', [ReportingExportController::class, 'customerPulseSummary']);
                Route::get('/v1/operations/global-feed', [GlobalOperationsFeedController::class, 'index']);
                Route::get('/v1/operations/global-feed/export', [ReportingExportController::class, 'globalOperationsFeed']);
            });
        });

        // Wave 3 Batch-3: lightweight operational review layer for reconciliation
        Route::prefix('financial-reconciliation')->middleware('permission:reports.financial.view')->group(function () {
            Route::get('/latest', [FinancialReconciliationController::class, 'latest']);
            Route::get('/health', [FinancialReconciliationController::class, 'health']);
            Route::get('/runs', [FinancialReconciliationController::class, 'runs']);
            Route::get('/findings', [FinancialReconciliationController::class, 'findings']);
            Route::get('/findings/{id}', [FinancialReconciliationController::class, 'show']);
            Route::get('/summary', [FinancialReconciliationController::class, 'summary']);
            Route::patch('/findings/{id}/status', [FinancialReconciliationController::class, 'updateFindingStatus'])
                ->middleware('permission:users.update');
        });

        // Institutional capabilities Batch-2: Meetings MVP (low risk, no video/calendar integrations)
        Route::prefix('meetings')->group(function () {
            Route::get('/', [MeetingController::class, 'index'])
                ->middleware('permission:meetings.update');
            Route::get('/{id}', [MeetingController::class, 'show'])
                ->whereNumber('id')
                ->middleware('permission:meetings.update');
            Route::post('/', [MeetingController::class, 'store'])
                ->middleware('permission:meetings.create');
            Route::put('/{id}', [MeetingController::class, 'update'])
                ->middleware('permission:meetings.update');
            Route::post('/{id}/participants', [MeetingController::class, 'addParticipant'])
                ->middleware('permission:meetings.update');
            Route::delete('/{id}/participants/{participantId}', [MeetingController::class, 'removeParticipant'])
                ->middleware('permission:meetings.update');
            Route::post('/{id}/minutes', [MeetingController::class, 'addMinutes'])
                ->middleware('permission:meetings.update');
            Route::get('/{id}/minutes', [MeetingController::class, 'listMinutes'])
                ->middleware('permission:meetings.view_minutes');
            Route::post('/{id}/decisions', [MeetingController::class, 'addDecision'])
                ->middleware('permission:meetings.manage_actions');
            Route::post('/{id}/decisions/{decisionId}/approval/start', [MeetingController::class, 'startDecisionApproval'])
                ->middleware('permission:meetings.manage_actions');
            Route::get('/{id}/decisions/{decisionId}/approval-status', [MeetingController::class, 'decisionApprovalStatus'])
                ->middleware('permission:meetings.view_minutes');
            Route::post('/{id}/decisions/{decisionId}/approve', [MeetingController::class, 'approveDecision'])
                ->middleware(['permission:meetings.manage_actions', 'permission:users.update']);
            Route::post('/{id}/decisions/{decisionId}/reject', [MeetingController::class, 'rejectDecision'])
                ->middleware(['permission:meetings.manage_actions', 'permission:users.update']);
            Route::post('/{id}/actions', [MeetingController::class, 'addAction'])
                ->middleware('permission:meetings.manage_actions');
            Route::patch('/{id}/actions/{actionId}', [MeetingController::class, 'updateAction'])
                ->middleware('permission:meetings.manage_actions');
            Route::post('/{id}/actions/{actionId}/close', [MeetingController::class, 'closeAction'])
                ->middleware('permission:meetings.manage_actions');
            Route::post('/{id}/close', [MeetingController::class, 'close'])
                ->middleware('permission:meetings.close');
        });

        // Financial Core — Ledger & Chart of Accounts
        Route::prefix('ledger')->middleware('permission:reports.accounting.view')->group(function () {
            Route::get('/', [LedgerController::class, 'index']);
            Route::get('/trial-balance', [LedgerController::class, 'trialBalance']);
            Route::get('/{id}', [LedgerController::class, 'show']);
            Route::post('/{id}/reverse', [LedgerController::class, 'reverse']);
        });

        Route::apiResource('chart-of-accounts', ChartOfAccountController::class)
            ->middleware('permission:reports.accounting.view');

        // Fleet Wallet — read-only
        Route::get('/wallet/{customerId}/summary', [WalletController::class, 'summary'])
            ->middleware('permission:invoices.view');
        Route::get('/wallet/{walletId}/transactions', [WalletController::class, 'transactions'])
            ->middleware('permission:invoices.view');

        // Fleet Wallet — write (idempotent)
        Route::middleware('idempotent')->group(function () {
            Route::post('/wallet/top-up/individual', [WalletController::class, 'topUpIndividual'])
                ->middleware('permission:invoices.update');
            Route::post('/wallet/top-up/fleet', [WalletController::class, 'topUpFleet'])
                ->middleware('permission:invoices.update');
            Route::post('/wallet/transfer', [WalletController::class, 'transfer'])
                ->middleware('permission:invoices.update');
            Route::post('/wallet/reversal', [WalletController::class, 'reverse'])
                ->middleware('permission:invoices.update');
        });

        // Fleet Operations — Workshop Side (verify-plate only)
        Route::prefix('fleet')->group(function () {
            Route::get('/customers', [FleetController::class, 'fleetCustomers']);
            Route::post('/verify-plate', [FleetController::class, 'verifyPlate'])
                ->middleware('permission:fleet.plate.verify');
            // يتم الاعتماد الآن من Fleet Portal — هذا المسار لمركز الخدمة فقط (طوارئ)
            Route::post('/work-orders/{id}/approve', [FleetController::class, 'approveWorkOrder'])
                ->middleware('permission:work_orders.update');
        });

        // Fleet Portal — Customer Side (fleet_contact / fleet_manager only)
        Route::prefix('fleet-portal')->group(function () {
            Route::get('/dashboard', [FleetPortalController::class, 'dashboard']);
            Route::get('/service-catalog', [FleetPortalController::class, 'serviceCatalog']);
            Route::post('/work-orders/pricing-preview', [FleetPortalController::class, 'previewWorkOrderLinePrice'])
                ->middleware('permission:fleet.workorder.create');
            Route::get('/vehicles', [FleetPortalController::class, 'vehicles']);
            Route::get('/wallet/summary', [FleetPortalController::class, 'walletSummary']);
            Route::get('/wallet/transactions', [FleetPortalController::class, 'transactions']);
            Route::post('/wallet/top-up', [FleetPortalController::class, 'topUp'])
                ->middleware(['idempotent', 'permission:fleet.wallet.topup']);
            Route::get('/work-orders', [FleetPortalController::class, 'pendingApproval']);
            Route::post('/work-orders', [FleetPortalController::class, 'createWorkOrder'])
                ->middleware('permission:fleet.workorder.create');
            Route::get('/work-orders/pending-approval', [FleetPortalController::class, 'pendingApproval']);
            Route::get('/wallet', [FleetPortalController::class, 'walletSummary']);
            Route::post('/work-orders/{id}/approve-credit', [FleetPortalController::class, 'approveCredit'])
                ->middleware('permission:fleet.workorder.approve');
            Route::post('/work-orders/{id}/reject-credit', [FleetPortalController::class, 'rejectCredit'])
                ->middleware('permission:fleet.workorder.approve');
        });
    });

    // ── SaaS Plans (Phase 7) ─────────────────────────────────────────
    Route::get('/plans', [SaasController::class, 'listPlans']);
    Route::post('/plans/seed', [SaasController::class, 'seedPlans'])
        ->middleware(['auth:sanctum', 'permission:subscriptions.manage']);
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {
        Route::get('/subscription', [SaasController::class, 'currentSubscription']);
        Route::post('/subscription/change', [SaasController::class, 'changePlan'])
            ->middleware('permission:subscriptions.manage');
        Route::post('/subscription/addons', [SaasController::class, 'purchaseSubscriptionAddon'])
            ->middleware('permission:subscriptions.manage');
        Route::delete('/subscription/addons/{slug}', [SaasController::class, 'removeSubscriptionAddon'])
            ->middleware('permission:subscriptions.manage');
        Route::get('/subscription/usage', [SaasController::class, 'usageLimits'])
            ->middleware('permission:subscriptions.view');
        Route::put('/plans/{slug}', [SaasController::class, 'updatePlan'])
            ->middleware('permission:subscriptions.manage');

        Route::prefix('subscriptions')->group(function () {
            Route::get('/payment-orders', [TenantPaymentOrderController::class, 'index'])
                ->middleware(['permission:subscriptions.view', 'throttle:60,1']);
            Route::post('/payment-orders', [TenantPaymentOrderController::class, 'store'])
                ->middleware(['permission:subscriptions.manage', 'throttle:30,1']);
            Route::post('/payment-orders/{id}/submit-transfer', [TenantPaymentOrderController::class, 'submitTransfer'])
                ->whereNumber('id')
                ->middleware(['permission:subscriptions.manage', 'throttle:30,1']);
            Route::post('/payment-orders/{id}/upload-receipt', [TenantPaymentOrderController::class, 'uploadReceipt'])
                ->whereNumber('id')
                ->middleware(['permission:subscriptions.manage', 'throttle:30,1']);
            Route::get('/current', [TenantSubscriptionPortalController::class, 'current'])
                ->middleware(['permission:subscriptions.view', 'throttle:60,1']);
            Route::get('/plans', [TenantSubscriptionPortalController::class, 'plans'])
                ->middleware(['permission:subscriptions.view', 'throttle:60,1']);
            Route::get('/invoices', [TenantSubscriptionPortalController::class, 'invoices'])
                ->middleware(['permission:subscriptions.view', 'throttle:60,1']);
            Route::get('/wallet', [TenantSubscriptionPortalController::class, 'wallet'])
                ->middleware(['permission:subscriptions.view', 'throttle:60,1']);
            Route::get('/notifications', [TenantSubscriptionPortalController::class, 'notifications'])
                ->middleware(['permission:subscriptions.view', 'throttle:120,1']);
            Route::post('/upgrade', [TenantSubscriptionPortalController::class, 'upgrade'])
                ->middleware(['permission:subscriptions.manage', 'throttle:30,1']);
            Route::post('/downgrade', [TenantSubscriptionPortalController::class, 'downgrade'])
                ->middleware(['permission:subscriptions.manage', 'throttle:30,1']);
        });
    });

    // ── Bays & Bookings (Phase 6) ────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->group(function () {
        Route::get('/bays', [BayController::class, 'index']);
        Route::post('/bays', [BayController::class, 'store'])
            ->middleware('permission:work_orders.update');
        Route::patch('/bays/{id}/status', [BayController::class, 'updateStatus'])
            ->middleware('permission:work_orders.update');
        Route::get('/bookings', [BayController::class, 'listBookings']);
        Route::post('/bookings', [BayController::class, 'storeBooking'])
            ->middleware('permission:work_orders.update');
        Route::patch('/bookings/{id}', [BayController::class, 'updateBooking'])
            ->middleware('permission:work_orders.update');
        Route::post('/bookings/availability', [BayController::class, 'checkAvailability'])
            ->middleware('permission:work_orders.update');
        Route::get('/bays/heatmap', [BayController::class, 'heatmap']);
    });

    // ── Workshop Operations (Phase 5) ────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('workshop')->group(function () {
        // Employees
        Route::get('/employees/stats', [WorkshopController::class, 'employeeStats']);
        Route::get('/employees', [WorkshopController::class, 'listEmployees']);
        Route::post('/employees', [WorkshopController::class, 'storeEmployee'])
            ->middleware('permission:users.update');
        Route::get('/employees/{id}', [WorkshopController::class, 'showEmployee']);
        Route::put('/employees/{id}', [WorkshopController::class, 'updateEmployee'])
            ->middleware('permission:users.update');
        // Attendance
        Route::post('/attendance/check-in', [WorkshopController::class, 'checkIn'])
            ->middleware('permission:users.update');
        Route::post('/attendance/check-out', [WorkshopController::class, 'checkOut'])
            ->middleware('permission:users.update');
        Route::get('/attendance/today', [WorkshopController::class, 'attendanceTodayAll']);
        Route::get('/attendance/month-all', [WorkshopController::class, 'attendanceMonthAll']);
        Route::get('/attendance/{employeeId}/today', [WorkshopController::class, 'attendanceToday']);
        Route::get('/attendance/{employeeId}/month', [WorkshopController::class, 'attendanceMonth']);
        Route::get('/attendance/{employeeId}/logs', [WorkshopController::class, 'attendanceLogs']);
        // Tasks
        Route::get('/tasks', [WorkshopController::class, 'listTasks']);
        Route::post('/tasks', [WorkshopController::class, 'storeTask'])
            ->middleware('permission:users.update');
        Route::patch('/tasks/{id}/status', [WorkshopController::class, 'updateTaskStatus'])
            ->middleware('permission:users.update');
        Route::get('/tasks/stats', [WorkshopController::class, 'taskStats']);
        Route::get('/tasks/smart-summary', [WorkshopController::class, 'smartTaskSummary']);
        Route::get('/tasks/suggested-assignees', [WorkshopController::class, 'suggestTaskAssignees']);
        // Administrative Communications
        Route::get('/communications', [AdminCommunicationController::class, 'index']);
        Route::post('/communications', [AdminCommunicationController::class, 'store']);
        Route::put('/communications/{id}', [AdminCommunicationController::class, 'update']);
        Route::post('/communications/{id}/submit', [AdminCommunicationController::class, 'submit']);
        Route::post('/communications/{id}/transfer', [AdminCommunicationController::class, 'transfer']);
        Route::post('/communications/{id}/request-signature', [AdminCommunicationController::class, 'requestSignature']);
        Route::post('/communications/{id}/sign', [AdminCommunicationController::class, 'sign']);
        Route::post('/communications/{id}/archive', [AdminCommunicationController::class, 'archive']);
        Route::post('/communications/{id}/restore', [AdminCommunicationController::class, 'restore']);
        // Commissions
        Route::get('/commissions', [WorkshopController::class, 'listCommissions']);
        Route::get('/commission-rules', [WorkshopController::class, 'listCommissionRules']);
        Route::post('/commission-rules', [WorkshopController::class, 'storeCommissionRule'])
            ->middleware('permission:users.update');
        Route::put('/commission-rules/{id}', [WorkshopController::class, 'updateCommissionRule'])
            ->middleware('permission:users.update');
        Route::delete('/commission-rules/{id}', [WorkshopController::class, 'deleteCommissionRule'])
            ->middleware('permission:users.update');
        Route::post('/commissions/{id}/pay', [WorkshopController::class, 'payCommission'])
            ->middleware('permission:users.update');
    });

    // بنود الكتالوج التعاقدي — صلاحيات مفصّلة (لا تتطلب `users.update` كي تبقى قابلة للفصل لاحقًا)
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription'])->prefix('governance')->group(function () {
        Route::middleware('permission:contracts.service_items.view')->group(function () {
            Route::get('/contracts/{contract}/service-items', [ContractServiceItemController::class, 'index']);
            Route::get('/contracts/{contract}/service-items/{itemId}/usage', [ContractServiceItemController::class, 'itemUsage']);
        });
        Route::post('/contracts/{contract}/service-items/match-preview', [ContractServiceItemController::class, 'matchPreview'])
            ->middleware('permission:contracts.service_items.match_preview');
        Route::post('/contracts/{contract}/service-items', [ContractServiceItemController::class, 'store'])
            ->middleware('permission:contracts.service_items.create');
        Route::put('/contracts/{contract}/service-items/{itemId}', [ContractServiceItemController::class, 'update'])
            ->middleware('permission:contracts.service_items.update');
        Route::delete('/contracts/{contract}/service-items/{itemId}', [ContractServiceItemController::class, 'destroy'])
            ->middleware('permission:contracts.service_items.delete');
    });

    // ── Governance (Phase 4) ──────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'subscription', 'permission:users.update'])->prefix('governance')->group(function () {
        // Policy Rules
        Route::get('/policies', [GovernanceController::class, 'listPolicies']);
        Route::post('/policies', [GovernanceController::class, 'storePolicy']);
        Route::delete('/policies/{id}', [GovernanceController::class, 'deletePolicy']);
        Route::post('/policies/evaluate', [GovernanceController::class, 'evaluatePolicy']);

        // Approval Workflows
        Route::get('/workflows', [GovernanceController::class, 'listWorkflows']);
        Route::post('/workflows/{id}/approve', [GovernanceController::class, 'approveWorkflow']);
        Route::post('/workflows/{id}/reject', [GovernanceController::class, 'rejectWorkflow']);

        // Audit Logs
        Route::get('/audit-logs', [GovernanceController::class, 'auditLogs']);

        // Alert Rules & Notifications
        Route::get('/alert-rules', [GovernanceController::class, 'listAlertRules']);
        Route::post('/alert-rules', [GovernanceController::class, 'storeAlertRule']);
        Route::get('/alerts/me', [GovernanceController::class, 'myAlerts']);
        Route::post('/alerts/mark-read', [GovernanceController::class, 'markAlertsRead']);

        // Contracts
        Route::apiResource('contracts', ContractController::class);
        Route::post('/contracts/{contract}/upload-document', [ContractController::class, 'uploadDocument']);
        Route::post('/contracts/{contract}/send-for-signature', [ContractController::class, 'sendForSignature']);
        Route::get('/contracts-expiring', [ContractController::class, 'expiringContracts']);

        // Excel Import
        Route::post('/products/import', [ImportController::class, 'importProducts']);
        Route::post('/services/import', [ImportController::class, 'importServices']);
        Route::post('/vehicles/import', [ImportController::class, 'importVehicles']);
        Route::get('/products/template', [ImportController::class, 'productsTemplate']);
        Route::get('/vehicles/template', [ImportController::class, 'vehiclesTemplate']);

        // Fuel Management
        Route::prefix('fuel')->group(function () {
            Route::get('/', [FuelController::class, 'index']);
            Route::post('/', [FuelController::class, 'store'])->middleware('idempotent');
            Route::delete('/{id}', [FuelController::class, 'destroy']);
            Route::get('/stats', [FuelController::class, 'stats']);
        });

        // Vehicle Settings & Documents
        Route::get('/vehicles/{id}/settings', [FuelController::class, 'getSettings']);
        Route::match(['put', 'patch'], '/vehicles/{id}/settings', [FuelController::class, 'saveSettings']);
        Route::get('/vehicles/{id}/documents', [FuelController::class, 'getDocuments']);
        Route::post('/vehicles/{id}/documents', [FuelController::class, 'uploadDocument']);
        Route::delete('/vehicles/{vehicleId}/documents/{docId}', [FuelController::class, 'deleteDocument']);

        // Bulk Import (Excel / CSV)
        Route::post('/vehicles/import', [ImportController::class, 'importVehicles']);
        Route::post('/employees/import', [ImportController::class, 'importEmployees']);

        // OCR
        Route::post('/ocr/plate', [OcrController::class, 'scanPlate']);
        Route::post('/ocr/invoice', [OcrController::class, 'scanInvoice']);
        Route::post('/ocr/vehicle-document', [OcrController::class, 'scanVehicleDocument'])
            ->middleware('permission:vehicles.update');

        // HR — Leaves
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::post('/leaves/{id}/approve', [LeaveController::class, 'approve']);
        Route::post('/leaves/{id}/reject', [LeaveController::class, 'reject']);
        Route::delete('/leaves/{id}', [LeaveController::class, 'destroy']);

        // HR — Salaries
        Route::get('/salaries', [SalaryController::class, 'index']);
        Route::post('/salaries', [SalaryController::class, 'store']);
        Route::post('/salaries/{id}/approve', [SalaryController::class, 'approve']);
        Route::post('/salaries/{id}/pay', [SalaryController::class, 'pay']);
        Route::get('/salaries/summary', [SalaryController::class, 'summary']);

        // Referral & Loyalty
        Route::get('/referrals', [ReferralController::class, 'index']);
        Route::post('/referrals/generate', [ReferralController::class, 'generate']);
        Route::get('/referrals/policy', [ReferralController::class, 'getPolicy']);
        Route::put('/referrals/policy', [ReferralController::class, 'savePolicy']);
        Route::get('/loyalty/customer/{id}', [ReferralController::class, 'customerPoints']);
        Route::post('/loyalty/redeem', [ReferralController::class, 'redeemPoints']);
        Route::get('/loyalty/leaderboard', [ReferralController::class, 'leaderboard']);
    });

    // ─── SUPPORT SYSTEM ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('support')->group(function () {
        // Tickets
        Route::get('/tickets', [SupportController::class, 'indexTickets']);
        Route::post('/tickets', [SupportController::class, 'storeTicket'])
            ->middleware('permission:users.update');
        Route::get('/tickets/{id}', [SupportController::class, 'showTicket']);
        Route::put('/tickets/{id}', [SupportController::class, 'updateTicket'])
            ->middleware('permission:users.update');
        Route::patch('/tickets/{id}/status', [SupportController::class, 'changeStatus'])
            ->middleware('permission:users.update');
        Route::post('/tickets/{id}/replies', [SupportController::class, 'storeReply'])
            ->middleware('permission:users.update');
        Route::post('/tickets/{id}/rate', [SupportController::class, 'rateSatisfaction'])
            ->middleware('permission:users.update');
        // Stats & SLA
        Route::get('/stats', [SupportController::class, 'stats']);
        Route::get('/sla-policies', [SupportController::class, 'indexSla']);
        Route::post('/sla-policies', [SupportController::class, 'storeSla'])
            ->middleware('permission:users.update');
        Route::put('/sla-policies/{id}', [SupportController::class, 'updateSla'])
            ->middleware('permission:users.update');
        Route::post('/sla/check-breaches', [SupportController::class, 'checkSlaBreaches'])
            ->middleware('permission:users.update');
        // Knowledge Base
        Route::get('/kb', [SupportController::class, 'indexKb']);
        Route::post('/kb', [SupportController::class, 'storeKb'])
            ->middleware('permission:users.update');
        Route::put('/kb/{id}', [SupportController::class, 'updateKb'])
            ->middleware('permission:users.update');
        Route::post('/kb/{id}/vote', [SupportController::class, 'voteKb'])
            ->middleware('permission:users.update');
        Route::get('/kb/search', [SupportController::class, 'searchKb']);
        Route::get('/kb-categories', [SupportController::class, 'indexKbCategories']);
        Route::post('/kb-categories', [SupportController::class, 'storeKbCategory'])
            ->middleware('permission:users.update');
    });

    // ── ZATCA ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('zatca')->group(function () {
        Route::get('/status', [ZatcaController::class, 'status']);
        Route::get('/logs', [ZatcaController::class, 'logs']);
        Route::post('/submit', [ZatcaController::class, 'submit'])
            ->middleware('permission:invoices.update');
    });

    // ── Notifications ──────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{id}/read', [NotificationController::class, 'markRead'])
            ->middleware('permission:users.update');
        Route::put('/read-all', [NotificationController::class, 'markAllRead'])
            ->middleware('permission:users.update');
    });

    // ── Customer Portal ────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('customer-portal')->group(function () {
        Route::get('/dashboard', [CustomerPortalController::class, 'dashboard']);
        Route::get('/pricing', [CustomerPortalController::class, 'pricing']);
        Route::get('/work-orders/{id}/media', [CustomerPortalController::class, 'workOrderMedia']);
        Route::get('/team-users', [CustomerPortalTeamUsersController::class, 'index']);
        Route::post('/team-users', [CustomerPortalTeamUsersController::class, 'store']);
        Route::put('/team-users/{id}', [CustomerPortalTeamUsersController::class, 'update']);
        Route::delete('/team-users/{id}', [CustomerPortalTeamUsersController::class, 'destroy']);
        Route::get('/org-units/tree', [CustomerPortalOrgUnitsController::class, 'tree']);
        Route::post('/org-units', [CustomerPortalOrgUnitsController::class, 'store']);
        Route::put('/org-units/{id}', [CustomerPortalOrgUnitsController::class, 'update']);
        Route::delete('/org-units/{id}', [CustomerPortalOrgUnitsController::class, 'destroy']);
        Route::get('/reports/filter-options', [CustomerPortalReportsController::class, 'filterOptions']);
        Route::get('/reports/summary', [CustomerPortalReportsController::class, 'summary']);
        Route::get('/reports/invoices', [CustomerPortalReportsController::class, 'invoices']);
        Route::get('/reports/org-unit-breakdown', [CustomerPortalReportsController::class, 'orgUnitBreakdown']);
        Route::get('/reports/work-order-items-by-service', [CustomerPortalReportsController::class, 'itemsByService']);
        Route::get('/reports/work-order-items-by-product', [CustomerPortalReportsController::class, 'itemsByProduct']);
        Route::get('/reports/work-orders-completed', [CustomerPortalReportsController::class, 'workOrdersCompleted']);
    });

    Route::middleware(['auth.apikey', 'api.log', 'financial.protection', 'subscription'])->group(function () {
        Route::prefix('external/v1')->group(function () {
            Route::post('/invoices', [ExternalInvoiceController::class, 'store'])->middleware('idempotent');
            Route::get('/invoices/{uuid}', [ExternalInvoiceController::class, 'show']);
        });
    });

    // ── AI Plugins Marketplace ─────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'tenant', 'financial.protection', 'branch.scope', 'subscription'])->prefix('plugins')->group(function () {
        Route::get('/', [PluginController::class, 'index']);
        Route::get('/tenant', [PluginController::class, 'tenantPlugins']);
        Route::get('/{key}', [PluginController::class, 'show']);
        Route::post('/{key}/install', [PluginController::class, 'install'])
            ->middleware('permission:users.update');
        Route::delete('/{key}/uninstall', [PluginController::class, 'uninstall'])
            ->middleware('permission:users.update');
        Route::put('/{key}/configure', [PluginController::class, 'configure'])
            ->middleware('permission:users.update');
        Route::post('/{key}/execute', [PluginController::class, 'execute'])
            ->middleware('permission:users.update');
    });
});
