<?php

declare(strict_types=1);

namespace Tests\Feature\SubscriptionsV2;

use App\Enums\SubscriptionStatus;
use App\Jobs\SubscriptionsV2\PruneSubscriptionRealtimeEventsJob;
use App\Jobs\SubscriptionsV2\RunReconciliationJob;
use App\Jobs\SubscriptionsV2\RunSubscriptionRenewalJob;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\SubscriptionCacheService;
use App\Modules\SubscriptionsV2\Services\SubscriptionWalletService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubscriptionPortalApiTest extends TestCase
{
    public function test_caching_keys_are_written_for_portal_and_admin_overview(): void
    {
        $tenant = $this->createTenant('owner');
        $platform = $this->createStandalonePlatformOperator('cache-admin-'.Str::random(5).'@platform.test');
        $cache = app(SubscriptionCacheService::class);

        $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/current')->assertOk();
        $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/plans')->assertOk();
        $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/wallet')->assertOk();
        $this->actingAs($platform, 'sanctum')->getJson('/api/v1/admin/subscriptions/overview')->assertOk();

        $this->assertTrue(Cache::has($cache->currentKey((int) $tenant['company']->id)));
        $this->assertTrue(Cache::has($cache->plansKey()));
        $this->assertTrue(Cache::has($cache->walletKey((int) $tenant['company']->id)));
        $this->assertTrue(Cache::has($cache->adminOverviewKey()));
    }

    public function test_wallet_update_invalidates_current_and_wallet_cache(): void
    {
        $tenant = $this->createTenant('owner');
        Wallet::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'customer_id' => null,
            'balance' => 1000,
            'currency' => 'SAR',
            'status' => 'active',
        ]);
        $cache = app(SubscriptionCacheService::class);
        Cache::put($cache->currentKey((int) $tenant['company']->id), ['ok' => true], 60);
        Cache::put($cache->walletKey((int) $tenant['company']->id), ['ok' => true], 60);

        app(SubscriptionWalletService::class)->debit((int) $tenant['company']->id, 10, 'cache-invalidation-test', (int) $tenant['user']->id);

        $this->assertFalse(Cache::has($cache->currentKey((int) $tenant['company']->id)));
        $this->assertFalse(Cache::has($cache->walletKey((int) $tenant['company']->id)));
    }

    public function test_admin_insights_endpoint_returns_expected_shape(): void
    {
        $platform = $this->createStandalonePlatformOperator('insights-admin-'.Str::random(5).'@platform.test');
        $res = $this->actingAs($platform, 'sanctum')->getJson('/api/v1/admin/subscriptions/insights');
        $res->assertOk()->assertJsonStructure([
            'data' => [
                'revenue',
                'churn_signals',
                'risks',
                'wallet_insights',
            ],
        ]);
    }

    public function test_jobs_are_routed_to_expected_queues(): void
    {
        $this->assertSame('high', (new RunReconciliationJob(1))->queue);
        $this->assertSame('high', (new RunSubscriptionRenewalJob())->queue);
        $this->assertSame('low', (new PruneSubscriptionRealtimeEventsJob())->queue);
    }

    public function test_client_subscription_endpoints_return_data(): void
    {
        $tenant = $this->createTenant('owner');
        Plan::query()->create([
            'slug' => 'api-'.Str::lower(Str::random(6)),
            'name' => 'API Plan',
            'name_ar' => 'خطة API',
            'price_monthly' => 1000,
            'price_yearly' => 10000,
            'currency' => 'SAR',
            'max_branches' => 3,
            'max_users' => 10,
            'max_products' => 100,
            'grace_period_days' => 3,
            'features' => ['pos' => true],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        Wallet::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'customer_id' => null,
            'balance' => 500,
            'currency' => 'SAR',
            'status' => 'active',
        ]);
        Invoice::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number' => 'SUB-INV-1',
            'type' => 'subscription',
            'status' => 'paid',
            'customer_type' => 'b2b',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 115,
            'due_amount' => 0,
            'currency' => 'SAR',
            'issued_at' => now(),
            'due_at' => now(),
        ]);

        $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/current')->assertOk();
        $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/plans')->assertOk();
        $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/invoices')->assertOk();
        $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/wallet')->assertOk();
    }

    public function test_tenant_can_list_own_payment_orders(): void
    {
        $tenant = $this->createTenant('owner');
        $plan = Plan::query()->create([
            'slug' => 'po-list-'.Str::lower(Str::random(6)),
            'name' => 'PO List Plan',
            'name_ar' => 'خطة قائمة الطلبات',
            'price_monthly' => 200,
            'price_yearly' => 2000,
            'currency' => 'SAR',
            'max_branches' => 2,
            'max_users' => 5,
            'max_products' => 50,
            'grace_period_days' => 2,
            'features' => ['invoices' => true],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $order = PaymentOrder::query()->create([
            'company_id' => $tenant['company']->id,
            'plan_id' => $plan->id,
            'amount' => 200,
            'vat' => 30,
            'total' => 230,
            'currency' => 'SAR',
            'reference_code' => 'SUB-ORD-'.Str::upper(Str::random(8)),
            'status' => PaymentOrderStatus::PendingTransfer,
            'expires_at' => now()->addDays(3),
            'created_by' => $tenant['user']->id,
        ]);

        $res = $this->actingAs($tenant['user'], 'sanctum')->getJson('/api/v1/subscriptions/payment-orders');
        $res->assertOk();
        $res->assertJsonPath('data.0.id', $order->id);
        $res->assertJsonPath('data.0.reference_code', $order->reference_code);
        $res->assertJsonPath('data.0.plan.slug', $plan->slug);
    }

    public function test_upgrade_and_downgrade_endpoints_work(): void
    {
        $tenant = $this->createTenant('owner');
        $current = Plan::query()->create([
            'slug' => 'base-'.Str::lower(Str::random(6)),
            'name' => 'Base',
            'name_ar' => 'أساسي',
            'price_monthly' => 500,
            'price_yearly' => 5000,
            'currency' => 'SAR',
            'max_branches' => 3,
            'max_users' => 10,
            'max_products' => 100,
            'grace_period_days' => 3,
            'features' => ['pos' => true],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $higher = Plan::query()->create([
            'slug' => 'higher-'.Str::lower(Str::random(6)),
            'name' => 'Higher',
            'name_ar' => 'أعلى',
            'price_monthly' => 1500,
            'price_yearly' => 15000,
            'currency' => 'SAR',
            'max_branches' => 8,
            'max_users' => 30,
            'max_products' => 1000,
            'grace_period_days' => 3,
            'features' => ['pos' => true],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $lower = Plan::query()->create([
            'slug' => 'lower-'.Str::lower(Str::random(6)),
            'name' => 'Lower',
            'name_ar' => 'أقل',
            'price_monthly' => 300,
            'price_yearly' => 3000,
            'currency' => 'SAR',
            'max_branches' => 2,
            'max_users' => 5,
            'max_products' => 50,
            'grace_period_days' => 3,
            'features' => ['pos' => true],
            'is_active' => true,
            'sort_order' => 0,
        ]);
        Subscription::withoutGlobalScopes()->whereKey($tenant['subscription']->id)->update([
            'plan' => $current->slug,
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->addDays(10),
        ]);
        Wallet::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'customer_id' => null,
            'balance' => 3000,
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/upgrade', ['plan_slug' => $higher->slug])
            ->assertCreated();
        $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/downgrade', ['plan_slug' => $lower->slug])
            ->assertCreated();
    }

    public function test_admin_overview_transactions_wallets_endpoints_work(): void
    {
        $platform = $this->createStandalonePlatformOperator('phase5-admin-'.Str::random(5).'@platform.test');
        BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date' => now()->toDateString(),
            'amount' => 10,
            'currency' => 'SAR',
            'is_matched' => false,
        ]);
        PaymentOrder::query()->create([
            'company_id' => $this->createCompany()->id,
            'plan_id' => Plan::query()->create([
                'slug' => 'p-'.Str::lower(Str::random(4)),
                'name' => 'P',
                'name_ar' => 'ب',
                'price_monthly' => 100,
                'price_yearly' => 1000,
                'currency' => 'SAR',
                'max_branches' => 1,
                'max_users' => 1,
                'max_products' => 1,
                'grace_period_days' => 3,
                'features' => ['pos' => true],
                'is_active' => true,
                'sort_order' => 0,
            ])->id,
            'amount' => 100,
            'vat' => 15,
            'total' => 115,
            'currency' => 'SAR',
            'reference_code' => 'SUB-ORD-'.Str::upper(Str::random(10)),
            'status' => 'awaiting_review',
            'expires_at' => now()->addDay(),
            'created_by' => $platform->id,
        ]);

        $this->actingAs($platform, 'sanctum')->getJson('/api/v1/admin/subscriptions/overview')->assertOk();
        $this->actingAs($platform, 'sanctum')->getJson('/api/v1/admin/subscriptions/transactions')->assertOk();
        $this->actingAs($platform, 'sanctum')->getJson('/api/v1/admin/subscriptions/wallets')->assertOk();
    }
}

