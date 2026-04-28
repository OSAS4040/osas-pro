<?php

declare(strict_types=1);

namespace Tests\Feature\SubscriptionsV2;

use App\Models\Plan;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\AuditLog;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\BankTransactionImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReconciliationPhase3Test extends TestCase
{
    private function makePlan(): Plan
    {
        return Plan::query()->create([
            'slug'              => 'v2p3-'.Str::lower(Str::random(8)),
            'name'              => 'V2 Phase3 Plan',
            'name_ar'           => 'باقة',
            'price_monthly'     => 1000,
            'price_yearly'      => 10000,
            'currency'          => 'SAR',
            'max_branches'      => 3,
            'max_users'         => 10,
            'max_products'      => 100,
            'grace_period_days' => 15,
            'features'          => ['pos' => true],
            'is_active'         => true,
            'sort_order'        => 0,
        ]);
    }

    public function test_import_bank_transactions_persists_rows_and_audit(): void
    {
        $platform = $this->createStandalonePlatformOperator('p3-import-'.Str::random(6).'@platform.test');

        $res = $this->actingAs($platform, 'sanctum')->postJson('/api/v1/admin/subscriptions/bank-transactions/import', [
            'rows' => [
                [
                    'amount'            => 500.5,
                    'transaction_date'  => now()->toDateString(),
                    'currency'          => 'SAR',
                    'sender_name'       => 'ACME LLC',
                    'bank_reference'    => 'REF-'.Str::upper(Str::random(8)),
                    'description'       => 'Inbound',
                ],
            ],
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('bank_transactions', ['amount' => '500.50', 'currency' => 'SAR']);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'import_bank_transactions')->count());
    }

    public function test_import_service_extracts_reference_from_description(): void
    {
        $svc = app(BankTransactionImportService::class);
        $platform = $this->createStandalonePlatformOperator('p3-import-svc-'.Str::random(6).'@platform.test');
        $ref = 'SUB-ORD-'.Str::upper(Str::random(10));
        $ids = $svc->import([
            [
                'amount'           => 100,
                'transaction_date' => now()->toDateString(),
                'description'      => 'Payment '.$ref.' done',
            ],
        ], (int) $platform->id);

        $this->assertNotEmpty($ids);
        $tx = BankTransaction::query()->findOrFail($ids[0]);
        $this->assertSame($ref, $tx->reference_extracted);
    }

    public function test_auto_match_runs_on_submit_transfer_when_bank_row_exists(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $ref     = 'SUB-ORD-AUTO'.Str::upper(Str::random(8));
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => $ref,
            'status'         => PaymentOrderStatus::PendingTransfer,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        BankTransaction::query()->create([
            'import_batch_uuid'   => (string) Str::uuid(),
            'transaction_date'    => now()->toDateString(),
            'amount'              => 1150,
            'currency'            => 'SAR',
            'sender_name'         => 'Same Sender',
            'description'         => 'Transfer for '.$ref,
            'reference_extracted' => null,
            'is_matched'          => false,
        ]);

        $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders/'.$order->id.'/submit-transfer', [
                'amount'        => 1150,
                'transfer_date' => now()->toDateString(),
                'bank_name'     => 'Test Bank',
                'sender_name'   => 'Same Sender',
            ])
            ->assertOk();

        $order->refresh();
        $this->assertTrue($order->hasConfirmedMatch());
        $this->assertSame(PaymentOrderStatus::Matched, $order->status);
    }

    public function test_approve_without_match_returns_422(): void
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
            'reference_code' => 'SUB-ORD-NOM'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $platform = $this->createStandalonePlatformOperator('p3-nom-'.Str::random(6).'@platform.test');

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/approve')
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'No reconciliation match.']);
    }

    public function test_manual_match_endpoint_confirms_and_allows_approve(): void
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
            'reference_code' => 'SUB-ORD-MM'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $tx = BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date'  => now()->toDateString(),
            'amount'            => 1150,
            'currency'          => 'SAR',
            'is_matched'        => false,
        ]);

        $platform = $this->createStandalonePlatformOperator('p3-mm-'.Str::random(6).'@platform.test');

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/review-queue/'.$order->id.'/match', [
                'bank_transaction_id' => $tx->id,
            ])
            ->assertOk();

        $this->assertTrue($order->fresh()->hasConfirmedMatch());

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/approve')
            ->assertOk();

        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'manual_match')->count());
    }

    public function test_cannot_use_same_bank_transaction_for_two_orders(): void
    {
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();

        $makeOrder = fn (string $ref) => PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => $ref,
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $o1 = $makeOrder('SUB-ORD-D1'.Str::upper(Str::random(5)));
        $o2 = $makeOrder('SUB-ORD-D2'.Str::upper(Str::random(5)));

        $tx = BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date'  => now()->toDateString(),
            'amount'            => 1150,
            'currency'          => 'SAR',
            'is_matched'        => false,
        ]);

        $platform = $this->createStandalonePlatformOperator('p3-dup-'.Str::random(6).'@platform.test');

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/review-queue/'.$o1->id.'/match', [
                'bank_transaction_id' => $tx->id,
            ])
            ->assertOk();

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/review-queue/'.$o2->id.'/match', [
                'bank_transaction_id' => $tx->id,
            ])
            ->assertStatus(422);
    }

    public function test_matched_bank_transaction_is_immutable(): void
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
            'reference_code' => 'SUB-ORD-IM'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $tx = BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date'  => now()->toDateString(),
            'amount'            => 1150,
            'currency'          => 'SAR',
            'is_matched'        => false,
        ]);

        $platform = $this->createStandalonePlatformOperator('p3-im-'.Str::random(6).'@platform.test');

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/review-queue/'.$order->id.'/match', [
                'bank_transaction_id' => $tx->id,
            ])
            ->assertOk();

        $tx->refresh();
        $this->expectException(\DomainException::class);
        $tx->amount = 1;
        $tx->save();
    }

    public function test_review_queue_reject_without_prior_match(): void
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
            'reference_code' => 'SUB-ORD-RQ'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $platform = $this->createStandalonePlatformOperator('p3-rq-'.Str::random(6).'@platform.test');

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/review-queue/'.$order->id.'/reject', [
                'reason' => 'No credible bank line',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentOrderStatus::Rejected->value);

        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'reject_review')->count());
    }

    public function test_upload_receipt_after_submit(): void
    {
        Storage::fake('public');
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-RC'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::PendingTransfer,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders/'.$order->id.'/submit-transfer', [
                'amount'        => 1150,
                'transfer_date' => now()->toDateString(),
                'bank_name'     => 'Test Bank',
            ])
            ->assertOk();

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $res = $this->actingAs($tenant['user'], 'sanctum')
            ->post('/api/v1/subscriptions/payment-orders/'.$order->id.'/upload-receipt', [
                'receipt'        => $file,
                'bank_reference' => 'BR-123',
                'notes'          => 'Please verify',
            ]);

        $res->assertOk();
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'upload_receipt')->count());
    }

    public function test_upload_receipt_blocked_after_approval(): void
    {
        Storage::fake('public');
        $tenant = $this->createTenant('owner');
        $plan    = $this->makePlan();
        $order    = PaymentOrder::query()->create([
            'company_id'     => $tenant['company']->id,
            'plan_id'        => $plan->id,
            'amount'         => 1000,
            'vat'            => 150,
            'total'          => 1150,
            'currency'       => 'SAR',
            'reference_code' => 'SUB-ORD-AP'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::PendingTransfer,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        $this->actingAs($tenant['user'], 'sanctum')
            ->postJson('/api/v1/subscriptions/payment-orders/'.$order->id.'/submit-transfer', [
                'amount'        => 1150,
                'transfer_date' => now()->toDateString(),
                'bank_name'     => 'Test Bank',
            ])
            ->assertOk();

        $tx = BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date'  => now()->toDateString(),
            'amount'            => 1150,
            'currency'          => 'SAR',
            'is_matched'        => false,
        ]);

        $platform = $this->createStandalonePlatformOperator('p3-apr-'.Str::random(6).'@platform.test');

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/review-queue/'.$order->id.'/match', [
                'bank_transaction_id' => $tx->id,
            ])
            ->assertOk();

        $this->actingAs($platform, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/payment-orders/'.$order->id.'/approve')
            ->assertOk();

        $file = UploadedFile::fake()->create('late.pdf', 50, 'application/pdf');

        $this->actingAs($tenant['user'], 'sanctum')
            ->post('/api/v1/subscriptions/payment-orders/'.$order->id.'/upload-receipt', [
                'receipt' => $file,
            ])
            ->assertStatus(422);
    }

    public function test_review_queue_index_includes_candidates(): void
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
            'reference_code' => 'SUB-ORD-RV'.Str::upper(Str::random(6)),
            'status'         => PaymentOrderStatus::AwaitingReview,
            'expires_at'     => now()->addDay(),
            'created_by'     => $tenant['user']->id,
        ]);

        BankTransaction::query()->create([
            'import_batch_uuid' => (string) Str::uuid(),
            'transaction_date'  => now()->toDateString(),
            'amount'            => 1150,
            'currency'          => 'SAR',
            'description'       => $order->reference_code,
            'is_matched'        => false,
        ]);

        $platform = $this->createStandalonePlatformOperator('p3-rv-'.Str::random(6).'@platform.test');

        $res = $this->actingAs($platform, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/review-queue');

        $res->assertOk();
        $rows = $res->json('data.data');
        if (! is_array($rows)) {
            $rows = $res->json('data');
        }
        $payload = collect(is_array($rows) ? $rows : []);
        $row     = $payload->firstWhere('payment_order.id', $order->id);
        $this->assertNotNull($row);
        $this->assertNotEmpty($row['candidates']);
    }
}
