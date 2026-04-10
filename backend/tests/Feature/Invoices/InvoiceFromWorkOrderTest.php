<?php

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\WalletService;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceFromWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private User $user;
    private Customer $customer;
    private Vehicle $vehicle;
    private WorkOrderService $workOrderService;
    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company  = $this->createCompany();
        $this->branch   = $this->createBranch($this->company);
        $this->user     = $this->createUser($this->company, $this->branch);
        $this->createActiveSubscription($this->company);

        $this->customer = Customer::create([
            'uuid'       => Str::uuid(),
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'type'       => 'fleet',
            'name'       => 'Fleet From-WO Test',
            'is_active'  => true,
        ]);

        $this->vehicle = Vehicle::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->company->id,
            'branch_id'          => $this->branch->id,
            'customer_id'        => $this->customer->id,
            'created_by_user_id' => $this->user->id,
            'plate_number'       => 'WO-INV-01',
            'make'               => 'Test',
            'model'              => 'Fleet',
            'year'               => 2024,
        ]);

        $this->workOrderService = app(WorkOrderService::class);
        $this->walletService    = app(WalletService::class);
    }

    /**
     * Completed work order → invoice via API must persist the same Idempotency-Key on the invoice row
     * so payment (especially wallet) can derive wallet_idempotency_key.
     */
    public function test_from_work_order_persists_idempotency_key_on_invoice(): void
    {
        $order = $this->workOrderService->create(
            [
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'items' => [[
                    'item_type' => 'labor',
                    'name' => 'Labor',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_rate' => 15,
                    'product_id' => null,
                ]],
            ],
            $this->company->id,
            $this->branch->id,
            $this->user->id,
        );

        $this->workOrderService->transition($order, WorkOrderStatus::Approved);
        $order->refresh();
        $this->workOrderService->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();
        $this->workOrderService->transition($order, WorkOrderStatus::Completed, [
            'technician_notes' => 'ready to invoice',
            'mileage_out'      => 12000,
        ]);
        $order->refresh();

        $idempotencyKey = (string) Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson("/api/v1/invoices/from-work-order/{$order->id}");

        $response->assertStatus(201);
        $invoiceId = (int) $response->json('data.id');
        $this->assertNotNull($invoiceId);

        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertSame($idempotencyKey, $invoice->idempotency_key);
        $this->assertSame(WorkOrderStatus::Completed->value, $order->fresh()->status->value);

        $this->assertDatabaseHas('invoices', [
            'id'                => $invoiceId,
            'idempotency_key'   => $idempotencyKey,
            'source_type'       => \App\Models\WorkOrder::class,
            'source_id'         => $order->id,
        ]);
    }

    /**
     * Full path: issue from work order (header key stored) then wallet pay without explicit wallet_idempotency_key.
     */
    public function test_wallet_pay_after_from_work_order_succeeds_using_invoice_idempotency_derivation(): void
    {
        $order = $this->workOrderService->create(
            [
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'items' => [[
                    'item_type' => 'labor',
                    'name' => 'Labor',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'tax_rate' => 15,
                    'product_id' => null,
                ]],
            ],
            $this->company->id,
            $this->branch->id,
            $this->user->id,
        );

        $this->workOrderService->transition($order, WorkOrderStatus::Approved);
        $order->refresh();
        $this->workOrderService->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();
        $this->workOrderService->transition($order, WorkOrderStatus::Completed, [
            'technician_notes' => 'invoice + wallet',
            'mileage_out'      => 13000,
        ]);

        $issueKey = (string) Str::uuid();

        $issueResponse = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $issueKey])
            ->postJson("/api/v1/invoices/from-work-order/{$order->id}");

        $issueResponse->assertStatus(201);
        $invoiceId = (int) $issueResponse->json('data.id');
        $due       = (float) $issueResponse->json('data.due_amount');
        $this->assertGreaterThan(0, $due);

        $this->walletService->topUpFleet(
            $this->company->id,
            $this->customer->id,
            null,
            $due + 5000,
            null,
            null,
            $this->user->id,
            'trace-wotest-fleet',
            (string) Str::uuid(),
            $this->branch->id,
            null,
        );

        $this->walletService->transferToVehicle(
            $this->company->id,
            $this->customer->id,
            $this->vehicle->id,
            $due + 1000,
            null,
            null,
            $this->user->id,
            'trace-wotest-xfer',
            (string) Str::uuid(),
            $this->branch->id,
            null,
        );

        $payKey = (string) Str::uuid();

        $payResponse = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $payKey])
            ->postJson("/api/v1/invoices/{$invoiceId}/pay", [
                'amount' => $due,
                'method' => 'wallet',
            ]);

        $payResponse->assertStatus(201);

        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame($issueKey, $invoice->idempotency_key);
    }
}
