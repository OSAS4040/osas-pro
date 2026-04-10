<?php

namespace Tests\Feature\Purchases;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\GoodsReceiptService;
use App\Services\InventoryService;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    private PurchaseOrderService $poService;
    private GoodsReceiptService  $receiptService;
    private InventoryService     $inventoryService;

    private array    $tenant;
    private Supplier $supplier;
    private Product  $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poService        = app(PurchaseOrderService::class);
        $this->receiptService   = app(GoodsReceiptService::class);
        $this->inventoryService = app(InventoryService::class);

        $this->tenant = $this->createTenant();

        $unit = Unit::create([
            'uuid'       => Str::uuid(),
            'company_id' => $this->tenant['company']->id,
            'name'       => 'Each',
            'symbol'     => 'ea',
            'is_active'  => true,
        ]);

        $this->product = Product::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->tenant['company']->id,
            'created_by_user_id' => $this->tenant['user']->id,
            'name'               => 'Oil Filter',
            'sku'                => 'OF-001',
            'product_type'       => 'physical',
            'unit_id'            => $unit->id,
            'sale_price'         => 30,
            'cost_price'         => 20,
            'track_inventory'    => true,
            'is_active'          => true,
        ]);

        $this->supplier = Supplier::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->tenant['company']->id,
            'created_by_user_id' => $this->tenant['user']->id,
            'name'               => 'Test Supplier',
            'is_active'          => true,
            'status'             => 'active',
        ]);
    }

    private function createTestPO(int $qty = 5)
    {
        return $this->poService->createPO(
            data: [
                'supplier_id' => $this->supplier->id,
                'items'       => [[
                    'name'       => 'Oil Filter',
                    'product_id' => $this->product->id,
                    'quantity'   => $qty,
                    'unit_cost'  => 20,
                    'tax_rate'   => 15,
                ]],
            ],
            companyId: $this->tenant['company']->id,
            branchId:  $this->tenant['branch']->id,
            userId:    $this->tenant['user']->id,
            traceId:   'trace-setup',
        );
    }

    public function test_creates_purchase_order_with_items(): void
    {
        $po = $this->poService->createPO(
            data: [
                'supplier_id' => $this->supplier->id,
                'items'       => [
                    ['name' => 'Oil Filter', 'product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 25, 'tax_rate' => 15],
                    ['name' => 'Air Filter', 'product_id' => null,               'quantity' => 5,  'unit_cost' => 15, 'tax_rate' => 15],
                ],
            ],
            companyId: $this->tenant['company']->id,
            branchId:  $this->tenant['branch']->id,
            userId:    $this->tenant['user']->id,
            traceId:   'trace-po-01',
        );

        $this->assertNotNull($po->id);
        $this->assertStringStartsWith('PO-', $po->reference_number);
        $this->assertCount(2, $po->items);

        $status = $po->status instanceof \BackedEnum ? $po->status->value : $po->status;
        $this->assertEquals('pending', $status);
    }

    public function test_po_status_transition_pending_to_ordered(): void
    {
        $po      = $this->createTestPO();
        $updated = $this->poService->transition($po, 'ordered');
        $status  = $updated->status instanceof \BackedEnum ? $updated->status->value : $updated->status;

        $this->assertEquals('ordered', $status);
    }

    public function test_invalid_po_transition_throws_exception(): void
    {
        $po = $this->createTestPO();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Invalid PO status transition/');

        $this->poService->transition($po, 'received');
    }

    public function test_goods_receipt_creates_stock_movements(): void
    {
        $po = $this->createTestPO();

        $balanceBefore = $this->inventoryService->getBalance(
            $this->tenant['company']->id, $this->tenant['branch']->id, $this->product->id
        );

        $receipt = $this->receiptService->createReceipt(
            purchase: $po->load('items'),
            data: [
                'items' => $po->items->map(fn($item) => [
                    'purchase_item_id'  => $item->id,
                    'received_quantity' => (float) $item->quantity,
                ])->values()->toArray(),
            ],
            userId:  $this->tenant['user']->id,
            traceId: 'trace-grn-01',
        );

        $this->assertNotNull($receipt->id);
        $this->assertStringStartsWith('GRN-', $receipt->grn_number);

        $balanceAfter = $this->inventoryService->getBalance(
            $this->tenant['company']->id, $this->tenant['branch']->id, $this->product->id
        );

        $this->assertGreaterThan($balanceBefore, $balanceAfter);
    }

    public function test_receipt_marks_po_as_received_when_all_items_done(): void
    {
        $po = $this->createTestPO();

        $this->receiptService->createReceipt(
            purchase: $po->load('items'),
            data: [
                'items' => $po->items->map(fn($item) => [
                    'purchase_item_id'  => $item->id,
                    'received_quantity' => (float) $item->quantity,
                ])->values()->toArray(),
            ],
            userId:  $this->tenant['user']->id,
            traceId: 'trace-grn-full',
        );

        $po->refresh();
        $status = $po->status instanceof \BackedEnum ? $po->status->value : $po->status;
        $this->assertEquals('received', $status);
    }

    public function test_partial_receipt_marks_po_as_partial(): void
    {
        $po   = $this->createTestPO(qty: 10);
        $item = $po->items->first();

        $this->receiptService->createReceipt(
            purchase: $po->load('items'),
            data: ['items' => [['purchase_item_id' => $item->id, 'received_quantity' => 5]]],
            userId:  $this->tenant['user']->id,
            traceId: 'trace-grn-partial',
        );

        $po->refresh();
        $status = $po->status instanceof \BackedEnum ? $po->status->value : $po->status;
        $this->assertEquals('partial', $status);
    }

    public function test_cancelled_po_cannot_receive_goods(): void
    {
        $po = $this->createTestPO();
        $this->poService->transition($po, 'ordered');
        $po->refresh();
        $this->poService->transition($po, 'cancelled');
        $po->refresh();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cannot receive goods/');

        $this->receiptService->createReceipt(
            purchase: $po->load('items'),
            data: ['items' => [['purchase_item_id' => $po->items->first()->id, 'received_quantity' => 1]]],
            userId:  $this->tenant['user']->id,
            traceId: 'trace-fail',
        );
    }

    public function test_tenant_isolation_prevents_cross_company_access(): void
    {
        $po      = $this->createTestPO();
        $tenant2 = $this->createTenant();

        $response = $this->actingAsUser($tenant2['user'])
            ->getJson("/api/v1/purchases/{$po->id}");

        $response->assertNotFound();
    }
}
