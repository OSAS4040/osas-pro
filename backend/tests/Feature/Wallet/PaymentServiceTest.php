<?php

namespace Tests\Feature\Wallet;

use App\Enums\InvoiceStatus;
use App\Enums\WalletType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private PaymentService $paymentService;
    private WalletService  $walletService;
    private array    $tenant;
    private Customer $customer;
    private Invoice  $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);
        $this->walletService  = app(WalletService::class);
        $this->tenant         = $this->createTenant();

        $this->customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $this->tenant['company']->id,
            'branch_id'  => $this->tenant['branch']->id,
            'type'       => 'individual',
            'name'       => 'Payment Customer',
            'is_active'  => true,
        ]);

        $this->invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $this->tenant['company']->id,
            'branch_id'          => $this->tenant['branch']->id,
            'created_by_user_id' => $this->tenant['user']->id,
            'customer_id'        => $this->customer->id,
            'invoice_number'     => 'INV-TEST-001',
            'invoice_hash'       => hash('sha256', 'test'),
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

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function topUp(float $amount = 1000.0): void
    {
        $this->walletService->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         $amount,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-setup-' . Str::random(4),
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );
    }

    private function walletBalance(): float
    {
        $wallet = CustomerWallet::where('company_id', $this->tenant['company']->id)
            ->where('customer_id', $this->customer->id)
            ->where('wallet_type', WalletType::CustomerMain->value)
            ->first();

        return $wallet ? (float) $wallet->balance : 0.0;
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_cash_payment_marks_invoice_paid(): void
    {
        $payment = $this->paymentService->createPayment(
            invoice:  $this->invoice,
            amount:   500.00,
            method:   'cash',
            userId:   $this->tenant['user']->id,
            traceId:  'trace-pay-01',
        );

        $this->assertEquals('completed', $payment->status);
        $this->invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $this->invoice->status);
        $this->assertEquals('0.0000', $this->invoice->due_amount);
    }

    public function test_wallet_payment_debits_wallet(): void
    {
        // Wallet must be topped up and debited BEFORE calling createPayment
        // (PaymentService no longer owns wallet logic — caller's responsibility)
        $this->topUp(1000.0);

        $idempotencyKey = (string) Str::uuid();

        $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         500.00,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-wallet-pay',
            idempotencyKey: $idempotencyKey,
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $this->paymentService->createPayment(
            invoice:  $this->invoice,
            amount:   500.00,
            method:   'wallet',
            userId:   $this->tenant['user']->id,
            traceId:  'trace-wallet-pay',
        );

        $this->assertEquals(500.0, $this->walletBalance());
    }

    public function test_partial_payment_sets_partial_status(): void
    {
        $this->paymentService->createPayment(
            invoice: $this->invoice,
            amount:  200.00,
            method:  'cash',
            userId:  $this->tenant['user']->id,
            traceId: 'trace-partial',
        );

        $this->invoice->refresh();
        $this->assertEquals(InvoiceStatus::PartialPaid, $this->invoice->status);
        $this->assertEquals('300.0000', $this->invoice->due_amount);
    }

    public function test_overpayment_throws_exception(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/exceeds invoice due amount/');

        $this->paymentService->createPayment(
            invoice: $this->invoice,
            amount:  600.00,
            method:  'cash',
            userId:  $this->tenant['user']->id,
            traceId: 'trace-over',
        );
    }

    public function test_refund_reverses_payment_and_resets_invoice(): void
    {
        $payment = $this->paymentService->createPayment(
            invoice: $this->invoice,
            amount:  500.00,
            method:  'cash',
            userId:  $this->tenant['user']->id,
            traceId: 'trace-pay',
        );

        $refund = $this->paymentService->refund(
            paymentId:      $payment->id,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-refund',
            idempotencyKey: (string) Str::uuid(),
        );

        $this->assertEquals('refunded', $refund->status);
        $this->assertEquals($payment->id, $refund->original_payment_id);

        $this->invoice->refresh();
        $this->assertEquals(InvoiceStatus::Pending, $this->invoice->status);
    }

    public function test_refund_wallet_payment_credits_wallet_back(): void
    {
        $this->topUp(1000.0);

        // Debit wallet for invoice
        $walletIdemKey = (string) Str::uuid();
        $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         500.00,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-wallet-debit',
            idempotencyKey: $walletIdemKey,
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $payment = $this->paymentService->createPayment(
            invoice: $this->invoice,
            amount:  500.00,
            method:  'wallet',
            userId:  $this->tenant['user']->id,
            traceId: 'trace-pay',
        );

        // Refund — credits wallet back via topUpIndividual
        $this->paymentService->refund(
            paymentId:      $payment->id,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-refund',
            idempotencyKey: (string) Str::uuid(),
        );

        $this->assertEquals(1000.0, $this->walletBalance());
    }

    public function test_double_refund_throws_exception(): void
    {
        $payment = $this->paymentService->createPayment(
            invoice: $this->invoice,
            amount:  500.00,
            method:  'cash',
            userId:  $this->tenant['user']->id,
            traceId: 'trace-pay',
        );

        $this->paymentService->refund(
            paymentId:      $payment->id,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-refund-1',
            idempotencyKey: (string) Str::uuid(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already been fully refunded/');

        $this->paymentService->refund(
            paymentId:      $payment->id,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-refund-2',
            idempotencyKey: (string) Str::uuid(),
        );
    }
}
