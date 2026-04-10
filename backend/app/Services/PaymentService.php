<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Intelligence\Events\InvoicePaid;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly IntelligentEventEmitter $intelligentEvents,
    ) {}

    /**
     * Record a payment for an invoice.
     * Wallet debit is NOT handled here — it must be triggered by the caller
     * (POSService or InvoiceService) using the correct typed method:
     *   - WalletService::debitIndividualForInvoice()  for B2C
     *   - WalletService::debitVehicleForInvoice()     for B2B fleet
     *
     * This method only creates the Payment record and updates invoice totals.
     */
    public function createPayment(
        Invoice $invoice,
        float   $amount,
        string  $method,
        int     $userId,
        string  $traceId,
        ?int    $branchId  = null,
        ?string $reference = null,
    ): Payment {
        if ($amount <= 0) {
            throw new \DomainException('Payment amount must be positive.');
        }

        $payment = DB::transaction(function () use (
            $invoice, $amount, $method, $userId, $traceId, $branchId, $reference
        ) {
            $locked = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                InvoiceStatus::Paid,
                InvoiceStatus::Cancelled,
                InvoiceStatus::Refunded,
                InvoiceStatus::Draft,
            ], true)) {
                throw new \DomainException('This invoice cannot accept a payment in its current status.');
            }

            $dueBefore  = (float) $locked->due_amount;
            $paidBefore = (float) $locked->paid_amount;

            if ($dueBefore <= 0.0001) {
                throw new \DomainException('This invoice has no outstanding balance.');
            }

            if ($amount - $dueBefore > 0.0001) {
                throw new \DomainException(
                    "Payment amount {$amount} exceeds invoice due amount {$dueBefore}."
                );
            }

            $payment = Payment::create([
                'uuid'                 => (string) Str::uuid(),
                'company_id'           => $locked->company_id,
                'branch_id'            => $branchId ?? $locked->branch_id,
                'invoice_id'           => $locked->id,
                'created_by_user_id'   => $userId,
                'method'               => $method,
                'payment_method'       => $method,
                'amount'               => $amount,
                'currency'             => $locked->currency ?? 'SAR',
                'reference'            => $reference,
                'status'               => 'completed',
                'external_sync_status' => null,
                'trace_id'             => $traceId,
                'created_at'           => now(),
            ]);

            // Remainder is derived from current due (not from total) so partial histories stay consistent.
            $newPaid = $paidBefore + $amount;
            $newDue  = max(0, $dueBefore - $amount);

            DB::table('invoices')->where('id', $locked->id)->update([
                'paid_amount' => $newPaid,
                'due_amount'  => $newDue,
                'status'      => $newDue <= 0.0001
                    ? InvoiceStatus::Paid->value
                    : InvoiceStatus::PartialPaid->value,
            ]);

            Log::info('payment.created', [
                'payment_id' => $payment->id,
                'invoice_id' => $locked->id,
                'method'     => $method,
                'amount'     => $amount,
                'trace_id'   => $traceId,
                'company_id' => $locked->company_id,
            ]);

            return $payment;
        });

        $inv = Invoice::find($payment->invoice_id);
        if ($inv) {
            $this->intelligentEvents->emit(new InvoicePaid(
                companyId: (int) $inv->company_id,
                branchId: $inv->branch_id ? (int) $inv->branch_id : null,
                causedByUserId: $userId,
                invoiceId: $inv->id,
                paymentId: $payment->id,
                amount: (float) $payment->amount,
                method: (string) $payment->method,
                invoiceStatus: $inv->fresh()->status->value,
                sourceContext: 'PaymentService::createPayment',
            ));
        }

        return $payment;
    }

    /**
     * Refund a payment.
     * For wallet payments, credits the wallet back using topUpIndividual or topUpFleet.
     * The caller must supply an idempotency_key for the credit operation.
     */
    public function refund(
        int     $paymentId,
        int     $userId,
        string  $traceId,
        string  $idempotencyKey,
        ?float  $amount = null,
        ?string $reason = null,
    ): Payment {
        return DB::transaction(function () use (
            $paymentId, $userId, $traceId, $idempotencyKey, $amount, $reason
        ) {
            $original = Payment::where('id', $paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($original->status === 'refunded') {
                throw new \DomainException('Payment has already been fully refunded.');
            }

            if ($original->original_payment_id) {
                throw new \DomainException('Cannot refund a reversal payment.');
            }

            $refundAmount = $amount ?? (float) $original->amount;

            if ($refundAmount > (float) $original->amount) {
                throw new \DomainException(
                    "Refund amount {$refundAmount} exceeds original payment amount {$original->amount}."
                );
            }

            if (in_array($original->method, ['wallet', 'prepaid'], true)) {
                $invoice = Invoice::find($original->invoice_id);
                if ($invoice?->customer_id) {
                    $notes = "Refund for payment #{$original->id}" . ($reason ? ": {$reason}" : '');
                    $ct = is_string($invoice->customer_type)
                        ? $invoice->customer_type
                        : (string) ($invoice->customer_type ?? '');

                    if ($invoice->vehicle_id && $ct === 'b2b') {
                        $this->walletService->refundVehicle(
                            companyId:      $original->company_id,
                            customerId:     $invoice->customer_id,
                            vehicleId:      (int) $invoice->vehicle_id,
                            amount:         $refundAmount,
                            invoiceId:      $original->invoice_id,
                            paymentId:      $original->id,
                            userId:         $userId,
                            traceId:        $traceId,
                            idempotencyKey: $idempotencyKey,
                            branchId:       $original->branch_id,
                            notes:          $notes,
                        );
                    } else {
                        $this->walletService->refundIndividual(
                            companyId:      $original->company_id,
                            customerId:     $invoice->customer_id,
                            vehicleId:      $invoice->vehicle_id,
                            amount:         $refundAmount,
                            invoiceId:      $original->invoice_id,
                            paymentId:      $original->id,
                            userId:         $userId,
                            traceId:        $traceId,
                            idempotencyKey: $idempotencyKey,
                            branchId:       $original->branch_id,
                            notes:          $notes,
                        );
                    }
                }
            }

            $refund = Payment::create([
                'uuid'                => (string) Str::uuid(),
                'company_id'          => $original->company_id,
                'branch_id'           => $original->branch_id,
                'invoice_id'          => $original->invoice_id,
                'created_by_user_id'  => $userId,
                'method'              => $original->method,
                'payment_method'      => $original->payment_method ?? $original->method,
                'amount'              => $refundAmount,
                'currency'            => $original->currency,
                'reference'           => $reason,
                'status'              => 'refunded',
                'original_payment_id' => $original->id,
                'trace_id'            => $traceId,
                'created_at'          => now(),
            ]);

            DB::table('payments')
                ->where('id', $original->id)
                ->update(['status' => 'refunded', 'reversal_payment_id' => $refund->id]);

            if ($original->invoice_id) {
                $inv = Invoice::where('id', $original->invoice_id)->lockForUpdate()->first();
                if ($inv) {
                    $paidBefore = (float) $inv->paid_amount;
                    $dueBefore  = (float) $inv->due_amount;
                    $newPaid    = max(0, $paidBefore - $refundAmount);
                    $newDue     = max(0, $dueBefore + $refundAmount);

                    DB::table('invoices')->where('id', $inv->id)->update([
                        'paid_amount' => $newPaid,
                        'due_amount'  => $newDue,
                        'status'      => $newPaid <= 0.0001
                            ? InvoiceStatus::Pending->value
                            : InvoiceStatus::PartialPaid->value,
                    ]);
                }
            }

            Log::info('payment.refunded', [
                'original_id' => $original->id,
                'refund_id'   => $refund->id,
                'amount'      => $refundAmount,
                'trace_id'    => $traceId,
                'company_id'  => $original->company_id,
            ]);

            return $refund;
        });
    }
}
