<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Models\Company;
use App\Models\Payment;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Support\ResolveCompanyBillingBranch;
use Illuminate\Support\Str;

/**
 * إنشاء سجل دفع فقط — لا اشتراك هنا.
 */
final class PaymentService
{
    public function __construct(
        private readonly ResolveCompanyBillingBranch $resolveBranch,
    ) {}

    public function createFromPaymentOrder(PaymentOrder $order, int $createdByUserId): Payment
    {
        $company = Company::query()->findOrFail($order->company_id);
        $branch  = ($this->resolveBranch)($company);

        return Payment::query()->create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $order->company_id,
            'branch_id'          => $branch->id,
            'invoice_id'         => null,
            'created_by_user_id' => $createdByUserId,
            'method'             => 'bank_transfer',
            'payment_method'     => 'bank_transfer',
            'amount'             => $order->total,
            'currency'           => $order->currency,
            'reference'          => $order->reference_code,
            'status'             => 'completed',
            'payment_order_id'   => $order->id,
            'meta'               => [
                'subscriptions_v2' => true,
                'payment_order_id' => $order->id,
                'breakdown'        => [
                    'wallet_amount' => '0.00',
                    'bank_amount'   => (string) $order->total,
                ],
            ],
            'created_at'         => now(),
        ]);
    }

    public function createFromWallet(PaymentOrder $order, int $createdByUserId, float $walletAmount): Payment
    {
        if ($walletAmount <= 0) {
            throw new \DomainException('Wallet amount must be greater than zero.');
        }

        return $this->createPaymentRecord($order, $createdByUserId, $walletAmount, 0.0, 'wallet');
    }

    public function createHybridPayment(
        PaymentOrder $order,
        int $createdByUserId,
        float $walletAmount,
        float $bankAmount,
    ): Payment {
        if ($walletAmount <= 0 || $bankAmount <= 0) {
            throw new \DomainException('Hybrid payment requires positive wallet and bank amounts.');
        }

        return $this->createPaymentRecord($order, $createdByUserId, $walletAmount, $bankAmount, 'hybrid');
    }

    public function attachInvoice(Payment $payment, int $invoiceId): void
    {
        if ($payment->invoice_id !== null) {
            throw new \DomainException('Payment already linked to an invoice.');
        }
        $payment->invoice_id = $invoiceId;
        $payment->save();
    }

    private function createPaymentRecord(
        PaymentOrder $order,
        int $createdByUserId,
        float $walletAmount,
        float $bankAmount,
        string $method,
    ): Payment {
        $company = Company::query()->findOrFail($order->company_id);
        $branch  = ($this->resolveBranch)($company);
        $total   = round($walletAmount + $bankAmount, 2);
        if (abs($total - (float) $order->total) > 0.01) {
            throw new \DomainException('Payment breakdown does not match payment order total.');
        }

        return Payment::query()->create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $order->company_id,
            'branch_id'          => $branch->id,
            'invoice_id'         => null,
            'created_by_user_id' => $createdByUserId,
            'method'             => $method,
            'payment_method'     => $method,
            'amount'             => $order->total,
            'currency'           => $order->currency,
            'reference'          => $order->reference_code,
            'status'             => 'completed',
            'payment_order_id'   => $order->id,
            'meta'               => [
                'subscriptions_v2' => true,
                'payment_order_id' => $order->id,
                'breakdown'        => [
                    'wallet_amount' => number_format($walletAmount, 2, '.', ''),
                    'bank_amount'   => number_format($bankAmount, 2, '.', ''),
                ],
            ],
            'created_at'         => now(),
        ]);
    }
}
