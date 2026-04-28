<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Models\SubscriptionChange;
use App\Modules\SubscriptionsV2\Support\ResolveCompanyBillingBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تفعيل/تحديث اشتراك المستأجر بعد دفع مُعتمد — لا يُستدعى إلا بعد Payment.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SubscriptionWalletService $walletService,
        private readonly InvoiceService $invoiceService,
        private readonly ResolveCompanyBillingBranch $resolveBranch,
        private readonly SubscriptionCacheService $subscriptionCacheService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function activateFromPayment(PaymentOrder $order, Payment $payment): Subscription
    {
        if ($payment->invoice_id === null) {
            throw new \DomainException('Subscription activation requires payment linked to an invoice.');
        }
        if ($payment->status !== 'completed') {
            throw new \DomainException('Subscription activation requires a completed payment.');
        }

        $plan = $order->plan()->firstOrFail();

        $subscription = Subscription::withoutGlobalScopes()
            ->where('company_id', $order->company_id)
            ->first();

        $baseDate = $subscription?->ends_at !== null && $subscription->ends_at->isFuture()
            ? $subscription->ends_at->copy()
            : now();
        $payload = [
            'plan'           => $plan->slug,
            'amount'         => $order->total,
            'currency'       => $order->currency,
            'features'       => $plan->features,
            'max_branches'   => $plan->max_branches,
            'max_users'      => $plan->max_users,
            'status'         => SubscriptionStatus::Active,
            'starts_at'      => $subscription?->starts_at ?? now(),
            'ends_at'        => $baseDate->copy()->addMonth(),
            'grace_ends_at'  => null,
        ];

        if ($subscription === null) {
            $subscription = Subscription::withoutGlobalScopes()->create(array_merge([
                'uuid'       => (string) Str::uuid(),
                'company_id' => $order->company_id,
            ], $payload));
        } else {
            $before = $subscription->only(['plan', 'status', 'amount', 'ends_at']);
            $subscription->update($payload);
            $this->auditLogService->log(
                null,
                'activate_subscription',
                'Subscription',
                $subscription->id,
                $before,
                $subscription->fresh()?->only(['plan', 'status', 'amount', 'ends_at']),
                ['payment_order_id' => $order->id, 'payment_id' => $payment->id],
            );

            return $subscription->fresh() ?? $subscription;
        }

        $this->auditLogService->log(
            null,
            'activate_subscription',
            'Subscription',
            $subscription->id,
            null,
            $subscription->only(['plan', 'status', 'company_id']),
            ['payment_order_id' => $order->id, 'payment_id' => $payment->id],
        );

        return $subscription;
    }

    public function processRenewalCycle(?int $actorId = null): void
    {
        Subscription::withoutGlobalScopes()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->where('ends_at', '<=', now()->copy()->addDay())
            ->orderBy('id')
            ->each(function (Subscription $subscription) use ($actorId): void {
                $this->renew($subscription, $actorId);
            });
    }

    public function renew(Subscription $subscription, ?int $actorId = null): void
    {
        DB::transaction(function () use ($subscription, $actorId): void {
            $locked = Subscription::withoutGlobalScopes()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $plan = Plan::query()->where('slug', $locked->plan)->first();
            if ($plan === null || ! $plan->is_active) {
                $this->markPastDue($locked);

                return;
            }

            $amount = round((float) $plan->price_monthly * 1.15, 2);
            $idempotencyKey = 'sub-renew-'.$locked->id.'-'.$locked->ends_at?->format('Ymd');
            $walletBalance = $this->walletService->getBalance((int) $locked->company_id);
            if ($walletBalance < $amount) {
                $this->markPastDue($locked, $plan);

                return;
            }

            $txn = $this->walletService->debit((int) $locked->company_id, $amount, 'subscription_renewal', (int) ($actorId ?? 1), $idempotencyKey);
            $payment = $this->createSubscriptionPayment($locked, $amount, 'wallet', (int) ($actorId ?? 1), [
                'wallet_transaction_id' => $txn->id,
                'renewal_idempotency_key' => $idempotencyKey,
            ]);
            $invoice = $this->invoiceService->createForSubscriptionCycle($payment, $locked, $plan, (int) ($actorId ?? 1), 'renewal');
            if ($invoice->id <= 0) {
                throw new \DomainException('Renewal requires invoice.');
            }

            $locked->status = SubscriptionStatus::Active;
            $locked->amount = $amount;
            $locked->currency = (string) $plan->currency;
            $locked->features = $plan->features;
            $locked->max_branches = $plan->max_branches;
            $locked->max_users = $plan->max_users;
            $locked->ends_at = ($locked->ends_at !== null && $locked->ends_at->isFuture() ? $locked->ends_at : now())->addMonth();
            $locked->grace_ends_at = null;
            $locked->save();

            $this->auditLogService->log(
                $actorId,
                'subscription_renewed',
                'Subscription',
                $locked->id,
                null,
                ['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'next_end' => $locked->ends_at?->toDateString()],
                ['wallet_transaction_id' => $txn->id],
            );
            $companyId = (int) $locked->company_id;
            $subscriptionId = (int) $locked->id;
            DB::afterCommit(function () use ($companyId, $subscriptionId): void {
                $this->subscriptionCacheService->invalidateCompany($companyId);
                $this->subscriptionCacheService->invalidateGlobal();
                $this->realtimeNotificationService->publish(
                    'subscription_renewed',
                    $companyId,
                    'company',
                    [
                        'type' => 'subscription_renewed',
                        'company_id' => $companyId,
                        'subscription_id' => $subscriptionId,
                        'message' => 'تم تجديد الاشتراك تلقائيًا.',
                    ],
                );
            });
        });
    }

    public function markPastDue(Subscription $subscription, ?Plan $plan = null): void
    {
        $graceDays = (int) ($plan?->grace_period_days ?? 3);
        $subscription->status = SubscriptionStatus::PastDue;
        $subscription->grace_ends_at = now()->addDays($graceDays);
        $subscription->save();
        $companyId = (int) $subscription->company_id;
        $subscriptionId = (int) $subscription->id;
        DB::afterCommit(function () use ($companyId, $subscriptionId): void {
            $this->subscriptionCacheService->invalidateCompany($companyId);
            $this->subscriptionCacheService->invalidateGlobal();
            $this->realtimeNotificationService->publish(
                'subscription_past_due',
                $companyId,
                'company',
                [
                    'type' => 'subscription_past_due',
                    'company_id' => $companyId,
                    'subscription_id' => $subscriptionId,
                    'message' => 'الاشتراك في حالة متأخر سداد.',
                ],
            );
        });
    }

    public function suspend(Subscription $subscription): void
    {
        $subscription->status = SubscriptionStatus::Suspended;
        $subscription->save();
    }

    public function expire(Subscription $subscription): void
    {
        $subscription->status = SubscriptionStatus::Expired;
        $subscription->save();
    }

    public function progressLifecycleStates(): void
    {
        Subscription::withoutGlobalScopes()
            ->where('status', SubscriptionStatus::PastDue)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', now())
            ->each(fn (Subscription $sub) => $this->suspend($sub));

        Subscription::withoutGlobalScopes()
            ->where('status', SubscriptionStatus::Suspended)
            ->where('ends_at', '<', now()->subDays(3))
            ->each(fn (Subscription $sub) => $this->expire($sub));
    }

    public function upgrade(Subscription $subscription, Plan $newPlan, int $actorId): SubscriptionChange
    {
        return DB::transaction(function () use ($subscription, $newPlan, $actorId): SubscriptionChange {
            $locked = Subscription::withoutGlobalScopes()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $currentPlan = Plan::query()->where('slug', $locked->plan)->firstOrFail();
            $proration = $this->calculateProration($locked, $currentPlan, $newPlan);
            $paymentMeta = ['change_type' => 'upgrade', 'proration_amount' => (string) $proration];

            if ($proration > 0) {
                $wallet = $this->walletService->getBalance((int) $locked->company_id);
                if ($wallet >= $proration) {
                    $tx = $this->walletService->debit((int) $locked->company_id, $proration, 'subscription_upgrade_proration', $actorId, 'sub-upg-'.$locked->id.'-'.$newPlan->id);
                    $paymentMeta['wallet_transaction_id'] = $tx->id;
                    $method = 'wallet';
                } else {
                    $walletPortion = max(0.0, $wallet);
                    $bankPortion = round($proration - $walletPortion, 2);
                    if ($walletPortion > 0) {
                        $tx = $this->walletService->debit((int) $locked->company_id, $walletPortion, 'subscription_upgrade_proration_partial', $actorId, 'sub-upg-'.$locked->id.'-'.$newPlan->id.'-wallet');
                        $paymentMeta['wallet_transaction_id'] = $tx->id;
                    }
                    $method = $walletPortion > 0 ? 'hybrid' : 'bank_transfer';
                    $paymentMeta['bank_amount'] = (string) $bankPortion;
                    $paymentMeta['wallet_amount'] = (string) $walletPortion;
                }
                $payment = $this->createSubscriptionPayment($locked, $proration, $method, $actorId, $paymentMeta);
                $invoice = $this->invoiceService->createForSubscriptionCycle($payment, $locked, $newPlan, $actorId, 'upgrade_proration');
                $paymentMeta['invoice_id'] = $invoice->id;
            }

            $change = SubscriptionChange::query()->create([
                'subscription_id' => $locked->id,
                'from_plan_id'    => $currentPlan->id,
                'to_plan_id'      => $newPlan->id,
                'change_type'     => 'upgrade',
                'proration_amount'=> $proration,
                'effective_at'    => now(),
                'created_by'      => $actorId,
            ]);

            $locked->plan = $newPlan->slug;
            $locked->features = $newPlan->features;
            $locked->max_branches = $newPlan->max_branches;
            $locked->max_users = $newPlan->max_users;
            $locked->save();
            DB::afterCommit(function () use ($locked): void {
                $this->subscriptionCacheService->invalidateCompany((int) $locked->company_id);
                $this->subscriptionCacheService->invalidateGlobal();
            });

            return $change;
        });
    }

    public function scheduleDowngrade(Subscription $subscription, Plan $newPlan, int $actorId): SubscriptionChange
    {
        $currentPlan = Plan::query()->where('slug', $subscription->plan)->firstOrFail();

        $change = SubscriptionChange::query()->create([
            'subscription_id' => $subscription->id,
            'from_plan_id'    => $currentPlan->id,
            'to_plan_id'      => $newPlan->id,
            'change_type'     => 'downgrade_scheduled',
            'proration_amount'=> 0,
            'effective_at'    => $subscription->ends_at ?? now()->addMonth(),
            'created_by'      => $actorId,
        ]);

        DB::afterCommit(function () use ($subscription): void {
            $this->subscriptionCacheService->invalidateCompany((int) $subscription->company_id);
            $this->subscriptionCacheService->invalidateGlobal();
        });

        return $change;
    }

    public function applyScheduledDowngrades(): void
    {
        SubscriptionChange::query()
            ->where('change_type', 'downgrade_scheduled')
            ->where('effective_at', '<=', now())
            ->orderBy('id')
            ->each(function (SubscriptionChange $change): void {
                DB::transaction(function () use ($change): void {
                    $subscription = Subscription::withoutGlobalScopes()->whereKey($change->subscription_id)->lockForUpdate()->first();
                    if ($subscription === null) {
                        return;
                    }
                    $plan = Plan::query()->whereKey($change->to_plan_id)->first();
                    if ($plan === null) {
                        return;
                    }
                    $subscription->plan = $plan->slug;
                    $subscription->features = $plan->features;
                    $subscription->max_branches = $plan->max_branches;
                    $subscription->max_users = $plan->max_users;
                    $subscription->save();

                    $change->change_type = 'downgrade_applied';
                    $change->save();
                });
            });
    }

    private function calculateProration(Subscription $subscription, Plan $currentPlan, Plan $targetPlan): float
    {
        $daysLeft = max(0, now()->diffInDays($subscription->ends_at ?? now(), false));
        $periodDays = 30;
        $delta = max(0.0, (float) $targetPlan->price_monthly - (float) $currentPlan->price_monthly);

        return round(($delta * ($daysLeft / $periodDays)) * 1.15, 2);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function createSubscriptionPayment(
        Subscription $subscription,
        float $amount,
        string $method,
        int $actorId,
        array $meta,
    ): Payment {
        $company = Company::query()->findOrFail((int) $subscription->company_id);
        $branch  = ($this->resolveBranch)($company);

        return Payment::query()->create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => (int) $subscription->company_id,
            'branch_id'          => $branch->id,
            'invoice_id'         => null,
            'payment_order_id'   => null,
            'created_by_user_id' => $actorId,
            'method'             => $method,
            'payment_method'     => $method,
            'amount'             => $amount,
            'currency'           => (string) $subscription->currency,
            'reference'          => 'SUB-CYCLE-'.$subscription->id.'-'.now()->format('YmdHi'),
            'status'             => 'completed',
            'meta'               => array_merge($meta, ['subscriptions_v2_cycle' => true, 'subscription_id' => $subscription->id]),
            'created_at'         => now(),
        ]);
    }
}
