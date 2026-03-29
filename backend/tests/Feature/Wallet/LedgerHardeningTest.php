<?php

namespace Tests\Feature\Wallet;

use App\Enums\WalletTransactionType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerHardeningTest extends TestCase
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
            'name'       => 'Ledger Customer',
            'is_active'  => true,
        ]);

        $this->invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $this->tenant['company']->id,
            'branch_id'          => $this->tenant['branch']->id,
            'created_by_user_id' => $this->tenant['user']->id,
            'customer_id'        => $this->customer->id,
            'invoice_number'     => 'INV-LEDGER-001',
            'invoice_hash'       => hash('sha256', 'ledger-test'),
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

    public function test_reversal_is_idempotent_and_references_original(): void
    {
        $this->walletService->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         300.0,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-topup',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $debit = $this->walletService->debitIndividualForInvoice(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         100.0,
            invoiceId:      $this->invoice->id,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-debit',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
            paymentMode:    'prepaid',
        );

        $revKey = (string) Str::uuid();
        $reversal = $this->walletService->reverse(
            companyId:              $this->tenant['company']->id,
            customerId:             $this->customer->id,
            vehicleId:              $debit->vehicle_id,
            amount:                 (float) $debit->amount,
            invoiceId:              $debit->invoice_id,
            paymentId:              $debit->payment_id,
            userId:                 $this->tenant['user']->id,
            traceId:                'trace-rev',
            idempotencyKey:         $revKey,
            branchId:               $this->tenant['branch']->id,
            notes:                  null,
            transactionIdToReverse: $debit->id,
        );

        $this->assertEquals(WalletTransactionType::Reversal, $reversal->type);
        $this->assertEquals($debit->id, $reversal->original_transaction_id);
        $this->assertEquals($debit->amount, $reversal->amount);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already reversed/');
        $this->walletService->reverse(
            companyId:              $this->tenant['company']->id,
            customerId:             $this->customer->id,
            vehicleId:              $debit->vehicle_id,
            amount:                 (float) $debit->amount,
            invoiceId:              $debit->invoice_id,
            paymentId:              $debit->payment_id,
            userId:                 $this->tenant['user']->id,
            traceId:                'trace-rev-2',
            idempotencyKey:         (string) Str::uuid(),
            branchId:               $this->tenant['branch']->id,
            notes:                  null,
            transactionIdToReverse: $debit->id,
        );
    }

    public function test_duplicate_wallet_transaction_idempotency_key_rejected(): void
    {
        $key = 'idem-ledger-dup-test-'.Str::random(8);

        $this->walletService->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         50.0,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-a',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $wallet = CustomerWallet::where('company_id', $this->tenant['company']->id)
            ->where('customer_id', $this->customer->id)
            ->firstOrFail();

        $first = WalletTransaction::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $this->tenant['company']->id,
            'branch_id'          => $this->tenant['branch']->id,
            'customer_wallet_id' => $wallet->id,
            'vehicle_id'         => null,
            'created_by_user_id' => $this->tenant['user']->id,
            'type'               => WalletTransactionType::TopUp,
            'amount'             => 1,
            'payment_mode'       => null,
            'balance_before'     => 0,
            'balance_after'      => 1,
            'reference_type'     => CustomerWallet::class,
            'reference_id'       => $wallet->id,
            'idempotency_key'    => $key,
            'trace_id'           => 'trace-direct-insert',
            'created_at'         => now(),
        ]);

        $this->assertNotNull($first->id);

        try {
            WalletTransaction::create([
                'uuid'               => (string) Str::uuid(),
                'company_id'         => $this->tenant['company']->id,
                'branch_id'          => $this->tenant['branch']->id,
                'customer_wallet_id' => $wallet->id,
                'vehicle_id'         => null,
                'created_by_user_id' => $this->tenant['user']->id,
                'type'               => WalletTransactionType::TopUp,
                'amount'             => 1,
                'payment_mode'       => null,
                'balance_before'     => 0,
                'balance_after'      => 1,
                'reference_type'     => CustomerWallet::class,
                'reference_id'       => $wallet->id,
                'idempotency_key'    => $key,
                'trace_id'           => 'trace-dup',
                'created_at'         => now(),
            ]);
            $this->fail('Expected duplicate idempotency key to violate unique constraint.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('unique', $e->getMessage());
        }
    }

    public function test_wallet_transactions_append_only_at_database_layer_on_postgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL append-only trigger not under test for this driver.');
        }

        $this->walletService->topUpIndividual(
            companyId:      $this->tenant['company']->id,
            customerId:     $this->customer->id,
            vehicleId:      null,
            amount:         10.0,
            invoiceId:      null,
            paymentId:      null,
            userId:         $this->tenant['user']->id,
            traceId:        'trace-immutable',
            idempotencyKey: (string) Str::uuid(),
            branchId:       $this->tenant['branch']->id,
            notes:          null,
        );

        $txn = WalletTransaction::where('company_id', $this->tenant['company']->id)->latest('id')->firstOrFail();

        try {
            DB::table('wallet_transactions')->where('id', $txn->id)->update(['note' => 'tamper']);
            $this->fail('Expected database to reject UPDATE on wallet_transactions.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('append-only', $e->getMessage());
        }
    }
}
