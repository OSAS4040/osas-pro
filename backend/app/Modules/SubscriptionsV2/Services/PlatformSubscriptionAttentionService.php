<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchStatus;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * تقديرات «تحتاج تدخل مشغّل المنصة» لطلبات دفع الاشتراك — قراءة فقط.
 */
final class PlatformSubscriptionAttentionService
{
    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $awaitingReview = PaymentOrder::query()
            ->where('status', PaymentOrderStatus::AwaitingReview)
            ->count();

        $matchedPendingFinalApproval = PaymentOrder::query()
            ->where('status', PaymentOrderStatus::Matched)
            ->whereHas(
                'reconciliationMatches',
                static fn (Builder $q) => $q->where('status', ReconciliationMatchStatus::Confirmed),
            )
            ->count();

        $pendingTransferWithSubmission = PaymentOrder::query()
            ->where('status', PaymentOrderStatus::PendingTransfer)
            ->whereHas('bankTransferSubmissions')
            ->count();

        $totalAttention = PaymentOrder::query()
            ->where(function (Builder $q): void {
                $q->where('status', PaymentOrderStatus::AwaitingReview)
                    ->orWhere(function (Builder $q2): void {
                        $q2->where('status', PaymentOrderStatus::Matched)
                            ->whereHas(
                                'reconciliationMatches',
                                static fn (Builder $m) => $m->where('status', ReconciliationMatchStatus::Confirmed),
                            );
                    })
                    ->orWhere(function (Builder $q3): void {
                        $q3->where('status', PaymentOrderStatus::PendingTransfer)
                            ->whereHas('bankTransferSubmissions');
                    });
            })
            ->count();

        return [
            'awaiting_review' => $awaitingReview,
            'matched_pending_final_approval' => $matchedPendingFinalApproval,
            'pending_transfer_with_submission' => $pendingTransferWithSubmission,
            'total_attention' => $totalAttention,
        ];
    }
}
