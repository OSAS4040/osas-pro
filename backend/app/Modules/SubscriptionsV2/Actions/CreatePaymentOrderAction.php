<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Actions;

use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\PaymentOrderService;
use App\Modules\SubscriptionsV2\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\DB;

final class CreatePaymentOrderAction
{
    public function __construct(
        private readonly PaymentOrderService $paymentOrderService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function execute(int $companyId, int $planId, ?int $actorUserId): PaymentOrder
    {
        return DB::transaction(function () use ($companyId, $planId, $actorUserId): PaymentOrder {
            $order = $this->paymentOrderService->createPaymentOrder(
            $companyId,
            $planId,
            $actorUserId,
        );
            DB::afterCommit(function () use ($companyId, $order): void {
                $this->realtimeNotificationService->publish(
                    'payment_order_created',
                    (int) $companyId,
                    'company',
                    [
                        'type' => 'payment_order_created',
                        'company_id' => $companyId,
                        'payment_order_id' => $order->id,
                        'message' => 'تم إنشاء طلب دفع جديد.',
                    ],
                );
                $this->realtimeNotificationService->publish(
                    'payment_order_created_admin',
                    null,
                    'admin',
                    [
                        'type' => 'payment_order_created',
                        'company_id' => $companyId,
                        'payment_order_id' => $order->id,
                        'message' => 'طلب دفع جديد يحتاج متابعة.',
                    ],
                );
            });

            return $order;
        });
    }
}
