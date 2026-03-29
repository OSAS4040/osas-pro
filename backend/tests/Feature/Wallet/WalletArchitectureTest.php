<?php

namespace Tests\Feature\Wallet;

use App\Enums\WalletType;
use App\Enums\WalletTransactionType;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletArchitectureTest extends TestCase
{
    private array $tenant;
    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant  = $this->createTenant();
        $this->service = app(WalletService::class);
    }

    // -------------------------------------------------------------------------
    // 1. Individual: one wallet shared across multiple vehicles
    // -------------------------------------------------------------------------
    public function test_individual_top_up_creates_customer_main_wallet(): void
    {
        $customer = $this->createCustomer($this->tenant);

        $txn = $this->service->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         500.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-001',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $this->assertEquals(WalletTransactionType::TopUp, $txn->transaction_type);
        $this->assertEquals('500.0000', $txn->balance_after);

        $wallet = CustomerWallet::find($txn->customer_wallet_id);
        $this->assertEquals(WalletType::CustomerMain, $wallet->wallet_type);
    }

    // -------------------------------------------------------------------------
    // 2. Individual: vehicle_id stored on debit transaction for traceability
    // -------------------------------------------------------------------------
    public function test_individual_invoice_debit_stores_vehicle_id(): void
    {
        $customer = $this->createCustomer($this->tenant);
        $vehicle  = $this->createVehicle($this->tenant, $customer);
        $invoice  = $this->createMinimalInvoice($this->tenant, $customer, $vehicle->id);

        $this->service->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         300.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-002',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $debitTxn = $this->service->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      $vehicle->id,
            amount:         100.00,
            invoiceId:      $invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-003',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $this->assertEquals($vehicle->id, $debitTxn->vehicle_id);
        $this->assertEquals(WalletTransactionType::InvoiceDebit, $debitTxn->transaction_type);
    }

    // -------------------------------------------------------------------------
    // 3. Fleet: top-up goes to fleet_main
    // -------------------------------------------------------------------------
    public function test_fleet_top_up_creates_fleet_main_wallet(): void
    {
        $customer = $this->createCustomer($this->tenant);

        $txn = $this->service->topUpFleet(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         10000.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-004',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $wallet = CustomerWallet::find($txn->customer_wallet_id);
        $this->assertEquals(WalletType::FleetMain, $wallet->wallet_type);
        $this->assertEquals('10000.0000', $txn->balance_after);
    }

    // -------------------------------------------------------------------------
    // 4. Fleet: transfer from fleet_main to vehicle_wallet
    // -------------------------------------------------------------------------
    public function test_fleet_transfer_creates_transfer_out_and_in(): void
    {
        $customer = $this->createCustomer($this->tenant);
        $vehicle  = $this->createVehicle($this->tenant, $customer);

        $this->service->topUpFleet(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         5000.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-005a',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $result = $this->service->transferToVehicle(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      $vehicle->id,
            amount:         1000.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-005b',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $this->assertEquals(WalletTransactionType::TransferOut, $result['transfer_out']->transaction_type);
        $this->assertEquals(WalletTransactionType::TransferIn, $result['transfer_in']->transaction_type);

        // Fleet main reduced
        $fleetWallet = CustomerWallet::find($result['transfer_out']->customer_wallet_id);
        $this->assertEquals('4000.0000', $fleetWallet->balance);

        // Vehicle wallet funded
        $vehicleWallet = CustomerWallet::find($result['transfer_in']->customer_wallet_id);
        $this->assertEquals(WalletType::VehicleWallet, $vehicleWallet->wallet_type);
        $this->assertEquals('1000.0000', $vehicleWallet->balance);
    }

    // -------------------------------------------------------------------------
    // 5. Fleet: invoice debits vehicle_wallet only, never fleet_main
    // -------------------------------------------------------------------------
    public function test_vehicle_wallet_debit_does_not_touch_fleet_main(): void
    {
        $customer = $this->createCustomer($this->tenant);
        $vehicle  = $this->createVehicle($this->tenant, $customer);

        $this->service->topUpFleet(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         5000.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-006a',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );
        $this->service->transferToVehicle(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      $vehicle->id,
            amount:         500.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-006b',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $invoice = $this->createMinimalInvoice($this->tenant, $customer, $vehicle->id);

        $this->service->debitVehicleForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      $vehicle->id,
            amount:         200.00,
            invoiceId:      $invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-006c',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $fleetWallet = CustomerWallet::where('company_id', $this->tenant['company']->id)
            ->where('customer_id', $customer->id)
            ->where('wallet_type', WalletType::FleetMain->value)
            ->first();

        // Fleet main should still be 4500 (5000 - 500 transfer)
        $this->assertEquals('4500.0000', $fleetWallet->balance);
    }

    // -------------------------------------------------------------------------
    // 6. Insufficient balance throws DomainException
    // -------------------------------------------------------------------------
    public function test_insufficient_balance_throws_exception(): void
    {
        $customer = $this->createCustomer($this->tenant);

        $this->service->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         50.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-007',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient/');

        $invoice = $this->createMinimalInvoice($this->tenant, $customer);

        $this->service->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         999.00,
            invoiceId:      $invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-007b',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );
    }

    // -------------------------------------------------------------------------
    // 7. Reversal creates compensating record (append-only)
    // -------------------------------------------------------------------------
    public function test_reversal_creates_compensating_entry(): void
    {
        $customer = $this->createCustomer($this->tenant);

        $topUpTxn = $this->service->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         200.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-008a',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $reversal = $this->service->reverse(
            companyId:              $this->tenant['company']->id,
            customerId:             $customer->id,
            vehicleId:              $topUpTxn->vehicle_id,
            amount:                   (float) $topUpTxn->amount,
            invoiceId:                $topUpTxn->invoice_id,
            paymentId:                $topUpTxn->payment_id,
            userId:                   $this->tenant['user']->id,
            traceId:                  'trace-008b',
            idempotencyKey:           Str::uuid(),
            branchId:                 $this->tenant['branch']->id,
            notes:                    null,
            transactionIdToReverse:   $topUpTxn->id,
        );

        $this->assertEquals(WalletTransactionType::Reversal, $reversal->transaction_type);
        $this->assertEquals($topUpTxn->id, $reversal->original_transaction_id);

        // Wallet balance should return to 0
        $wallet = CustomerWallet::find($topUpTxn->customer_wallet_id);
        $this->assertEquals('0.0000', $wallet->balance);

        // Append-only ledger: link is reversal.original_transaction_id only (no UPDATE on original row).
        $original = WalletTransaction::find($topUpTxn->id);
        $this->assertNull($original->reversal_transaction_id);
        $this->assertEquals($reversal->id, WalletTransaction::where('original_transaction_id', $topUpTxn->id)->where('type', WalletTransactionType::Reversal)->value('id'));
    }

    // -------------------------------------------------------------------------
    // 8. Append-only: direct update throws RuntimeException
    // -------------------------------------------------------------------------
    public function test_wallet_transaction_is_immutable(): void
    {
        $customer = $this->createCustomer($this->tenant);

        $txn = $this->service->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         100.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-009',
            idempotencyKey: Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $this->expectException(\RuntimeException::class);

        $txn->notes = 'tampered';
        $txn->save();
    }

    // -------------------------------------------------------------------------
    // 9. Idempotency: duplicate key rejected with DomainException
    // -------------------------------------------------------------------------
    public function test_duplicate_idempotency_key_is_rejected(): void
    {
        $customer       = $this->createCustomer($this->tenant);
        $idempotencyKey = (string) Str::uuid();

        // First call succeeds
        $this->service->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         100.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-010',
            idempotencyKey: $idempotencyKey,
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        // Second call with same key must be rejected
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/idempotency/i');

        $this->service->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $customer->id,
            vehicleId:      null,
            amount:         100.00,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-010',
            idempotencyKey: $idempotencyKey,
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCustomer(array $tenant): \App\Models\Customer
    {
        return \App\Models\Customer::create([
            'company_id' => $tenant['company']->id,
            'name'       => 'Test Customer',
            'type'       => 'individual',
            'status'     => 'active',
        ]);
    }

    private function createVehicle(array $tenant, \App\Models\Customer $customer): \App\Models\Vehicle
    {
        return \App\Models\Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number'       => 'TST-' . rand(1000, 9999),
            'make'               => 'Toyota',
            'model'              => 'Camry',
            'year'               => 2022,
            'is_active'          => true,
        ]);
    }

    private function createMinimalInvoice(array $tenant, \App\Models\Customer $customer, ?int $vehicleId = null): \App\Models\Invoice
    {
        return \App\Models\Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $vehicleId,
            'invoice_number'     => 'INV-WARCH-' . Str::random(8),
            'invoice_hash'       => hash('sha256', Str::random(16)),
            'invoice_counter'    => random_int(1, 999999),
            'source_type'        => 'pos',
            'source_id'          => 0,
            'subtotal'           => 434.78,
            'tax_amount'         => 65.22,
            'total'              => 500.00,
            'paid_amount'        => 0,
            'due_amount'         => 500.00,
            'status'             => 'pending',
            'currency'           => 'SAR',
        ]);
    }
}
