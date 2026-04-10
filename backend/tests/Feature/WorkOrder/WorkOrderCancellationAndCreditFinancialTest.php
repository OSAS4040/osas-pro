<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Enums\CompanyReceivableEntryType;
use App\Enums\InvoiceStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WorkOrderStatus;
use App\Models\CompanyReceivableLedger;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\InvoiceService;
use App\Services\SensitivePreviewTokenService;
use App\Services\WalletService;
use App\Services\WorkOrderService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkOrderCancellationAndCreditFinancialTest extends TestCase
{
    public function test_prepaid_cancellation_without_invoice_completes_and_second_approve_fails(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'CanX',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'CAN-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'labor', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $tokAp = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            [(int) $order->id],
        );
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
                'sensitive_preview_token' => $tokAp,
            ])
            ->assertOk();

        $order->refresh();
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'in_progress',
                'version' => $order->version,
            ])
            ->assertOk();

        $order->refresh();
        $this->assertNull($order->invoice_id);

        $tokCx = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_CANCELLATION_REQUEST,
            [(int) $order->id],
        );

        $sub = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-orders/{$order->id}/cancellation-requests", [
                'sensitive_preview_token' => $tokCx,
                'reason' => 'Operational test cancellation prepaid no invoice',
            ]);
        $sub->assertStatus(201);
        $reqId = (int) $sub->json('data.id');

        $order->refresh();
        $this->assertSame(WorkOrderStatus::CancellationRequested, $order->status);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-order-cancellation-requests/{$reqId}/approve", ['note' => 'ok'])
            ->assertOk();

        $order->refresh();
        $this->assertSame(WorkOrderStatus::Cancelled, $order->status);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-order-cancellation-requests/{$reqId}/approve", ['note' => 'again'])
            ->assertStatus(422);
    }

    public function test_prepaid_cancellation_with_wallet_paid_invoice_reverses_debit_once(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Fleet prepaid',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'PRE-W',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'labor', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $tokAp = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            [(int) $order->id],
        );
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
                'sensitive_preview_token' => $tokAp,
            ])
            ->assertOk();
        $order->refresh();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'in_progress',
                'version' => $order->version,
            ])
            ->assertOk();
        $order->refresh();

        $invoice = app(InvoiceService::class)->createInvoice([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'customer_type' => 'b2b',
            'type' => 'sale',
            'items' => [
                [
                    'name' => 'Manual line',
                    'quantity' => 1,
                    'unit_price' => 40,
                    'discount_amount' => 0,
                    'tax_rate' => 15,
                ],
            ],
        ], $company->id, $branch->id, $user->id);

        $due = (float) $invoice->due_amount;

        app(WalletService::class)->topUpFleet(
            $company->id,
            $customer->id,
            null,
            $due + 1000,
            null,
            null,
            $user->id,
            'closure-top',
            (string) Str::uuid(),
            $branch->id,
            null,
        );
        app(WalletService::class)->transferToVehicle(
            $company->id,
            $customer->id,
            $vehicle->id,
            $due + 100,
            null,
            null,
            $user->id,
            'closure-xfer',
            (string) Str::uuid(),
            $branch->id,
            null,
        );

        $payKey = (string) Str::uuid();
        $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $payKey])
            ->postJson("/api/v1/invoices/{$invoice->id}/pay", [
                'amount' => $due,
                'method' => 'wallet',
                'wallet_idempotency_key' => 'closure-wallet-pay:'.$invoice->id.':'.$payKey,
            ])
            ->assertStatus(201);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);

        $debitsBefore = WalletTransaction::query()
            ->where('company_id', $company->id)
            ->where('invoice_id', $invoice->id)
            ->where('type', WalletTransactionType::InvoiceDebit)
            ->where('payment_mode', 'prepaid')
            ->count();
        $this->assertGreaterThan(0, $debitsBefore);

        $order->update(['invoice_id' => $invoice->id]);

        $tokCx = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_CANCELLATION_REQUEST,
            [(int) $order->id],
        );

        $sub = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-orders/{$order->id}/cancellation-requests", [
                'sensitive_preview_token' => $tokCx,
                'reason' => 'Cancel after wallet pay — reversal expected',
            ]);
        $sub->assertStatus(201);
        $reqId = (int) $sub->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-order-cancellation-requests/{$reqId}/approve", ['note' => 'closure'])
            ->assertOk();

        $reversals = WalletTransaction::query()
            ->where('company_id', $company->id)
            ->where('invoice_id', $invoice->id)
            ->where('type', WalletTransactionType::Reversal)
            ->count();
        $this->assertGreaterThanOrEqual(1, $reversals);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $order->refresh();
        $this->assertSame(WorkOrderStatus::Cancelled, $order->status);
    }

    public function test_credit_work_order_approve_creates_receivable_and_cancellation_reverses(): void
    {
        $company = $this->createCompany([
            'financial_model' => 'credit',
            'financial_model_status' => 'approved_credit',
            'credit_limit' => '500000',
        ]);
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Credit co',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'CR-WO',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'labor', 'name' => 'Labor', 'quantity' => 1, 'unit_price' => 200, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $tokAp = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            [(int) $order->id],
        );
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
                'sensitive_preview_token' => $tokAp,
            ])
            ->assertOk();

        $order->refresh();
        $this->assertNotNull($order->invoice_id);
        $inv = Invoice::findOrFail((int) $order->invoice_id);

        $charges = CompanyReceivableLedger::query()
            ->where('company_id', $company->id)
            ->where('work_order_id', $order->id)
            ->where('entry_type', CompanyReceivableEntryType::Charge)
            ->count();
        $this->assertSame(1, $charges);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'in_progress',
                'version' => $order->version,
            ])
            ->assertOk();
        $order->refresh();

        $tokCx = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_CANCELLATION_REQUEST,
            [(int) $order->id],
        );

        $sub = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-orders/{$order->id}/cancellation-requests", [
                'sensitive_preview_token' => $tokCx,
                'reason' => 'Credit tenant cancel E2E',
            ]);
        $sub->assertStatus(201);
        $reqId = (int) $sub->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-order-cancellation-requests/{$reqId}/approve", ['note' => 'credit rev'])
            ->assertOk();

        $reversals = CompanyReceivableLedger::query()
            ->where('company_id', $company->id)
            ->where('work_order_id', $order->id)
            ->where('entry_type', CompanyReceivableEntryType::Reversal)
            ->count();
        $this->assertSame(1, $reversals);

        $inv->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $inv->status);
        $order->refresh();
        $this->assertSame(WorkOrderStatus::Cancelled, $order->status);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-order-cancellation-requests/{$reqId}/approve", ['note' => 'dup'])
            ->assertStatus(422);
    }

    public function test_credit_preview_warns_when_estimate_exceeds_limit(): void
    {
        $company = $this->createCompany([
            'financial_model' => 'credit',
            'financial_model_status' => 'approved_credit',
            'credit_limit' => '1',
        ]);
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Lim',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'LIM-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'labor', 'name' => 'Big', 'quantity' => 1, 'unit_price' => 99999, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $order->update(['estimated_total' => 120000]);

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sensitive-operations/preview', [
                'operation' => SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
                'work_order_ids' => [(int) $order->id],
            ]);

        $res->assertOk();
        $warnings = $res->json('data.warnings');
        $this->assertIsArray($warnings);
        $joined = implode(' ', $warnings);
        $this->assertStringContainsString('تجاوز', $joined);
    }

    public function test_cancellation_submit_is_idempotent_against_duplicate_pending(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Dup',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'DUP-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'labor', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $tokAp = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            [(int) $order->id],
        );
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
                'sensitive_preview_token' => $tokAp,
            ])
            ->assertOk();
        $order->refresh();

        $tok1 = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_CANCELLATION_REQUEST,
            [(int) $order->id],
        );
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-orders/{$order->id}/cancellation-requests", [
                'sensitive_preview_token' => $tok1,
                'reason' => 'First pending request',
            ])
            ->assertStatus(201);

        $tok2 = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_CANCELLATION_REQUEST,
            [(int) $order->id],
        );
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/work-orders/{$order->id}/cancellation-requests", [
                'sensitive_preview_token' => $tok2,
                'reason' => 'Duplicate must fail',
            ])
            ->assertStatus(422);
    }
}
