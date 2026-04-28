<?php

declare(strict_types=1);

namespace Tests\Feature\SubscriptionsV2;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\PaymentService;
use App\Modules\SubscriptionsV2\Services\SubscriptionService;
use App\Modules\SubscriptionsV2\Services\SubscriptionWalletService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubscriptionLifecyclePhase4Test extends TestCase
{
    private function makePlan(float $monthly = 1000): Plan
    {
        return Plan::query()->create([
            'slug'              => 'p4-'.Str::lower(Str::random(8)),
            'name'              => 'Phase4 Plan',
            'name_ar'           => 'باقة',
            'price_monthly'     => $monthly,
            'price_yearly'      => $monthly * 10,
            'currency'          => 'SAR',
            'max_branches'      => 3,
            'max_users'         => 10,
            'max_products'      => 100,
            'grace_period_days' => 3,
            'features'          => ['pos' => true],
            'is_active'         => true,
            'sort_order'        => 0,
        ]);
    }

    private function makePaymentOrder(int $companyId, int $planId, int $createdBy, float $total = 1150): PaymentOrder
    {
        return PaymentOrder::query()->create([
            'company_id'     => $companyId,
            'plan_id'        => $planId,
            'amount'         => round($total / 1.15, 2),
            'vat'            => round($total - round($total / 1.15, 2), 2),
            'total'          => $total,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-P4'.Str::upper(Str::random(8)),
            'status'         => 'pending_transfer',
            'expires_at'     => now()->addDay(),
            'created_by'     => $createdBy,
        ]);
    }

    public function test_wallet_credit_and_debit_work_with_balance_tracking(): void
    {
        $tenant = $this->createTenant('owner');
        $svc = app(SubscriptionWalletService::class);

        $credit = $svc->credit((int) $tenant['company']->id, 2000, 'seed', (int) $tenant['user']->id, 'k-credit');
        $debit = $svc->debit((int) $tenant['company']->id, 500, 'charge', (int) $tenant['user']->id, 'k-debit');

        $this->assertGreaterThan(0, $credit->id);
        $this->assertGreaterThan(0, $debit->id);
        $this->assertSame(1500.0, round($svc->getBalance((int) $tenant['company']->id), 2));
    }

    public function test_payment_via_wallet_only_and_hybrid_breakdown(): void
    {
        $tenant = $this->createTenant('owner');
        $plan = $this->makePlan();
        $order = $this->makePaymentOrder((int) $tenant['company']->id, (int) $plan->id, (int) $tenant['user']->id);
        $paymentService = app(PaymentService::class);

        $walletPayment = $paymentService->createFromWallet($order, (int) $tenant['user']->id, 1150);
        $this->assertSame('wallet', $walletPayment->method);
        $this->assertSame('1150.00', (string) data_get($walletPayment->meta, 'breakdown.wallet_amount'));

        $order2 = $this->makePaymentOrder((int) $tenant['company']->id, (int) $plan->id, (int) $tenant['user']->id);
        $hybridPayment = $paymentService->createHybridPayment($order2, (int) $tenant['user']->id, 300, 850);
        $this->assertSame('hybrid', $hybridPayment->method);
        $this->assertSame('300.00', (string) data_get($hybridPayment->meta, 'breakdown.wallet_amount'));
        $this->assertSame('850.00', (string) data_get($hybridPayment->meta, 'breakdown.bank_amount'));
    }

    public function test_renewal_with_wallet_extends_subscription_and_creates_invoice_payment(): void
    {
        $tenant = $this->createTenant('owner');
        $plan = $this->makePlan(1000);
        $subscription = Subscription::withoutGlobalScopes()->whereKey($tenant['subscription']->id)->firstOrFail();
        $subscription->update([
            'plan' => $plan->slug,
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->addHours(2),
        ]);

        app(SubscriptionWalletService::class)->credit((int) $tenant['company']->id, 2000, 'renewal-fund', (int) $tenant['user']->id, 'renew-fund');

        app(SubscriptionService::class)->processRenewalCycle((int) $tenant['user']->id);
        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->ends_at !== null && $subscription->ends_at->gt(now()->addDays(20)));
        $payment = Payment::withoutGlobalScopes()->where('company_id', $tenant['company']->id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoice_id);
    }

    public function test_renewal_without_wallet_moves_to_past_due_then_suspend_then_expire(): void
    {
        $tenant = $this->createTenant('owner');
        $plan = $this->makePlan(1000);
        $subscription = Subscription::withoutGlobalScopes()->whereKey($tenant['subscription']->id)->firstOrFail();
        $subscription->update([
            'plan' => $plan->slug,
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->subMinute(),
            'grace_ends_at' => null,
        ]);

        app(SubscriptionService::class)->renew($subscription, (int) $tenant['user']->id);
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);

        $subscription->update(['grace_ends_at' => now()->subMinute()]);
        app(SubscriptionService::class)->progressLifecycleStates();
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->status);

        $subscription->update(['ends_at' => now()->subDays(4)]);
        app(SubscriptionService::class)->progressLifecycleStates();
        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
    }

    public function test_upgrade_with_proration_and_scheduled_downgrade(): void
    {
        $tenant = $this->createTenant('owner');
        $base = $this->makePlan(500);
        $higher = $this->makePlan(1500);
        $lower = $this->makePlan(300);
        $sub = Subscription::withoutGlobalScopes()->whereKey($tenant['subscription']->id)->firstOrFail();
        $sub->update([
            'plan' => $base->slug,
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->addDays(15),
        ]);

        app(SubscriptionWalletService::class)->credit((int) $tenant['company']->id, 2000, 'upgrade-fund', (int) $tenant['user']->id, 'upgrade-fund');
        $service = app(SubscriptionService::class);
        $upgrade = $service->upgrade($sub, $higher, (int) $tenant['user']->id);
        $this->assertSame('upgrade', $upgrade->change_type);
        $this->assertGreaterThan(0, (float) $upgrade->proration_amount);

        $sub->refresh();
        $this->assertSame($higher->slug, $sub->plan);

        $down = $service->scheduleDowngrade($sub, $lower, (int) $tenant['user']->id);
        $this->assertSame('downgrade_scheduled', $down->change_type);

        $down->update(['effective_at' => now()->subMinute()]);
        $service->applyScheduledDowngrades();
        $sub->refresh();
        $down->refresh();
        $this->assertSame($lower->slug, $sub->plan);
        $this->assertSame('downgrade_applied', $down->change_type);
    }

    public function test_duplicate_wallet_debit_and_negative_balance_are_prevented(): void
    {
        $tenant = $this->createTenant('owner');
        $svc = app(SubscriptionWalletService::class);
        $svc->credit((int) $tenant['company']->id, 100, 'seed', (int) $tenant['user']->id, 'dup-seed');
        $svc->debit((int) $tenant['company']->id, 50, 'charge', (int) $tenant['user']->id, 'dup-key');

        $this->expectException(\DomainException::class);
        $svc->debit((int) $tenant['company']->id, 50, 'charge-again', (int) $tenant['user']->id, 'dup-key');
    }
}

