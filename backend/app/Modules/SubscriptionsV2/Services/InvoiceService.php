<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Support\ResolveCompanyBillingBranch;
use Illuminate\Support\Str;

/**
 * إنشاء فاتورة من دفع مُعتمد فقط — لا يُستدعى بدون Payment مُكتمل ومربوط بالطلب.
 */
final class InvoiceService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PaymentService $paymentService,
        private readonly ResolveCompanyBillingBranch $resolveBranch,
    ) {}

    public function createFromPayment(Payment $payment, PaymentOrder $order, int $createdByUserId): Invoice
    {
        if ($payment->invoice_id !== null) {
            throw new \DomainException('Invoice cannot be created: payment already has an invoice.');
        }
        if ($payment->status !== 'completed') {
            throw new \DomainException('Invoice requires a completed payment.');
        }
        if ((int) $payment->company_id !== (int) $order->company_id) {
            throw new \DomainException('Payment does not belong to the payment order company.');
        }
        if ((int) ($payment->payment_order_id ?? 0) !== (int) $order->id) {
            throw new \DomainException('Payment is not linked to this payment order.');
        }

        $company = Company::query()->findOrFail($order->company_id);
        $branch   = ($this->resolveBranch)($company);
        $plan     = $order->plan()->firstOrFail();

        $invoiceNumber = $this->allocateInvoiceNumber($company->id, $order->id, $payment->id);

        $invoice = Invoice::query()->create([
            'uuid'                 => (string) Str::uuid(),
            'company_id'           => $order->company_id,
            'branch_id'            => $branch->id,
            'customer_id'          => null,
            'vehicle_id'           => null,
            'created_by_user_id'   => $createdByUserId,
            'invoice_number'       => $invoiceNumber,
            'type'                 => 'subscription',
            'status'               => InvoiceStatus::Paid,
            'customer_type'        => 'b2b',
            'source_type'          => PaymentOrder::class,
            'source_id'            => $order->id,
            'subtotal'             => $order->amount,
            'discount_amount'      => 0,
            'tax_amount'           => $order->vat,
            'total'                => $order->total,
            'paid_amount'          => $order->total,
            'due_amount'           => 0,
            'currency'             => $order->currency,
            'idempotency_key'      => 'subv2_po_'.$order->id.'_pay_'.$payment->id,
            'issued_at'            => now(),
            'due_at'               => now(),
            'notes'                => 'Subscriptions V2 — plan '.$plan->slug,
        ]);

        InvoiceItem::query()->create([
            'company_id'       => $order->company_id,
            'invoice_id'       => $invoice->id,
            'product_id'       => null,
            'service_id'       => null,
            'name'             => 'Subscription — '.$plan->name,
            'description'      => null,
            'sku'              => $plan->slug,
            'quantity'         => 1,
            'unit_price'       => $order->amount,
            'cost_price'       => null,
            'discount_amount'  => 0,
            'tax_rate'         => 15,
            'tax_amount'       => $order->vat,
            'subtotal'         => $order->amount,
            'total'            => $order->total,
            'line_total'       => $order->total,
        ]);

        $this->paymentService->attachInvoice($payment, (int) $invoice->id);

        $this->auditLogService->log(
            $createdByUserId,
            'create_invoice',
            'Invoice',
            $invoice->id,
            null,
            ['invoice_number' => $invoice->invoice_number, 'total' => (string) $invoice->total, 'payment_id' => $payment->id],
            ['payment_order_id' => $order->id],
        );

        return $invoice;
    }

    public function createForSubscriptionCycle(
        Payment $payment,
        Subscription $subscription,
        Plan $plan,
        int $createdByUserId,
        string $noteContext,
    ): Invoice {
        if ($payment->invoice_id !== null) {
            throw new \DomainException('Invoice cannot be created: payment already has an invoice.');
        }
        if ($payment->status !== 'completed') {
            throw new \DomainException('Invoice requires a completed payment.');
        }

        $company = Company::query()->findOrFail((int) $subscription->company_id);
        $branch  = ($this->resolveBranch)($company);
        $amount  = (float) $payment->amount;
        $net     = round($amount / 1.15, 2);
        $vat     = round($amount - $net, 2);

        $invoice = Invoice::query()->create([
            'uuid'                 => (string) Str::uuid(),
            'company_id'           => (int) $subscription->company_id,
            'branch_id'            => $branch->id,
            'customer_id'          => null,
            'vehicle_id'           => null,
            'created_by_user_id'   => $createdByUserId,
            'invoice_number'       => 'SUBV2-CYCLE-'.(int) $subscription->company_id.'-'.$payment->id,
            'type'                 => 'subscription',
            'status'               => InvoiceStatus::Paid,
            'customer_type'        => 'b2b',
            'source_type'          => Subscription::class,
            'source_id'            => $subscription->id,
            'subtotal'             => $net,
            'discount_amount'      => 0,
            'tax_amount'           => $vat,
            'total'                => $amount,
            'paid_amount'          => $amount,
            'due_amount'           => 0,
            'currency'             => $payment->currency,
            'idempotency_key'      => 'subv2_sub_'.$subscription->id.'_pay_'.$payment->id,
            'issued_at'            => now(),
            'due_at'               => now(),
            'notes'                => 'Subscriptions V2 cycle '.$noteContext.' — plan '.$plan->slug,
        ]);

        InvoiceItem::query()->create([
            'company_id'       => (int) $subscription->company_id,
            'invoice_id'       => $invoice->id,
            'product_id'       => null,
            'service_id'       => null,
            'name'             => 'Subscription cycle — '.$plan->name,
            'description'      => null,
            'sku'              => $plan->slug,
            'quantity'         => 1,
            'unit_price'       => $net,
            'cost_price'       => null,
            'discount_amount'  => 0,
            'tax_rate'         => 15,
            'tax_amount'       => $vat,
            'subtotal'         => $net,
            'total'            => $amount,
            'line_total'       => $amount,
        ]);

        $this->paymentService->attachInvoice($payment, (int) $invoice->id);

        return $invoice;
    }

    private function allocateInvoiceNumber(int $companyId, int $orderId, int $paymentId): string
    {
        return 'SUBV2-'.str_pad((string) $companyId, 4, '0', STR_PAD_LEFT).'-'.$orderId.'-'.$paymentId;
    }
}
