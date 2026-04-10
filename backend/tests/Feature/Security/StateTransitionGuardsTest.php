<?php

namespace Tests\Feature\Security;

use App\Models\Bay;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Services\SensitivePreviewTokenService;
use App\Services\WorkOrderService;
use Illuminate\Support\Str;
use Tests\TestCase;

class StateTransitionGuardsTest extends TestCase
{
    public function test_invoice_update_allows_valid_transition_and_updates_state(): void
    {
        $tenant = $this->createTenant();

        $invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number'     => 'INV-TST-' . Str::upper(Str::random(6)),
            'status'             => 'pending',
            'type'               => 'sale',
            'customer_type'      => 'b2c',
            'subtotal'           => 100,
            'tax_amount'         => 15,
            'total'              => 115,
            'paid_amount'        => 50,
            'due_amount'         => 65,
            'currency'           => 'SAR',
            'issued_at'          => now(),
            'trace_id'           => 'trace-test-invoice-transition',
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->withHeaders(['Idempotency-Key' => 'idem-' . Str::lower(Str::random(16))])
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'status' => 'draft',
            ]);

        $response->assertOk();
        $invoice->refresh();
        $this->assertSame('draft', $invoice->status->value);
    }

    public function test_invoice_update_rejects_transition_and_keeps_state_stable(): void
    {
        $tenant = $this->createTenant();

        $invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number'     => 'INV-TST-' . Str::upper(Str::random(6)),
            'status'             => 'partial_paid',
            'type'               => 'sale',
            'customer_type'      => 'b2c',
            'subtotal'           => 100,
            'tax_amount'         => 15,
            'total'              => 115,
            'paid_amount'        => 50,
            'due_amount'         => 65,
            'currency'           => 'SAR',
            'notes'              => 'stable-before-reject',
            'issued_at'          => now(),
            'trace_id'           => 'trace-test-invoice-transition-reject',
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->withHeaders(['Idempotency-Key' => 'idem-' . Str::lower(Str::random(16))])
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'status' => 'draft',
                'notes' => 'should-not-change',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Invoice status transition partial_paid -> draft is not allowed.');

        $invoice->refresh();
        $this->assertSame('partial_paid', $invoice->status->value);
        $this->assertSame('stable-before-reject', $invoice->notes);
    }

    public function test_purchase_receive_allows_valid_transition_and_updates_received_qty(): void
    {
        $tenant = $this->createTenant();

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
            'name'               => 'Transition Guard Product',
            'sku'                => 'TGP-' . Str::upper(Str::random(4)),
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
            'name'               => 'Transition Guard Supplier',
            'is_active'          => true,
            'status'             => 'active',
        ]);

        $purchase = Purchase::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'supplier_id'        => $supplier->id,
            'created_by_user_id' => $tenant['user']->id,
            'reference_number'   => 'PO-TST-' . Str::upper(Str::random(6)),
            'status'             => 'ordered',
            'subtotal'           => 100,
            'tax_amount'         => 15,
            'total'              => 115,
            'currency'           => 'SAR',
        ]);

        $item = PurchaseItem::create([
            'company_id'        => $tenant['company']->id,
            'purchase_id'       => $purchase->id,
            'product_id'        => $product->id,
            'name'              => 'Transition Guard Product',
            'quantity'          => 5,
            'received_quantity' => 0,
            'unit_cost'         => 20,
            'tax_rate'          => 15,
            'tax_amount'        => 15,
            'total'             => 115,
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/purchases/{$purchase->id}/receive", [
                'items' => [
                    ['purchase_item_id' => $item->id, 'received_qty' => 2],
                ],
            ]);

        $response->assertOk();
        $purchase->refresh();
        $item->refresh();
        $this->assertSame('partial', $purchase->status->value);
        $this->assertSame('2.0000', (string) $item->received_quantity);
    }

    public function test_purchase_receive_rejects_when_purchase_is_not_receivable_and_has_no_side_effects(): void
    {
        $tenant = $this->createTenant();

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
            'name'               => 'Transition Guard Product 2',
            'sku'                => 'TGP-' . Str::upper(Str::random(4)),
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
            'name'               => 'Transition Guard Supplier 2',
            'is_active'          => true,
            'status'             => 'active',
        ]);

        $purchase = Purchase::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'supplier_id'        => $supplier->id,
            'created_by_user_id' => $tenant['user']->id,
            'reference_number'   => 'PO-TST-' . Str::upper(Str::random(6)),
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
            'name'              => 'Transition Guard Product 2',
            'quantity'          => 5,
            'received_quantity' => 5,
            'unit_cost'         => 20,
            'tax_rate'          => 15,
            'tax_amount'        => 15,
            'total'             => 115,
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/purchases/{$purchase->id}/receive", [
                'items' => [
                    ['purchase_item_id' => $item->id, 'received_qty' => 1],
                ],
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Purchase status transition received -> receive is not allowed.');

        $purchase->refresh();
        $item->refresh();
        $this->assertSame('received', $purchase->status->value);
        $this->assertSame('5.0000', (string) $item->received_quantity);
    }

    public function test_support_status_allows_valid_transition(): void
    {
        $tenant = $this->createTenant();

        $ticket = SupportTicket::create([
            'uuid'          => (string) Str::uuid(),
            'ticket_number' => 'TKT-TST-' . Str::upper(Str::random(6)),
            'company_id'    => $tenant['company']->id,
            'branch_id'     => $tenant['branch']->id,
            'created_by'    => $tenant['user']->id,
            'subject'       => 'Transition guard validation',
            'description'   => 'Validate open ticket can move to in progress.',
            'priority'      => 'medium',
            'status'        => 'open',
            'channel'       => 'portal',
            'sla_due_at'    => now()->addDay(),
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/support/tickets/{$ticket->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertOk();
        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
    }

    public function test_support_status_rejects_invalid_transition_and_keeps_db_state_stable(): void
    {
        $tenant = $this->createTenant();

        $ticket = SupportTicket::create([
            'uuid'          => (string) Str::uuid(),
            'ticket_number' => 'TKT-TST-' . Str::upper(Str::random(6)),
            'company_id'    => $tenant['company']->id,
            'branch_id'     => $tenant['branch']->id,
            'created_by'    => $tenant['user']->id,
            'subject'       => 'Transition guard validation',
            'description'   => 'Validate closed ticket cannot move back.',
            'priority'      => 'medium',
            'status'        => 'closed',
            'closed_at'     => now(),
            'channel'       => 'portal',
            'sla_due_at'    => now()->addDay(),
        ]);

        $response = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/support/tickets/{$ticket->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Ticket status transition closed -> in_progress is not allowed.');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_rejection_messages_are_consistent_for_all_entities(): void
    {
        $tenant = $this->createTenant();

        $invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'invoice_number'     => 'INV-TST-' . Str::upper(Str::random(6)),
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

        $invoiceMsg = (string) $this->actingAsUser($tenant['user'])
            ->withHeaders(['Idempotency-Key' => 'idem-' . Str::lower(Str::random(16))])
            ->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'draft'])
            ->json('message');
        $this->assertStringEndsWith('is not allowed.', $invoiceMsg);

        $ticket = SupportTicket::create([
            'uuid'          => (string) Str::uuid(),
            'ticket_number' => 'TKT-TST-' . Str::upper(Str::random(6)),
            'company_id'    => $tenant['company']->id,
            'branch_id'     => $tenant['branch']->id,
            'created_by'    => $tenant['user']->id,
            'subject'       => 'Transition guard validation',
            'description'   => 'Validate message consistency.',
            'priority'      => 'medium',
            'status'        => 'closed',
            'closed_at'     => now(),
            'channel'       => 'portal',
            'sla_due_at'    => now()->addDay(),
        ]);

        $supportMsg = (string) $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/support/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->json('message');
        $this->assertStringEndsWith('is not allowed.', $supportMsg);

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
            'name'               => 'Transition Guard Product 3',
            'sku'                => 'TGP-' . Str::upper(Str::random(4)),
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
            'name'               => 'Transition Guard Supplier 3',
            'is_active'          => true,
            'status'             => 'active',
        ]);

        $purchase = Purchase::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'supplier_id'        => $supplier->id,
            'created_by_user_id' => $tenant['user']->id,
            'reference_number'   => 'PO-TST-' . Str::upper(Str::random(6)),
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
            'name'              => 'Transition Guard Product 3',
            'quantity'          => 5,
            'received_quantity' => 5,
            'unit_cost'         => 20,
            'tax_rate'          => 15,
            'tax_amount'        => 15,
            'total'             => 115,
        ]);

        $purchaseMsg = (string) $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/purchases/{$purchase->id}/receive", [
                'items' => [
                    ['purchase_item_id' => $item->id, 'received_qty' => 1],
                ],
            ])
            ->json('message');
        $this->assertStringEndsWith('is not allowed.', $purchaseMsg);
    }

    public function test_work_order_api_status_allows_and_rejects_transitions(): void
    {
        $tenant = $this->createTenant();

        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'STG Customer',
            'is_active'  => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number'       => 'STG-' . Str::upper(Str::random(5)),
            'make'               => 'X',
            'model'              => 'Y',
            'year'               => 2024,
            'is_active'          => true,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'service', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
            ],
            $tenant['company']->id,
            $tenant['branch']->id,
            $tenant['user']->id,
        );

        $token = $this->obtainSensitivePreviewToken(
            $tenant['user'],
            SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            [(int) $order->id],
        );

        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
                'sensitive_preview_token' => $token,
            ])
            ->assertOk();

        $order->refresh();

        $ok = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status'  => 'in_progress',
                'version' => $order->version,
            ]);
        $ok->assertOk();
        $order->refresh();
        $this->assertSame('in_progress', $order->status->value);

        $bad = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status'  => 'delivered',
                'version' => $order->version,
            ]);
        $bad->assertStatus(409);
        $order->refresh();
        $this->assertSame('in_progress', $order->status->value);
    }

    public function test_bay_status_allows_and_rejects_transitions(): void
    {
        $tenant = $this->createTenant();

        $bay = Bay::create([
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'code'       => 'STG-B1',
            'name'       => 'Bay STG',
            'status'     => 'available',
        ]);

        $ok = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/bays/{$bay->id}/status", ['status' => 'maintenance']);
        $ok->assertOk();
        $bay->refresh();
        $this->assertSame('maintenance', $bay->status);

        $bad = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/bays/{$bay->id}/status", ['status' => 'reserved']);
        $bad->assertStatus(409);
        $bay->refresh();
        $this->assertSame('maintenance', $bay->status);
    }

    public function test_workshop_task_status_patch_allows_and_rejects_transitions(): void
    {
        $tenant = $this->createTenant();

        $task = Task::create([
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'title'      => 'STG Task',
            'status'     => 'pending',
        ]);

        $ok = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/workshop/tasks/{$task->id}/status", ['status' => 'in_progress']);
        $ok->assertOk();
        $task->refresh();
        $this->assertSame('in_progress', $task->status);

        $bad = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/workshop/tasks/{$task->id}/status", ['status' => 'pending']);
        $bad->assertStatus(409);
        $task->refresh();
        $this->assertSame('in_progress', $task->status);
    }

    public function test_booking_legacy_status_confirm_works_from_pending(): void
    {
        $tenant = $this->createTenant();

        $bay = Bay::create([
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'code'       => 'STG-BK',
            'name'       => 'Booking bay',
            'status'     => 'available',
        ]);

        $booking = Booking::create([
            'company_id'       => $tenant['company']->id,
            'branch_id'        => $tenant['branch']->id,
            'bay_id'           => $bay->id,
            'starts_at'        => now()->addHour(),
            'ends_at'          => now()->addHours(2),
            'duration_minutes' => 60,
            'status'           => 'pending',
        ]);

        $r = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/bookings/{$booking->id}", ['status' => 'confirmed']);
        $r->assertOk();
        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
    }

    public function test_booking_patch_without_action_or_status_returns_422(): void
    {
        $tenant = $this->createTenant();

        $bay = Bay::create([
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'code'       => 'STG-BK2',
            'name'       => 'Booking bay 2',
            'status'     => 'available',
        ]);

        $booking = Booking::create([
            'company_id'       => $tenant['company']->id,
            'branch_id'        => $tenant['branch']->id,
            'bay_id'           => $bay->id,
            'starts_at'        => now()->addHour(),
            'ends_at'          => now()->addHours(2),
            'duration_minutes' => 60,
            'status'           => 'pending',
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/bookings/{$booking->id}", []);

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'trace_id']);
        $booking->refresh();
        $this->assertSame('pending', $booking->status);
    }
}
