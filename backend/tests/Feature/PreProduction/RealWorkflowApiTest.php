<?php

namespace Tests\Feature\PreProduction;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Unit;
use App\Services\InventoryService;
use App\Services\SensitivePreviewTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * مسار تشغيلي كامل عبر HTTP (كما يفعل عميل حقيقي): عميل → مركبة → أمر عمل + خدمة + قطعة
 * → إكمال → فاتورة من الأمر → تعبئة/تحويل محفظة أسطول → دفع محفظة.
 *
 * يتحقق من: trace_id في الاستجابات، عدم تكرار فاتورة لنفس أمر العمل، مخزون غير سالب
 * وميزان دفتر القيود للقيود المرتبطة بالفاتورة.
 */
#[Group('pre-production')]
class RealWorkflowApiTest extends TestCase
{
    private \App\Models\Company $company;

    private \App\Models\Branch $branch;

    private \App\Models\User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company  = $this->createCompany();
        $this->branch   = $this->createBranch($this->company);
        $this->user     = $this->createUser($this->company, $this->branch);
        $this->createActiveSubscription($this->company);

        $unit = Unit::create([
            'company_id' => $this->company->id,
            'name'       => 'Piece', 'symbol' => 'pcs',
            'type'       => 'quantity', 'is_base' => true,
            'is_system'  => false, 'is_active' => true,
        ]);

        $this->product = Product::create([
            'uuid'              => Str::uuid(),
            'company_id'        => $this->company->id,
            'name'              => 'PreProd Part',
            'sku'               => 'PP-' . Str::random(6),
            'product_type'      => 'physical',
            'unit_id'           => $unit->id,
            'sale_price'        => 25.00,
            'cost_price'        => 12.00,
            'track_inventory'   => true,
            'is_active'         => true,
        ]);

        app(InventoryService::class)->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  100,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'preprod-seed',
        );
    }

    public function test_end_to_end_operational_chain_and_data_integrity(): void
    {
        $auth = fn () => $this->actingAs($this->user, 'sanctum');

        $cid = $this->company->id;

        $qtyBefore = (float) Inventory::where('company_id', $cid)
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $rCustomer = $auth()->postJson('/api/v1/customers', [
            'type' => 'b2b',
            'name' => 'PreProd Fleet Co',
            'phone' => '0500000001',
        ]);
        $rCustomer->assertStatus(201)
            ->assertJsonStructure(['data', 'trace_id'])
            ->assertJsonPath('data.type', 'b2b');
        $customerId = (int) $rCustomer->json('data.id');

        $plate = 'PRE-' . strtoupper(Str::random(5));
        $rVehicle = $auth()->postJson('/api/v1/vehicles', [
            'customer_id'  => $customerId,
            'plate_number' => $plate,
            'make'         => 'Test',
            'model'        => 'Truck',
            'year'         => 2023,
        ]);
        $rVehicle->assertStatus(201)
            ->assertJsonStructure(['data', 'trace_id']);
        $vehicleId = (int) $rVehicle->json('data.id');

        $rWo = $auth()->postJson('/api/v1/work-orders', [
            'customer_id' => $customerId,
            'vehicle_id'  => $vehicleId,
            'items'       => [
                [
                    'item_type' => 'labor',
                    'name'      => 'Oil change labor',
                    'quantity'  => 1,
                    'unit_price'=> 80,
                    'tax_rate'  => 15,
                ],
                [
                    'item_type'  => 'part',
                    'name'       => $this->product->name,
                    'product_id' => $this->product->id,
                    'quantity'   => 2,
                    'unit_price' => 25,
                    'tax_rate'   => 15,
                ],
            ],
        ]);
        $rWo->assertStatus(201)->assertJsonStructure(['data', 'trace_id']);
        $woId    = (int) $rWo->json('data.id');
        $version = (int) $rWo->json('data.version');

        $rPrev = $auth()->postJson('/api/v1/sensitive-operations/preview', [
            'operation' => SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            'work_order_ids' => [$woId],
        ]);
        $rPrev->assertOk();
        $prevTok = (string) $rPrev->json('data.sensitive_preview_token');

        $rAp = $auth()->patchJson("/api/v1/work-orders/{$woId}/status", [
            'status' => 'approved',
            'version' => $version,
            'sensitive_preview_token' => $prevTok,
        ]);
        $rAp->assertOk();
        $version = (int) $rAp->json('data.version');

        $rIp = $auth()->patchJson("/api/v1/work-orders/{$woId}/status", [
            'status'  => 'in_progress',
            'version' => $version,
        ]);
        $rIp->assertOk()->assertJsonStructure(['data', 'trace_id']);
        $version = (int) $rIp->json('data.version');

        $rDone = $auth()->patchJson("/api/v1/work-orders/{$woId}/status", [
            'status'           => 'completed',
            'version'          => $version,
            'technician_notes' => 'preprod complete',
            'mileage_out'      => 88000,
        ]);
        $rDone->assertOk()->assertJsonStructure(['data', 'trace_id']);

        $issueKey = (string) Str::uuid();
        $rInv = $auth()->withHeaders(['Idempotency-Key' => $issueKey])
            ->postJson("/api/v1/invoices/from-work-order/{$woId}");
        $rInv->assertStatus(201)->assertJsonStructure(['data', 'trace_id']);
        $invoiceId = (int) $rInv->json('data.id');
        $due       = (float) $rInv->json('data.due_amount');
        $this->assertGreaterThan(0, $due);

        $this->assertSame(
            1,
            Invoice::withoutGlobalScopes()->where('company_id', $cid)->where('source_id', $woId)->count(),
            'Exactly one invoice per work order source_id'
        );

        $qtyAfterIssue = (float) Inventory::where('company_id', $cid)
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');
        $this->assertEqualsWithDelta($qtyBefore - 2, $qtyAfterIssue, 0.0001, 'Stock must decrease by invoiced part qty');

        $replay = $auth()->withHeaders(['Idempotency-Key' => $issueKey])
            ->postJson("/api/v1/invoices/from-work-order/{$woId}");
        $replay->assertStatus(201);
        $this->assertTrue($replay->headers->has('X-Idempotent-Replayed'));
        $this->assertSame($invoiceId, (int) $replay->json('data.id'));

        $otherKey = (string) Str::uuid();
        $dup = $auth()->withHeaders(['Idempotency-Key' => $otherKey])
            ->postJson("/api/v1/invoices/from-work-order/{$woId}");
        $dup->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('already', (string) $dup->json('message'));

        $topUpKey = (string) Str::uuid();
        $rTop = $auth()->withHeaders(['Idempotency-Key' => $topUpKey])
            ->postJson('/api/v1/wallet/top-up', [
                'customer_id'     => $customerId,
                'amount'          => $due + 5000,
                'target'          => 'fleet',
                'idempotency_key' => $topUpKey,
            ]);
        $rTop->assertStatus(201)->assertJsonStructure(['data', 'trace_id']);

        $xferKey = (string) Str::uuid();
        $rXfer = $auth()->withHeaders(['Idempotency-Key' => $xferKey])
            ->postJson('/api/v1/wallet/transfer', [
                'customer_id'     => $customerId,
                'vehicle_id'      => $vehicleId,
                'amount'          => $due + 500,
                'idempotency_key' => $xferKey,
            ]);
        $rXfer->assertStatus(201)->assertJsonStructure(['data', 'trace_id']);

        $payKey = (string) Str::uuid();
        $rPay = $auth()->withHeaders(['Idempotency-Key' => $payKey])
            ->postJson("/api/v1/invoices/{$invoiceId}/pay", [
                'amount' => $due,
                'method' => 'wallet',
            ]);
        $rPay->assertStatus(201)->assertJsonStructure(['data', 'trace_id']);

        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);

        $negStock = DB::table('inventory')
            ->where('company_id', $cid)
            ->where('quantity', '<', 0)
            ->count();
        $this->assertSame(0, $negStock, 'No negative on-hand stock rows');

        $dupInvHash = DB::select(
            'SELECT invoice_hash, COUNT(*)::int AS c FROM invoices WHERE company_id = ? GROUP BY invoice_hash HAVING COUNT(*) > 1',
            [$cid]
        );
        $this->assertCount(0, $dupInvHash, 'No duplicate invoice_hash within company');

        $jeForInvoice = JournalEntry::withoutGlobalScopes()
            ->where('company_id', $cid)
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoiceId)
            ->get();
        $this->assertGreaterThan(
            0,
            $jeForInvoice->count(),
            'Posted sale invoice must have at least one journal entry (ledger)'
        );
        foreach ($jeForInvoice as $je) {
            $this->assertEqualsWithDelta(
                (float) $je->total_debit,
                (float) $je->total_credit,
                0.02,
                "Journal entry {$je->id} must balance"
            );
        }
    }

    public function test_wallet_payment_replay_same_idempotency_does_not_double_debit(): void
    {
        $auth = fn () => $this->actingAs($this->user, 'sanctum');

        $customer = Customer::create([
            'uuid'       => Str::uuid(),
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'type'       => 'b2b',
            'name'       => 'Pay Replay Customer',
            'is_active'  => true,
        ]);

        $vehicle = \App\Models\Vehicle::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->company->id,
            'branch_id'          => $this->branch->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $this->user->id,
            'plate_number'       => 'PAY-' . strtoupper(Str::random(4)),
            'make'               => 'X', 'model' => 'Y', 'year' => 2022,
        ]);

        $order = app(\App\Services\WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [[
                    'item_type' => 'labor',
                    'name' => 'Labor',
                    'quantity' => 1,
                    'unit_price' => 50,
                    'tax_rate' => 15,
                    'product_id' => null,
                ]],
            ],
            $this->company->id,
            $this->branch->id,
            $this->user->id,
        );

        app(\App\Services\WorkOrderService::class)->transition($order, \App\Enums\WorkOrderStatus::Approved);
        $order->refresh();
        app(\App\Services\WorkOrderService::class)->transition($order, \App\Enums\WorkOrderStatus::InProgress);
        $order->refresh();
        app(\App\Services\WorkOrderService::class)->transition($order, \App\Enums\WorkOrderStatus::Completed, [
            'technician_notes' => 'pay idem',
            'mileage_out'      => 1000,
        ]);

        $issueKey = (string) Str::uuid();
        $issue = $auth()->withHeaders(['Idempotency-Key' => $issueKey])
            ->postJson("/api/v1/invoices/from-work-order/{$order->id}");
        $issue->assertStatus(201);
        $invoiceId = (int) $issue->json('data.id');
        $due       = (float) $issue->json('data.due_amount');

        app(\App\Services\WalletService::class)->topUpFleet(
            $this->company->id,
            $customer->id,
            null,
            $due + 2000,
            null,
            null,
            $this->user->id,
            'preprod-pay-1',
            (string) Str::uuid(),
            $this->branch->id,
            null,
        );
        app(\App\Services\WalletService::class)->transferToVehicle(
            $this->company->id,
            $customer->id,
            $vehicle->id,
            $due + 200,
            null,
            null,
            $this->user->id,
            'preprod-pay-2',
            (string) Str::uuid(),
            $this->branch->id,
            null,
        );

        $payKey = (string) Str::uuid();
        $p1 = $auth()->withHeaders(['Idempotency-Key' => $payKey])
            ->postJson("/api/v1/invoices/{$invoiceId}/pay", ['amount' => $due, 'method' => 'wallet']);
        $p1->assertStatus(201);

        $p2 = $auth()->withHeaders(['Idempotency-Key' => $payKey])
            ->postJson("/api/v1/invoices/{$invoiceId}/pay", ['amount' => $due, 'method' => 'wallet']);
        $p2->assertStatus(201);
        $this->assertTrue($p2->headers->has('X-Idempotent-Replayed'));

        $payCount = DB::table('payments')
            ->where('company_id', $this->company->id)
            ->where('invoice_id', $invoiceId)
            ->count();
        $this->assertSame(1, $payCount, 'Replay must not create a second payment row');
    }
}
