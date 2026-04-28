<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\Bay;
use App\Models\Booking;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Models\ApprovalWorkflow;
use App\Services\InventoryService;
use App\Services\ReservationService;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_03_PROGRESS_REPORT.md — عقد 409 عبر عدة موارد API
 */
#[Group('phase3')]
class ConflictErrorContractTest extends TestCase
{
    public function test_409_error_contract_is_consistent_for_transition_rejections(): void
    {
        $tenant = $this->createTenant();

        $invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number'     => 'INV-CNT-' . Str::upper(Str::random(6)),
            'status'             => 'partial_paid',
            'type'               => 'sale',
            'customer_type'      => 'b2c',
            'subtotal'           => 100,
            'tax_amount'         => 15,
            'total'              => 115,
            'paid_amount'        => 50,
            'due_amount'         => 65,
            'currency'           => 'SAR',
            'issued_at'          => now(),
        ]);

        $invoiceResponse = $this->actingAsUser($tenant['user'])
            ->withHeaders(['Idempotency-Key' => 'idem-' . Str::lower(Str::random(16))])
            ->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'draft']);
        $this->assertConflictContract($invoiceResponse);

        $unit = Unit::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'name'       => 'Each',
            'symbol'     => 'ea',
            'is_active'  => true,
        ]);
        $product = Product::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'created_by_user_id' => $tenant['user']->id,
            'name'               => 'Contract Product',
            'sku'                => 'CP-' . Str::upper(Str::random(4)),
            'product_type'       => 'physical',
            'unit_id'            => $unit->id,
            'sale_price'         => 30,
            'cost_price'         => 20,
            'track_inventory'    => true,
            'is_active'          => true,
        ]);
        $supplier = Supplier::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'created_by_user_id' => $tenant['user']->id,
            'name'               => 'Contract Supplier',
            'is_active'          => true,
            'status'             => 'active',
        ]);
        $purchase = Purchase::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'supplier_id'        => $supplier->id,
            'created_by_user_id' => $tenant['user']->id,
            'reference_number'   => 'PO-CNT-' . Str::upper(Str::random(6)),
            'status'             => 'received',
            'subtotal'           => 100,
            'tax_amount'         => 15,
            'total'              => 115,
            'currency'           => 'SAR',
        ]);
        $item = PurchaseItem::create([
            'company_id'        => $tenant['company']->id,
            'purchase_id'       => $purchase->id,
            'product_id'        => $product->id,
            'name'              => 'Contract Product',
            'quantity'          => 5,
            'received_quantity' => 5,
            'unit_cost'         => 20,
            'tax_rate'          => 15,
            'tax_amount'        => 15,
            'total'             => 115,
        ]);

        $purchaseResponse = $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/purchases/{$purchase->id}/receive", [
                'items' => [['purchase_item_id' => $item->id, 'received_qty' => 1]],
            ]);
        $this->assertConflictContract($purchaseResponse);

        $ticket = SupportTicket::create([
            'uuid'          => (string) Str::uuid(),
            'ticket_number' => 'TKT-CNT-' . Str::upper(Str::random(6)),
            'company_id'    => $tenant['company']->id,
            'branch_id'     => $tenant['branch']->id,
            'created_by'    => $tenant['user']->id,
            'subject'       => 'Contract check',
            'description'   => 'Ensure 409 payload is consistent.',
            'priority'      => 'medium',
            'status'        => 'closed',
            'closed_at'     => now(),
            'channel'       => 'portal',
            'sla_due_at'    => now()->addDay(),
        ]);

        $supportResponse = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/support/tickets/{$ticket->id}/status", ['status' => 'in_progress']);
        $this->assertConflictContract($supportResponse);
    }

    private function assertConflictContract(TestResponse $response, string $code = 'TRANSITION_NOT_ALLOWED'): void
    {
        $response->assertStatus(409)
            ->assertJsonStructure(['message', 'trace_id', 'code', 'status'])
            ->assertJsonPath('code', $code)
            ->assertJsonPath('status', 409);
    }

    public function test_fleet_portal_reject_credit_invalid_transition_returns_unified_409_contract(): void
    {
        $tenant = $this->createTenant();

        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'fleet',
            'name'       => 'Fleet Contract Customer',
            'phone'      => '0500000000',
            'is_active'  => true,
        ]);

        $fleetManager = $this->createUser($tenant['company'], $tenant['branch'], 'fleet_manager', [
            'customer_id' => $customer->id,
        ]);

        $vehicle = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $fleetManager->id,
            'plate_number'       => 'FLT-' . Str::upper(Str::random(5)),
            'make'               => 'Toyota',
            'model'              => 'Hilux',
            'year'               => 2024,
            'is_active'          => true,
        ]);

        $workOrder = WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $vehicle->id,
            'created_by_user_id' => $fleetManager->id,
            'order_number'       => 'WO-CNT-' . Str::upper(Str::random(6)),
            'status'             => 'pending_manager_approval',
            'priority'           => 'normal',
            'created_by_side'    => 'fleet',
            'approval_status'    => 'not_required',
            'credit_authorized'  => false,
            'estimated_total'    => 0,
            'actual_total'       => 0,
        ]);

        $response = $this->actingAsUser($fleetManager)
            ->postJson("/api/v1/fleet-portal/work-orders/{$workOrder->id}/reject-credit");

        $this->assertConflictContract($response);
        $response->assertJsonPath('message', 'Work order approval transition not_required -> rejected is not allowed.');
    }

    public function test_governance_workflow_approve_invalid_transition_returns_unified_409_contract(): void
    {
        $tenant = $this->createTenant();

        $workflow = ApprovalWorkflow::create([
            'uuid'          => (string) Str::uuid(),
            'company_id'    => $tenant['company']->id,
            'subject_type'  => WorkOrder::class,
            'subject_id'    => 99999,
            'policy_code'   => 'test.policy',
            'status'        => 'approved',
            'requested_by'  => $tenant['user']->id,
            'resolved_by'   => $tenant['user']->id,
            'resolved_at'   => now(),
            'requester_note'=> 'initial',
            'resolver_note' => 'done',
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/governance/workflows/{$workflow->id}/approve", ['note' => 'again']);

        $this->assertConflictContract($response);
        $response->assertJsonPath('message', 'Workflow status transition approved -> approved is not allowed.');
    }

    public function test_work_order_bay_task_booking_invalid_transitions_return_unified_409_contract(): void
    {
        $tenant = $this->createTenant();

        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'WO Guard Customer',
            'is_active'  => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number'       => 'WG-' . Str::upper(Str::random(5)),
            'make'               => 'Test',
            'model'              => 'Car',
            'year'               => 2023,
            'is_active'          => true,
        ]);

        $workOrder = WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $vehicle->id,
            'created_by_user_id' => $tenant['user']->id,
            'order_number'       => 'WO-WG-' . Str::upper(Str::random(6)),
            'status'             => 'pending_manager_approval',
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $this->assertConflictContract(
            $this->actingAsUser($tenant['user'])
                ->patchJson("/api/v1/work-orders/{$workOrder->id}/status", [
                    'status'  => 'delivered',
                    'version' => $workOrder->version,
                ])
        );

        $bay = Bay::create([
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'code'       => 'B-WG1',
            'name'       => 'Guard bay',
            'status'     => 'in_use',
        ]);

        $this->assertConflictContract(
            $this->actingAsUser($tenant['user'])
                ->patchJson("/api/v1/bays/{$bay->id}/status", ['status' => 'reserved'])
        );

        $task = Task::create([
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'title'      => 'Guard task',
            'status'     => 'completed',
        ]);

        $this->assertConflictContract(
            $this->actingAsUser($tenant['user'])
                ->patchJson("/api/v1/workshop/tasks/{$task->id}/status", ['status' => 'pending'])
        );

        $booking = Booking::create([
            'company_id'       => $tenant['company']->id,
            'branch_id'        => $tenant['branch']->id,
            'bay_id'           => $bay->id,
            'starts_at'        => now()->addHour(),
            'ends_at'          => now()->addHours(2),
            'duration_minutes' => 60,
            'status'           => 'completed',
        ]);

        $this->assertConflictContract(
            $this->actingAsUser($tenant['user'])
                ->patchJson("/api/v1/bookings/{$booking->id}", ['action' => 'start'])
        );
    }

    public function test_inventory_reservation_cancel_invalid_transition_returns_unified_409_contract(): void
    {
        $tenant = $this->createTenant();

        $unit = Unit::create([
            'company_id' => $tenant['company']->id,
            'name'       => 'Piece',
            'symbol'     => 'pcs',
            'type'       => 'quantity',
            'is_base'    => true,
            'is_system'  => false,
            'is_active'  => true,
        ]);

        $product = Product::create([
            'uuid'            => (string) Str::uuid(),
            'company_id'      => $tenant['company']->id,
            'created_by_user_id' => $tenant['user']->id,
            'name'            => 'Res Contract Product',
            'sku'             => 'RCP-' . Str::upper(Str::random(4)),
            'product_type'    => 'physical',
            'unit_id'         => $unit->id,
            'sale_price'      => 10,
            'track_inventory' => true,
            'is_active'       => true,
        ]);

        app(InventoryService::class)->addStock(
            companyId: $tenant['company']->id,
            branchId:  $tenant['branch']->id,
            productId: $product->id,
            quantity:  50,
            userId:    $tenant['user']->id,
            type:      'manual_add',
            traceId:   'conflict-res-setup',
        );

        $reservation = app(ReservationService::class)->reserve(
            companyId:     $tenant['company']->id,
            branchId:      $tenant['branch']->id,
            productId:     $product->id,
            quantity:      5,
            userId:        $tenant['user']->id,
            referenceType: 'work_order',
            referenceId:   1,
            traceId:       'conflict-res-1',
        );

        app(ReservationService::class)->consume($reservation, 'conflict-res-consume');
        $reservation->refresh();

        $response = $this->actingAsUser($tenant['user'])
            ->withHeaders(['Idempotency-Key' => 'idem-' . Str::lower(Str::random(16))])
            ->patchJson("/api/v1/inventory/reservations/{$reservation->id}/cancel");

        $this->assertConflictContract($response);
        $response->assertJsonPath(
            'message',
            'Reservation status transition consumed -> cancel is not allowed.'
        );
    }
}
