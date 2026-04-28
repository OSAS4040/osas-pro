<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\Plan;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformSubscriptionsRoutesTest extends TestCase
{
    public function test_platform_subscriptions_read_routes_return_ok_for_platform_operator(): void
    {
        $platform = $this->createStandalonePlatformOperator('sub-routes-'.Str::lower(Str::random(6)).'@platform.test');
        $tenant = $this->createTenant('owner');
        $subId = (int) $tenant['subscription']->id;

        $plan = Plan::query()->create([
            'slug' => 'po-'.Str::lower(Str::random(6)),
            'name' => 'PO Plan',
            'name_ar' => 'خطة',
            'price_monthly' => 100,
            'price_yearly' => 1000,
            'currency' => 'SAR',
            'max_branches' => 3,
            'max_users' => 10,
            'max_products' => 100,
            'grace_period_days' => 3,
            'features' => ['pos' => true],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $paymentOrder = PaymentOrder::query()->create([
            'company_id' => $tenant['company']->id,
            'plan_id' => $plan->id,
            'amount' => 100,
            'vat' => 15,
            'total' => 115,
            'currency' => 'SAR',
            'reference_code' => 'SUB-RT-'.Str::upper(Str::random(10)),
            'status' => 'awaiting_review',
            'expires_at' => now()->addDay(),
            'created_by' => $platform->id,
        ]);

        $bankTx = BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date' => now()->toDateString(),
            'amount' => 115,
            'currency' => 'SAR',
            'is_matched' => false,
        ]);

        $invoice = Invoice::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number' => 'SUB-RT-INV-'.Str::upper(Str::random(6)),
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

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/list')
            ->assertOk();

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/invoices')
            ->assertOk();

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/subscription/'.$subId)
            ->assertOk();

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/payment-orders/'.$paymentOrder->id)
            ->assertOk();

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/invoices/'.$invoice->id)
            ->assertOk();

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/bank-transactions/'.$bankTx->id)
            ->assertOk();
    }

    public function test_tenant_user_cannot_access_platform_subscriptions_list(): void
    {
        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/admin/subscriptions/list')
            ->assertForbidden();
    }

    public function test_debug_health_is_disabled_by_default(): void
    {
        $platform = $this->createStandalonePlatformOperator('sub-dbg-off-'.Str::lower(Str::random(5)).'@platform.test');
        Config::set('platform_subscriptions.debug_health_enabled', false);

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/debug/health')
            ->assertNotFound();
    }

    public function test_debug_health_returns_payload_when_enabled(): void
    {
        $platform = $this->createStandalonePlatformOperator('sub-dbg-on-'.Str::lower(Str::random(5)).'@platform.test');
        Config::set('platform_subscriptions.debug_health_enabled', true);

        $this->actingAsUser($platform)
            ->getJson('/api/v1/admin/subscriptions/debug/health')
            ->assertOk()
            ->assertJsonStructure([
                'routes',
                'auth',
                'disk_public',
                'sample_data' => [
                    'subscriptions',
                    'invoices',
                    'payment_orders',
                    'bank_transactions',
                ],
                'trace_id',
            ]);
    }
}
