<?php

namespace App\Services;

use App\Enums\WalletTransactionType;
use App\Enums\WalletType;
use App\Intelligence\Events\WalletCredited;
use App\Intelligence\Events\WalletDebited;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Models\WorkOrder;
use App\Support\Accounting\FinancialGlMapping;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Enums\WorkOrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Every mutating entrypoint carries the same explicit financial context (never implicit):
     * companyId, customerId, vehicleId, amount, invoiceId, paymentId, userId, traceId, idempotencyKey, branchId, notes
     *
     * Operation-specific parameters (paymentMode, workOrderId, walletType, transactionIdToReverse) are appended after.
     */

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly IntelligentEventEmitter $intelligentEvents,
    ) {}

    /**
     * Top up an individual customer's main wallet.
     *
     * @param  ?int  $vehicleId   Trace/context vehicle (nullable when not vehicle-scoped).
     * @param  ?int  $invoiceId   Optional billing link (nullable).
     * @param  ?int  $paymentId   Optional payment link (nullable).
     */
    public function topUpIndividual(
        int $companyId,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId, $paymentId, $userId, $traceId,
            $idempotencyKey, $branchId, $notes
        ) {
            $wallet = $this->resolveOrCreateWallet(
                $companyId, $customerId, null, WalletType::CustomerMain, $branchId
            );

            return $this->creditWallet(
                $wallet, $amount, $userId, WalletTransactionType::TopUp,
                $vehicleId, null, null, $traceId, $idempotencyKey, null, $notes,
                $invoiceId, $paymentId
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        $this->emitWalletCredited($txn, $companyId, $branchId, $userId, 'WalletService::topUpIndividual');

        return $txn;
    }

    /**
     * Top up a fleet's main wallet.
     *
     * @param  ?int  $vehicleId  Optional fleet vehicle context (nullable for pure fleet-main funding).
     */
    public function topUpFleet(
        int $companyId,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId, $paymentId, $userId, $traceId,
            $idempotencyKey, $branchId, $notes
        ) {
            $wallet = $this->resolveOrCreateWallet(
                $companyId, $customerId, null, WalletType::FleetMain, $branchId
            );

            return $this->creditWallet(
                $wallet, $amount, $userId, WalletTransactionType::TopUp,
                $vehicleId, null, null, $traceId, $idempotencyKey, null, $notes,
                $invoiceId, $paymentId
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        $this->postLedger($companyId, $branchId, $userId, $traceId, [
            'type'          => FinancialGlMapping::WALLET_TOP_UP,
            'description'   => "Fleet wallet top-up — customer #{$customerId}",
            'source_type'   => WalletTransaction::class,
            'source_id'     => $txn->id,
            'lines'         => FinancialGlMapping::walletLines(FinancialGlMapping::WALLET_TOP_UP, $amount, [
                'debit'  => 'Cash received for fleet wallet',
                'credit' => 'Fleet main wallet deposit',
            ]),
        ]);

        $this->emitWalletCredited($txn, $companyId, $branchId, $userId, 'WalletService::topUpFleet');

        return $txn;
    }

    /**
     * Transfer from fleet_main wallet to a vehicle_wallet.
     * Creates TRANSFER_OUT on source and TRANSFER_IN on destination atomically.
     */
    public function transferToVehicle(
        int $companyId,
        int $customerId,
        int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
    ): array {
        $this->checkIdempotency($companyId, $idempotencyKey);

        $result = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId, $paymentId, $userId,
            $traceId, $idempotencyKey, $branchId, $notes
        ) {
            $fleetWallet = CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('wallet_type', WalletType::FleetMain->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$fleetWallet->isActive()) {
                throw new \DomainException("Fleet wallet is {$fleetWallet->status}.");
            }
            if ((float) $fleetWallet->balance < $amount) {
                throw new \DomainException(
                    "Insufficient fleet balance. Available: {$fleetWallet->balance}, Required: {$amount}."
                );
            }

            $vehicleWallet = $this->resolveOrCreateWallet(
                $companyId, $customerId, $vehicleId, WalletType::VehicleWallet, $branchId
            );

            // TRANSFER_OUT from fleet
            $outTxn = $this->debitWallet(
                $fleetWallet, $amount, $userId, WalletTransactionType::TransferOut,
                $vehicleId, null, null, $traceId, $idempotencyKey . '_out', 'direct', $notes,
                $invoiceId, $paymentId, false
            );

            // TRANSFER_IN to vehicle (new idempotency key suffix)
            $inTxn = $this->creditWallet(
                $vehicleWallet, $amount, $userId, WalletTransactionType::TransferIn,
                $vehicleId, null, null, $traceId, $idempotencyKey . '_in', 'direct', $notes,
                $invoiceId, $paymentId
            );

            return ['transfer_out' => $outTxn, 'transfer_in' => $inTxn];
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $result['transfer_out']->id);

        $this->postLedger($companyId, $branchId, $userId, $traceId, [
            'type'          => FinancialGlMapping::WALLET_TRANSFER,
            'description'   => "Fleet→Vehicle wallet transfer — vehicle #{$vehicleId}",
            'source_type'   => WalletTransaction::class,
            'source_id'     => $result['transfer_out']->id,
            'lines'         => FinancialGlMapping::walletLines(FinancialGlMapping::WALLET_TRANSFER, $amount, [
                'debit'  => 'Fleet main wallet out',
                'credit' => 'Vehicle wallet in',
            ]),
        ]);

        return $result;
    }

    /**
     * Debit an individual customer's wallet for an invoice (customer_main).
     * Pass vehicleId only when recording traceability to a real vehicles row; otherwise null.
     */
    public function debitIndividualForInvoice(
        int $companyId,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
        string $paymentMode,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId,
            $userId, $traceId, $idempotencyKey, $paymentMode, $paymentId, $branchId, $notes
        ) {
            $wallet = CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('wallet_type', WalletType::CustomerMain->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$wallet->isActive()) {
                throw new \DomainException("Wallet is {$wallet->status}.");
            }
            if ((float) $wallet->balance < $amount) {
                throw new \DomainException(
                    "Insufficient balance. Available: {$wallet->balance}, Required: {$amount}."
                );
            }

            return $this->debitWallet(
                $wallet, $amount, $userId, WalletTransactionType::InvoiceDebit,
                $vehicleId, Invoice::class, $invoiceId, $traceId,
                $idempotencyKey, $paymentMode, $notes,
                $invoiceId, $paymentId, false
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        $this->emitWalletDebited($txn, $companyId, $branchId, $userId, $invoiceId, 'WalletService::debitIndividualForInvoice');

        return $txn;
    }

    /**
     * Debit a vehicle wallet for a fleet invoice.
     * Never touches the fleet_main wallet directly.
     */
    public function debitVehicleForInvoice(
        int $companyId,
        int $customerId,
        int $vehicleId,
        float $amount,
        int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
        string $paymentMode,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId,
            $userId, $traceId, $idempotencyKey, $paymentMode, $paymentId, $branchId, $notes
        ) {
            $wallet = CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('vehicle_id', $vehicleId)
                ->where('wallet_type', WalletType::VehicleWallet->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$wallet->isActive()) {
                throw new \DomainException("Vehicle wallet is {$wallet->status}.");
            }
            if ((float) $wallet->balance < $amount) {
                throw new \DomainException(
                    "Insufficient vehicle wallet balance. Available: {$wallet->balance}, Required: {$amount}."
                );
            }

            return $this->debitWallet(
                $wallet, $amount, $userId, WalletTransactionType::InvoiceDebit,
                $vehicleId, Invoice::class, $invoiceId, $traceId,
                $idempotencyKey, $paymentMode, $notes,
                $invoiceId, $paymentId, false
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        $this->postLedger($companyId, $branchId, $userId, $traceId, [
            'type'          => FinancialGlMapping::WALLET_DEBIT,
            'description'   => "Vehicle wallet debit — invoice #{$invoiceId} vehicle #{$vehicleId}",
            'source_type'   => WalletTransaction::class,
            'source_id'     => $txn->id,
            'lines'         => FinancialGlMapping::walletLines(FinancialGlMapping::WALLET_DEBIT, $amount, [
                'debit'  => 'Vehicle wallet charged',
                'credit' => 'Service revenue',
            ]),
        ]);

        $this->emitWalletDebited($txn, $companyId, $branchId, $userId, $invoiceId, 'WalletService::debitVehicleForInvoice');

        return $txn;
    }

    /**
     * Debit a vehicle wallet for a service using credit mode.
     * Requires an approved work order — allows negative balance (credit line).
     */
    public function debitVehicleForServiceCredit(
        int $companyId,
        int $customerId,
        int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
        int $workOrderId,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId, $paymentId, $workOrderId,
            $userId, $traceId, $idempotencyKey, $branchId, $notes
        ) {
            $workOrder = WorkOrder::where('company_id', $companyId)
                ->where('id', $workOrderId)
                ->where('vehicle_id', $vehicleId)
                ->whereIn('status', [
                    WorkOrderStatus::Pending->value,
                    WorkOrderStatus::InProgress->value,
                ])
                ->where('approval_status', 'approved')
                ->where('credit_authorized', true)
                ->lockForUpdate()
                ->first();

            if (! $workOrder) {
                throw new \DomainException(
                    'No approved credit work order found for this vehicle. Service cannot proceed.'
                );
            }

            $wallet = $this->resolveOrCreateWallet(
                $companyId, $customerId, $vehicleId, WalletType::VehicleWallet, $branchId
            );

            if (! $wallet->isActive()) {
                throw new \DomainException("Vehicle wallet is {$wallet->status}.");
            }

            return $this->debitWallet(
                $wallet, $amount, $userId, WalletTransactionType::InvoiceDebit,
                $vehicleId, WorkOrder::class, $workOrderId, $traceId,
                $idempotencyKey, 'credit', $notes,
                $invoiceId, $paymentId, true
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        $this->postLedger($companyId, $branchId, $userId, $traceId, [
            'type'          => FinancialGlMapping::WALLET_CREDIT_DEBIT,
            'description'   => "Vehicle credit service — work order #{$workOrderId} vehicle #{$vehicleId}",
            'source_type'   => WalletTransaction::class,
            'source_id'     => $txn->id,
            'lines'         => FinancialGlMapping::walletLines(FinancialGlMapping::WALLET_CREDIT_DEBIT, $amount, [
                'debit'  => 'Vehicle wallet credit debit',
                'credit' => 'Service revenue (credit)',
            ]),
        ]);

        return $txn;
    }

    /**
     * Credit customer_main wallet for a refund (prepaid balance returned to wallet).
     * Immutable ledger row; links to invoice (and optional payment) for ZATCA / audit.
     */
    public function refundIndividual(
        int $companyId,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);
        $invoice = $this->assertInvoiceInCompany($companyId, $invoiceId);
        $this->assertInvoiceBelongsToCustomer($invoice, $customerId);
        $this->assertPaymentBelongsToInvoice($companyId, $invoiceId, $paymentId);
        $this->assertVehicleContextMatchesInvoice($invoice, $vehicleId);

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId, $paymentId,
            $userId, $traceId, $idempotencyKey, $notes, $invoice
        ) {
            $wallet = CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('wallet_type', WalletType::CustomerMain->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $wallet->isActive()) {
                throw new \DomainException("Wallet is {$wallet->status}.");
            }

            $ledgerVehicleId = $vehicleId ?? $invoice->vehicle_id;

            return $this->creditWallet(
                $wallet,
                $amount,
                $userId,
                WalletTransactionType::Refund,
                $ledgerVehicleId,
                Invoice::class,
                $invoiceId,
                $traceId,
                $idempotencyKey,
                'prepaid',
                $notes,
                $invoiceId,
                $paymentId
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        return $txn;
    }

    /**
     * Credit vehicle_wallet for a fleet refund.
     */
    public function refundVehicle(
        int $companyId,
        int $customerId,
        int $vehicleId,
        float $amount,
        int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);
        $invoice = $this->assertInvoiceInCompany($companyId, $invoiceId);
        $this->assertInvoiceBelongsToCustomer($invoice, $customerId);
        $this->assertPaymentBelongsToInvoice($companyId, $invoiceId, $paymentId);

        if ($invoice->vehicle_id !== null && (int) $invoice->vehicle_id !== $vehicleId) {
            throw new \DomainException('Invoice vehicle does not match target vehicle wallet.');
        }

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId, $paymentId,
            $userId, $traceId, $idempotencyKey, $notes
        ) {
            $wallet = CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('vehicle_id', $vehicleId)
                ->where('wallet_type', WalletType::VehicleWallet->value)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $wallet->isActive()) {
                throw new \DomainException("Vehicle wallet is {$wallet->status}.");
            }

            return $this->creditWallet(
                $wallet,
                $amount,
                $userId,
                WalletTransactionType::Refund,
                $vehicleId,
                Invoice::class,
                $invoiceId,
                $traceId,
                $idempotencyKey,
                'prepaid',
                $notes,
                $invoiceId,
                $paymentId
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        return $txn;
    }

    /**
     * Manual credit adjustment (append-only). Requires non-empty reason (stored in note).
     */
    public function adjustmentAdd(
        int $companyId,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        string $notes,
        WalletType $walletType,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);
        $notes = trim($notes);
        if ($notes === '') {
            throw new \DomainException('Adjustment reason is required.');
        }

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $walletType, $amount,
            $invoiceId, $paymentId, $userId, $traceId, $idempotencyKey, $notes, $branchId
        ) {
            $wallet = $this->resolveOrCreateWallet($companyId, $customerId, $vehicleId, $walletType, $branchId);

            if (! $wallet->isActive()) {
                throw new \DomainException("Wallet is {$wallet->status}.");
            }

            return $this->creditWallet(
                $wallet,
                $amount,
                $userId,
                WalletTransactionType::AdjustmentAdd,
                $vehicleId,
                null,
                null,
                $traceId,
                $idempotencyKey,
                null,
                $notes,
                $invoiceId,
                $paymentId
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        return $txn;
    }

    /**
     * Manual debit adjustment (append-only). Requires non-empty reason.
     */
    public function adjustmentSub(
        int $companyId,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        string $notes,
        WalletType $walletType,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);
        $notes = trim($notes);
        if ($notes === '') {
            throw new \DomainException('Adjustment reason is required.');
        }

        $txn = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $walletType, $amount,
            $invoiceId, $paymentId, $userId, $traceId, $idempotencyKey, $notes, $branchId
        ) {
            $wallet = $this->resolveOrCreateWallet($companyId, $customerId, $vehicleId, $walletType, $branchId);

            if (! $wallet->isActive()) {
                throw new \DomainException("Wallet is {$wallet->status}.");
            }
            if ((float) $wallet->balance < $amount) {
                throw new \DomainException(
                    'Insufficient balance for adjustment. Available: '.$wallet->balance.", Required: {$amount}."
                );
            }

            return $this->debitWallet(
                $wallet,
                $amount,
                $userId,
                WalletTransactionType::AdjustmentSub,
                $vehicleId,
                null,
                null,
                $traceId,
                $idempotencyKey,
                null,
                $notes,
                $invoiceId,
                $paymentId,
                false
            );
        });

        $this->persistIdempotencyResult($companyId, $idempotencyKey, $txn->id);

        return $txn;
    }

    /**
     * Reverse a wallet transaction (append-only correction).
     * Caller must supply the full ledger context; it is validated against the original row.
     */
    public function reverse(
        int $companyId,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
        int $userId,
        string $traceId,
        string $idempotencyKey,
        ?int $branchId,
        ?string $notes,
        int $transactionIdToReverse,
    ): WalletTransaction {
        $this->checkIdempotency($companyId, $idempotencyKey);

        $reversal = DB::transaction(function () use (
            $companyId, $customerId, $vehicleId, $amount, $invoiceId, $paymentId,
            $userId, $traceId, $idempotencyKey, $branchId, $notes, $transactionIdToReverse
        ) {
            $original = WalletTransaction::where('id', $transactionIdToReverse)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            if (WalletTransaction::where('company_id', $companyId)
                ->where('original_transaction_id', $original->id)
                ->where('type', WalletTransactionType::Reversal)
                ->exists()) {
                throw new \DomainException('Transaction already reversed.');
            }

            $wallet = CustomerWallet::where('id', $original->customer_wallet_id)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertReversalContextMatchesOriginal($original, $wallet, $customerId, $vehicleId, $amount, $invoiceId, $paymentId);

            $isDebit = $original->type->isDebit();

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter  = $isDebit
                ? $balanceBefore + (float) $original->amount
                : $balanceBefore - (float) $original->amount;

            if (!$isDebit && $balanceAfter < 0) {
                throw new \DomainException('Cannot reverse credit: insufficient balance.');
            }

            if ($isDebit) {
                $wallet->increment('balance', $original->amount);
            } else {
                $wallet->decrement('balance', $original->amount);
            }
            $wallet->increment('version');
            $wallet->refresh();

            $this->validateLedgerBalances(
                $balanceBefore,
                (float) $original->amount,
                (float) $wallet->balance,
                $isDebit
            );

            $noteText = "Reversal of transaction #{$original->id}";
            if ($notes !== null && trim($notes) !== '') {
                $noteText .= ' — '.$notes;
            }

            try {
                $rev = WalletTransaction::create([
                    'uuid'                    => (string) Str::uuid(),
                    'company_id'              => $original->company_id,
                    'branch_id'               => $branchId ?? $original->branch_id,
                    'customer_wallet_id'      => $original->customer_wallet_id,
                    'vehicle_id'              => $original->vehicle_id,
                    'created_by_user_id'      => $userId,
                    'type'                    => WalletTransactionType::Reversal,
                    'amount'                  => $original->amount,
                    'payment_mode'            => $original->payment_mode,
                    'balance_before'          => $balanceBefore,
                    'balance_after'           => (float) $wallet->balance,
                    'original_transaction_id' => $original->id,
                    'reference_type'          => $original->reference_type ?? CustomerWallet::class,
                    'reference_id'            => $original->reference_id ?? $original->customer_wallet_id,
                    'invoice_id'              => $original->invoice_id,
                    'payment_id'              => $original->payment_id,
                    'idempotency_key'         => $idempotencyKey,
                    'trace_id'                => $traceId,
                    'note'                    => $noteText,
                    'created_at'              => now(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new \DomainException('Transaction already reversed.', 0, $e);
            }

            Log::info('wallet.reversal', [
                'financial_operation' => true,
                'original_id'         => $original->id,
                'reversal_id'         => $rev->id,
                'trace_id'            => $traceId,
                'company_id'          => $original->company_id,
            ]);

            return $rev;
        });

        $this->persistIdempotencyResult($reversal->company_id, $idempotencyKey, $reversal->id);

        return $reversal;
    }

    /**
     * Get wallet balance summary for a customer.
     */
    public function getBalanceSummary(int $companyId, int $customerId): array
    {
        $wallets = CustomerWallet::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->get();

        return $wallets->map(fn($w) => [
            'id'          => $w->id,
            'wallet_type' => $w->wallet_type->value,
            'vehicle_id'  => $w->vehicle_id,
            'balance'     => (float) $w->balance,
            'status'      => $w->status,
            'currency'    => $w->currency,
        ])->toArray();
    }

    /**
     * Return total balance across all wallets for a customer (used by tests/reports).
     * For fleet customers this sums fleet_main + all vehicle_wallets.
     */
    public function getTotalBalance(int $companyId, int $customerId): float
    {
        return (float) CustomerWallet::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->sum('balance');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function assertInvoiceInCompany(int $companyId, int $invoiceId): Invoice
    {
        $invoice = Invoice::where('company_id', $companyId)->where('id', $invoiceId)->first();
        if (! $invoice) {
            throw new \DomainException("Invoice #{$invoiceId} not found for tenant.");
        }

        return $invoice;
    }

    private function assertInvoiceBelongsToCustomer(Invoice $invoice, int $customerId): void
    {
        if ($invoice->customer_id !== null && (int) $invoice->customer_id !== $customerId) {
            throw new \DomainException('Invoice does not belong to this customer.');
        }
    }

    private function assertPaymentBelongsToInvoice(int $companyId, int $invoiceId, ?int $paymentId): void
    {
        if ($paymentId === null) {
            return;
        }
        $exists = Payment::where('company_id', $companyId)
            ->where('id', $paymentId)
            ->where('invoice_id', $invoiceId)
            ->exists();
        if (! $exists) {
            throw new \DomainException('Payment does not belong to this invoice or company.');
        }
    }

    private function assertVehicleContextMatchesInvoice(Invoice $invoice, ?int $vehicleId): void
    {
        $invV = $invoice->vehicle_id !== null ? (int) $invoice->vehicle_id : null;
        $ctxV = $vehicleId !== null ? (int) $vehicleId : null;
        if ($invV !== $ctxV) {
            throw new \DomainException('vehicleId does not match invoice vehicle context.');
        }
    }

    private function assertReversalContextMatchesOriginal(
        WalletTransaction $original,
        CustomerWallet $wallet,
        int $customerId,
        ?int $vehicleId,
        float $amount,
        ?int $invoiceId,
        ?int $paymentId,
    ): void {
        if ((int) $wallet->customer_id !== $customerId) {
            throw new \DomainException('customerId does not match the wallet for this transaction.');
        }
        if (abs((float) $original->amount - $amount) > 0.0001) {
            throw new \DomainException('amount does not match the original ledger entry.');
        }
        $oInv = $original->invoice_id !== null ? (int) $original->invoice_id : null;
        $cInv = $invoiceId !== null ? (int) $invoiceId : null;
        if ($oInv !== $cInv) {
            throw new \DomainException('invoiceId does not match the original ledger entry.');
        }
        $oPay = $original->payment_id !== null ? (int) $original->payment_id : null;
        $cPay = $paymentId !== null ? (int) $paymentId : null;
        if ($oPay !== $cPay) {
            throw new \DomainException('paymentId does not match the original ledger entry.');
        }
        $oVeh = $original->vehicle_id !== null ? (int) $original->vehicle_id : null;
        $cVeh = $vehicleId !== null ? (int) $vehicleId : null;
        if ($oVeh !== $cVeh) {
            throw new \DomainException('vehicleId does not match the original ledger entry.');
        }
    }

    private function resolveOrCreateWallet(
        int        $companyId,
        int        $customerId,
        ?int       $vehicleId,
        WalletType $type,
        ?int       $branchId,
    ): CustomerWallet {
        // Use a SELECT ... FOR UPDATE to prevent race conditions when two concurrent
        // requests try to create the same wallet simultaneously.
        // If the row already exists, we lock and return it.
        // If not, we INSERT — the UNIQUE constraint on (company_id, customer_id, vehicle_id, wallet_type)
        // is the final safety net against any concurrent INSERT slipping through.
        $existing = CustomerWallet::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('vehicle_id', $vehicleId)
            ->where('wallet_type', $type->value)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return CustomerWallet::create([
                'uuid'        => (string) Str::uuid(),
                'company_id'  => $companyId,
                'customer_id' => $customerId,
                'vehicle_id'  => $vehicleId,
                'wallet_type' => $type->value,
                'branch_id'   => $branchId,
                'status'      => 'active',
                'balance'     => 0,
                'currency'    => 'SAR',
                'version'     => 0,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Another concurrent request created the wallet between our SELECT and INSERT.
            // Re-fetch and return the now-existing row.
            return CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->where('vehicle_id', $vehicleId)
                ->where('wallet_type', $type->value)
                ->lockForUpdate()
                ->firstOrFail();
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'unique constraint') ||
                str_contains($e->getMessage(), 'Unique violation') ||
                $e->getCode() === '23505') {
                return CustomerWallet::where('company_id', $companyId)
                    ->where('customer_id', $customerId)
                    ->where('vehicle_id', $vehicleId)
                    ->where('wallet_type', $type->value)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
            throw $e;
        }
    }

    private function creditWallet(
        CustomerWallet        $wallet,
        float                 $amount,
        int                   $userId,
        WalletTransactionType $type,
        ?int                  $vehicleId,
        ?string               $refType,
        ?int                  $refId,
        string                $traceId,
        string                $idempotencyKey,
        ?string               $paymentMode,
        ?string               $notes,
        ?int                  $invoiceId = null,
        ?int                  $paymentId = null,
    ): WalletTransaction {
        $this->assertFinancialTraceId($traceId);
        [$refType, $refId] = $this->resolveLedgerReference($wallet, $refType, $refId);

        $balanceBefore = (float) $wallet->balance;

        $wallet->increment('balance', $amount);
        $wallet->increment('version');
        $wallet->refresh();

        $this->validateLedgerBalances($balanceBefore, $amount, (float) $wallet->balance, true);

        try {
            $txn = WalletTransaction::create([
                'uuid'               => (string) Str::uuid(),
                'company_id'         => $wallet->company_id,
                'branch_id'          => $wallet->branch_id,
                'customer_wallet_id' => $wallet->id,
                'vehicle_id'         => $vehicleId,
                'created_by_user_id' => $userId,
                'type'               => $type,
                'amount'             => $amount,
                'payment_mode'       => $paymentMode,
                'balance_before'     => $balanceBefore,
                'balance_after'      => (float) $wallet->balance,
                'reference_type'     => $refType,
                'reference_id'       => $refId,
                'invoice_id'         => $invoiceId,
                'payment_id'         => $paymentId,
                'idempotency_key'    => $idempotencyKey,
                'trace_id'           => $traceId,
                'note'               => $notes,
                'created_at'         => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new \DomainException(
                'Duplicate idempotency key for wallet transaction.',
                0,
                $e
            );
        }

        Log::info('wallet.credit', [
            'financial_operation' => true,
            'wallet_id'           => $wallet->id,
            'type'                => $type->value,
            'amount'              => $amount,
            'balance_after'       => (float) $wallet->balance,
            'trace_id'            => $traceId,
            'company_id'          => $wallet->company_id,
        ]);

        return $txn;
    }

    private function debitWallet(
        CustomerWallet        $wallet,
        float                 $amount,
        int                   $userId,
        WalletTransactionType $type,
        ?int                  $vehicleId,
        ?string               $refType,
        ?int                  $refId,
        string                $traceId,
        string                $idempotencyKey,
        ?string               $paymentMode,
        ?string               $notes,
        ?int                  $invoiceId = null,
        ?int                  $paymentId = null,
        bool                  $allowNegativeBalance = false,
    ): WalletTransaction {
        $this->assertFinancialTraceId($traceId);
        [$refType, $refId] = $this->resolveLedgerReference($wallet, $refType, $refId);

        $balanceBefore = (float) $wallet->balance;

        if (! $allowNegativeBalance && $balanceBefore - $amount < -0.0001) {
            throw new \DomainException('Debit would result in negative balance.');
        }

        $wallet->decrement('balance', $amount);
        $wallet->increment('version');
        $wallet->refresh();

        $this->validateLedgerBalances($balanceBefore, $amount, (float) $wallet->balance, false);

        try {
            $txn = WalletTransaction::create([
                'uuid'               => (string) Str::uuid(),
                'company_id'         => $wallet->company_id,
                'branch_id'          => $wallet->branch_id,
                'customer_wallet_id' => $wallet->id,
                'vehicle_id'         => $vehicleId,
                'created_by_user_id' => $userId,
                'type'               => $type,
                'amount'             => $amount,
                'payment_mode'       => $paymentMode,
                'balance_before'     => $balanceBefore,
                'balance_after'      => (float) $wallet->balance,
                'reference_type'     => $refType,
                'reference_id'       => $refId,
                'invoice_id'         => $invoiceId,
                'payment_id'         => $paymentId,
                'idempotency_key'    => $idempotencyKey,
                'trace_id'           => $traceId,
                'note'               => $notes,
                'created_at'         => now(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new \DomainException(
                'Duplicate idempotency key for wallet transaction.',
                0,
                $e
            );
        }

        Log::info('wallet.debit', [
            'financial_operation' => true,
            'wallet_id'           => $wallet->id,
            'type'                => $type->value,
            'amount'              => $amount,
            'balance_after'       => (float) $wallet->balance,
            'trace_id'            => $traceId,
            'company_id'          => $wallet->company_id,
        ]);

        return $txn;
    }

    private function assertFinancialTraceId(string $traceId): void
    {
        if (trim($traceId) === '') {
            throw new \InvalidArgumentException('trace_id is required for wallet ledger operations.');
        }
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function resolveLedgerReference(CustomerWallet $wallet, ?string $refType, ?int $refId): array
    {
        if ($refType !== null && $refType !== '') {
            return [$refType, $refId];
        }

        return [CustomerWallet::class, $wallet->id];
    }

    /**
     * Verify stored balance matches previous balance ± movement (authoritative check after wallet refresh).
     */
    private function validateLedgerBalances(
        float $balanceBefore,
        float $movementAmount,
        float $balanceAfter,
        bool $movementIsCredit,
    ): void {
        $expected = $movementIsCredit
            ? $balanceBefore + $movementAmount
            : $balanceBefore - $movementAmount;

        if (abs($expected - $balanceAfter) > 0.0001) {
            throw new \RuntimeException(
                'Wallet balance integrity check failed: expected ending balance '.$expected.', got '.$balanceAfter.'.'
            );
        }
    }

    /**
     * Reserve an idempotency key atomically before executing any financial operation.
     *
     * Strategy:
     *   1. Attempt INSERT with UNIQUE(company_id, key).
     *   2. If the INSERT succeeds → first-time request, proceed.
     *   3. If a UniqueConstraintViolation is caught → duplicate, throw DomainException.
     *
     * This guarantees that even under concurrent requests hitting the DB simultaneously,
     * only one will succeed — the UNIQUE constraint is the enforcing layer, not PHP logic.
     */
    private function checkIdempotency(int $companyId, string $key): void
    {
        if (trim($key) === '') {
            throw new \InvalidArgumentException('idempotency_key is required for wallet operations.');
        }

        // Check if key exists AND has been processed (has response_snapshot)
        $existing = DB::table('idempotency_keys')
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            // If it has a response_snapshot, it was truly already processed
            if (!empty($existing->response_snapshot)) {
                throw new \DomainException(
                    "Duplicate idempotency key [{$key}]. This operation was already processed."
                );
            }
            // Key exists but no snapshot yet (inserted by IdempotencyMiddleware) — proceed
            return;
        }

        // Key doesn't exist — insert it
        try {
            DB::table('idempotency_keys')->insert([
                'company_id'  => $companyId,
                'key'         => $key,
                'endpoint'    => 'wallet',
                'trace_id'    => app()->bound('trace_id') ? app('trace_id') : null,
                'request_hash'=> hash('sha256', $companyId . '|' . $key),
                'expires_at'  => now()->addHours(24),
                'created_at'  => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race condition — another concurrent request inserted it first; check if processed
            $fresh = DB::table('idempotency_keys')
                ->where('company_id', $companyId)->where('key', $key)->first();
            if ($fresh && !empty($fresh->response_snapshot)) {
                throw new \DomainException(
                    "Duplicate idempotency key [{$key}]. This operation was already processed."
                );
            }
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Unique violation') ||
                str_contains($e->getMessage(), 'unique constraint') ||
                $e->getCode() === '23505') {
                // Same race condition handling
                return;
            }
            throw $e;
        }
    }

    /**
     * Persist the result of a successful wallet operation against the idempotency record.
     * Called after the DB transaction commits — safe to call outside the transaction.
     */
    private function persistIdempotencyResult(int $companyId, string $key, int $transactionId): void
    {
        DB::table('idempotency_keys')
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->update([
                'response_snapshot' => json_encode(['wallet_transaction_id' => $transactionId]),
            ]);
    }

    /**
     * Post a balanced journal entry via LedgerService.
     * Non-blocking: on failure, logs the error and continues.
     */
    private function postLedger(
        int    $companyId,
        ?int   $branchId,
        ?int   $userId,
        string $traceId,
        array  $data,
    ): void {
        try {
            $this->ledger->post($companyId, array_merge($data, ['trace_id' => $traceId]), $branchId, $userId);
        } catch (\Throwable $e) {
            Log::error('wallet.ledger_post_failed', [
                'error'      => $e->getMessage(),
                'company_id' => $companyId,
                'data'       => $data,
            ]);
        }
    }

    private function emitWalletCredited(
        WalletTransaction $txn,
        int $companyId,
        ?int $branchId,
        int $userId,
        string $sourceContext,
    ): void {
        $type = $txn->type instanceof \BackedEnum ? $txn->type->value : (string) $txn->type;
        $this->intelligentEvents->emit(new WalletCredited(
            companyId: $companyId,
            branchId: $branchId,
            causedByUserId: $userId,
            walletTransactionId: $txn->id,
            customerWalletId: (int) $txn->customer_wallet_id,
            amount: (float) $txn->amount,
            transactionType: $type,
            sourceContext: $sourceContext,
        ));
    }

    private function emitWalletDebited(
        WalletTransaction $txn,
        int $companyId,
        ?int $branchId,
        int $userId,
        ?int $invoiceId,
        string $sourceContext,
    ): void {
        $type = $txn->type instanceof \BackedEnum ? $txn->type->value : (string) $txn->type;
        $this->intelligentEvents->emit(new WalletDebited(
            companyId: $companyId,
            branchId: $branchId,
            causedByUserId: $userId,
            walletTransactionId: $txn->id,
            customerWalletId: (int) $txn->customer_wallet_id,
            amount: (float) $txn->amount,
            transactionType: $type,
            invoiceId: $invoiceId,
            sourceContext: $sourceContext,
        ));
    }
}
