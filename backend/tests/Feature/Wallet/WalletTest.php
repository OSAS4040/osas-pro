<?php

namespace Tests\Feature\Wallet;

use App\Enums\WalletType;
use App\Enums\WalletTransactionType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Services\WalletService;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletTest extends TestCase
{
    private WalletService $walletService;
    private array $tenant;
    private Customer $customer;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletService = app(WalletService::class);
        $this->tenant        = $this->createTenant();

        $this->customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $this->tenant['company']->id,
            'branch_id'  => $this->tenant['branch']->id,
            'type'       => 'individual',
            'name'       => 'Test Customer',
            'is_active'  => true,
        ]);

        $this->invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $this->tenant['company']->id,
            'branch_id'          => $this->tenant['branch']->id,
            'created_by_user_id' => $this->tenant['user']->id,
            'customer_id'        => $this->customer->id,
            'invoice_number'     => 'INV-WALLET-TEST',
            'invoice_hash'       => hash('sha256', 'wallet-test'),
            'invoice_counter'    => 1,
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function topUp(float $amount = 500.0): \App\Models\WalletTransaction
    {
        return $this->walletService->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         $amount,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-' . Str::random(6),
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );
    }

    private function wallet(): CustomerWallet
    {
        return CustomerWallet::where('company_id', $this->tenant['company']->id)
            ->where('customer_id', $this->customer->id)
            ->where('wallet_type', WalletType::CustomerMain->value)
            ->firstOrFail();
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_top_up_creates_wallet_and_transaction(): void
    {
        $txn = $this->topUp(500.0);

        $this->assertEquals(WalletTransactionType::TopUp, $txn->transaction_type);
        $this->assertEquals('500.0000', $txn->amount);
        $this->assertEquals('500.0000', $this->wallet()->balance);
    }

    public function test_top_up_accumulates_balance(): void
    {
        $this->topUp(300.0);
        $this->topUp(200.0);

        $this->assertEquals('500.0000', $this->wallet()->balance);
    }

    public function test_invoice_debit_reduces_balance(): void
    {
        $this->topUp(1000.0);

        $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         400.0,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-debit',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $this->assertEquals('600.0000', $this->wallet()->balance);
    }

    public function test_insufficient_balance_throws_exception(): void
    {
        $this->topUp(100.0);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient/');

        $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         999.0,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-fail',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );
    }

    public function test_reversal_compensates_original_debit(): void
    {
        $this->topUp(1000.0);

        $debit = $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         400.0,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-debit',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $reversal = $this->walletService->reverse(
            companyId:              $this->tenant['company']->id,
            customerId:             $this->customer->id,
            vehicleId:              $debit->vehicle_id,
            amount:                 (float) $debit->amount,
            invoiceId:              $debit->invoice_id,
            paymentId:              $debit->payment_id,
            userId:                 $this->tenant['user']->id,
            traceId:                'trace-reverse',
            idempotencyKey:         (string) Str::uuid(),
            branchId:               $this->tenant['branch']->id,
            notes:                  null,
            transactionIdToReverse: $debit->id,
        );

        $this->assertEquals(WalletTransactionType::Reversal, $reversal->transaction_type);
        $this->assertEquals($debit->id, $reversal->original_transaction_id);
        $this->assertEquals('1000.0000', $this->wallet()->balance);
    }

    public function test_double_reversal_is_rejected(): void
    {
        $this->topUp(500.0);

        $debit = $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         200.0,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-d',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $this->walletService->reverse(
            companyId:              $this->tenant['company']->id,
            customerId:             $this->customer->id,
            vehicleId:              $debit->vehicle_id,
            amount:                 (float) $debit->amount,
            invoiceId:              $debit->invoice_id,
            paymentId:              $debit->payment_id,
            userId:                 $this->tenant['user']->id,
            traceId:                'rev-1',
            idempotencyKey:         (string) Str::uuid(),
            branchId:               $this->tenant['branch']->id,
            notes:                  null,
            transactionIdToReverse: $debit->id,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already reversed/');

        // Second reversal of same transaction — must fail
        $this->walletService->reverse(
            companyId:              $this->tenant['company']->id,
            customerId:             $this->customer->id,
            vehicleId:              $debit->vehicle_id,
            amount:                 (float) $debit->amount,
            invoiceId:              $debit->invoice_id,
            paymentId:              $debit->payment_id,
            userId:                 $this->tenant['user']->id,
            traceId:                'rev-2',
            idempotencyKey:         (string) Str::uuid(),
            branchId:               $this->tenant['branch']->id,
            notes:                  null,
            transactionIdToReverse: $debit->id,
        );
    }

    public function test_wallet_transactions_are_append_only(): void
    {
        $txn = $this->topUp(100.0);

        $this->expectException(\RuntimeException::class);

        $txn->notes = 'tampered';
        $txn->save();
    }

    public function test_wallet_transactions_cannot_be_deleted(): void
    {
        $txn = $this->topUp(100.0);

        $this->expectException(\RuntimeException::class);

        $txn->delete();
    }

    public function test_duplicate_idempotency_key_is_rejected(): void
    {
        $key = (string) Str::uuid();

        $this->walletService->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         100.0,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-idem-1',
            idempotencyKey: $key,
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already processed/i');

        $this->walletService->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         100.0,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-idem-2',
            idempotencyKey: $key,
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );
    }

    public function test_suspended_wallet_cannot_be_debited(): void
    {
        $this->topUp(500.0);

        CustomerWallet::where('company_id', $this->tenant['company']->id)
            ->where('customer_id', $this->customer->id)
            ->update(['status' => 'suspended']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/suspended/');

        $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         100.0,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-suspended',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );
    }
}
