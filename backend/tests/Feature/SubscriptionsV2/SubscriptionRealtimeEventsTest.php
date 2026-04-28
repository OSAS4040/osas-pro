<?php

declare(strict_types=1);

namespace Tests\Feature\SubscriptionsV2;

use App\Models\Plan;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Models\RealtimeEvent;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SubscriptionRealtimeEventsTest extends TestCase
{
    public function test_first_three_realtime_events_are_published(): void
    {
        $tenant = $this->createTenant('owner');
        $plan = Plan::query()->create([
            'slug' => 'rt-'.Str::lower(Str::random(6)),
            'name' => 'Realtime',
            'name_ar' => 'فوري',
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

        $createRes = $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders', ['plan_id' => $plan->id]);
        $createRes->assertCreated();
        $orderId = (int) $createRes->json('data.id');
        $order = PaymentOrder::query()->findOrFail($orderId);

        $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders/'.$order->id.'/submit-transfer', [
                'amount'        => (float) $order->total,
                'transfer_date' => now()->toDateString(),
                'bank_name'     => 'Test Bank',
            ])
            ->assertOk();

        $tx = BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date'  => now()->toDateString(),
            'amount'            => $order->total,
            'currency'          => 'SAR',
            'description'       => (string) $order->reference_code,
            'is_matched'        => false,
        ]);
        $platform = $this->createStandalonePlatformOperator('rt-admin-'.Str::random(5).'@platform.test');
        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/review-queue/'.$order->id.'/match', [
                'bank_transaction_id' => $tx->id,
            ])
            ->assertOk();
        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/approve')
            ->assertOk();

        $companyEvents = RealtimeEvent::query()
            ->where('company_id', $tenant['company']->id)
            ->pluck('event_type')
            ->all();

        $this->assertContains('payment_order_created', $companyEvents);
        $this->assertContains('transfer_submitted', $companyEvents);
        $this->assertContains('transfer_approved', $companyEvents);
    }
}

