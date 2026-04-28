<?php

declare(strict_types=1);

namespace Tests\Feature\SubscriptionsV2;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchType;
use App\Modules\SubscriptionsV2\Models\AuditLog;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\InvoiceService;
use App\Modules\SubscriptionsV2\Services\ReconciliationService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PaymentOrderFlowTest extends TestCase
{
    private function seedConfirmedReconciliation(PaymentOrder $order, User $actor): void
    {
        $tx = BankTransaction::query()->create([
            'import_batch_uuid'   => (string) Str::uuid(),
            'transaction_date'    => now()->toDateString(),
            'amount'              => $order->total,
            'currency'            => $order->currency,
            'description'         => 'Wire '.$order->reference_code,
            'reference_extracted' => null,
            'is_matched'          => false,
        ]);

        app(ReconciliationService::class)->confirmMatch(
            $order->fresh(),
            $tx,
            ReconciliationMatchType::Manual,
            (int) $actor->id,
            99.0,
            'test_seed',
        );
    }

    private function makePlan(): Plan
    {
        return Plan::query()->create([
            'slug'           => 'v2t-'.Str::lower(Str::random(8)),
            'name'           => 'V2 Test Plan',
            'name_ar'        => 'باقة اختبار',
            'price_monthly'  => 1000,
            'price_yearly'   => 10000,
            'currency'       => 'SAR',
            'max_branches'   => 3,
            'max_users'      => 10,
            'max_products'   => 100,
            'grace_period_days' => 15,
            'features'       => ['pos' => true],
            'is_active'      => true,
            'sort_order'     => 0,
        ]);
    }

    public function test_create_payment_order_via_api(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();

        $res = $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders', ['plan_id' => $plan->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.company_id', $tenant['company']->id);
        $res->assertJsonPath('data.status', PaymentOrderStatus::PendingTransfer->value);
        $this->assertDatabaseHas('payment_orders', [
            'company_id' => $tenant['company']->id,
            'plan_id'    => $plan->id,
        ]);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'create_order')->count());
    }

    public function test_submit_transfer_moves_to_awaiting_review(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-TEST'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::PendingTransfer,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $res = $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders/'.$order->id.'/submit-transfer', [
                'amount'        => 1150,
                'transfer_date' => now()->toDateString(),
                'bank_name'     => 'Test Bank',
            ]);

        $res->assertOk();
        $order->refresh();
        $this->assertSame(PaymentOrderStatus::AwaitingReview, $order->status);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'submit_transfer')->count());
    }

    public function test_submit_transfer_rejected_when_order_expired(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-EXP'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::PendingTransfer,
            'expires_at'     => now()->subMinute(),
            'created_by'     => $tenant['user']->id,
        ]);

        $res = $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders/'.$order->id.'/submit-transfer', [
                'amount'        => 1150,
                'transfer_date' => now()->toDateString(),
                'bank_name'     => 'Test Bank',
            ]);

        $res->assertStatus(422);
        $order->refresh();
        $this->assertSame(PaymentOrderStatus::PendingTransfer, $order->status);
    }

    public function test_approve_creates_payment_invoice_and_updates_subscription(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-APR'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $platform = $this->createStandalonePlatformOperator('plat-v2-'.Str::random(6).'@platform.test');
        $this->seedConfirmedReconciliation($order, $platform);

        $res = $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/approve');

        $res->assertOk();
        $res->assertJsonStructure(['data' => ['order', 'payment_id', 'invoice_id']]);

        $order->refresh();
        $this->assertSame(PaymentOrderStatus::Approved, $order->status);
        $this->assertNotNull($order->approved_at);

        $paymentId = (int) $res->json('data.payment_id');
        $this->assertGreaterThan(0, $paymentId);
        $payment = Payment::withoutGlobalScopes()->findOrFail($paymentId);
        $this->assertNotNull($payment->invoice_id);
        $this->assertSame((int) $order->company_id, (int) $payment->company_id);

        $tenant['subscription']->refresh();
        $this->assertSame($plan->slug, $tenant['subscription']->plan);

        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'create_payment')->count());
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'create_invoice')->count());
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'activate_subscription')->count());
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'approve_transfer')->count());
    }

    public function test_double_approve_returns_422(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-DUP'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $platform = $this->createStandalonePlatformOperator('plat-v2-dup-'.Str::random(6).'@platform.test');
        $this->seedConfirmedReconciliation($order, $platform);

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/approve')
            ->assertOk();

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/approve')
            ->assertStatus(422);
    }

    public function test_reject_flow(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-REJ'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $platform = $this->createStandalonePlatformOperator('plat-v2-rej-'.Str::random(6).'@platform.test');

        $res = $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/reject', [
                'reason' => 'Mismatch on reference',
            ]);

        $res->assertOk();
        $res->assertJsonPath('data.status', PaymentOrderStatus::Rejected->value);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'reject_transfer')->count());
    }

    public function test_invoice_service_rejects_without_valid_payment(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-INV'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $payment = Payment::withoutGlobalScopes()->create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $order->company_id,
            'branch_id'          => $tenant['branch']->id,
            'invoice_id'         => null,
            'payment_order_id'   => null,
            'created_by_user_id' => $tenant['user']->id,
            'method'             => 'bank_transfer',
            'amount'             => $order->total,
            'currency'           => $order->currency,
            'reference'          => $order->reference_code,
            'status'             => 'completed',
            'created_at'         => now(),
        ]);

        $this->expectException(\DomainException::class);
        app(InvoiceService::class)->createFromPayment($payment, $order, (int) $tenant['user']->id);
    }
}
