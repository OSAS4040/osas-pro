<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Models\Plan;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use Illuminate\Support\Str;

final class PaymentOrderService
{
    private const VAT_RATE = 0.15;

    private const REF_PREFIX = 'SUB-ORD-';

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function createPaymentOrder(int $companyId, int $planId, ?int $createdByUserId): PaymentOrder
    {
        $plan = Plan::query()->whereKey($planId)->where('is_active', true)->firstOrFail();

        $amountExVat = (float) $plan->price_monthly;
        $vat         = round($amountExVat * self::VAT_RATE, 2);
        $total       = round($amountExVat + $vat, 2);

        $order = PaymentOrder::query()->create([
            'company_id'     => $companyId,
            'plan_id'        => $plan->id,
            'amount'         => $amountExVat,
            'vat'            => $vat,
            'total'          => $total,
            'currency'       => (string) $plan->currency,
            'reference_code' => $this->uniqueReferenceCode(),
            'status'         => PaymentOrderStatus::PendingTransfer,
            'expires_at'     => now()->addDays(7),
            'created_by'     => $createdByUserId,
        ]);

        $this->auditLogService->log(
            $createdByUserId,
            'create_order',
            'PaymentOrder',
            $order->id,
            null,
            $order->only(['company_id', 'plan_id', 'amount', 'vat', 'total', 'status', 'reference_code']),
            ['plan_slug' => $plan->slug],
        );

        return $order;
    }

    public function getById(int $id): ?PaymentOrder
    {
        return PaymentOrder::query()->find($id);
    }

    public function markAwaitingReview(PaymentOrder $order): PaymentOrder
    {
        $order->status = PaymentOrderStatus::AwaitingReview;
        $order->save();

        return $order->fresh() ?? $order;
    }

    public function expireOrder(PaymentOrder $order): PaymentOrder
    {
        $order->status = PaymentOrderStatus::Expired;
        $order->save();

        return $order->fresh() ?? $order;
    }

    private function uniqueReferenceCode(): string
    {
        do {
            $code = self::REF_PREFIX.strtoupper(Str::random(10));
        } while (PaymentOrder::query()->where('reference_code', $code)->exists());

        return $code;
    }
}
