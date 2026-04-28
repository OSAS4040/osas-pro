<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchType;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\BankTransferSubmission;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Models\ReconciliationMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReconciliationService
{
    private const AUTO_CONFIRM_MIN = 92.0;

    private const REVIEW_MIN = 70.0;

    private const IGNORE_BELOW = 60.0;

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return list<array{transaction: BankTransaction, score: float}>
     */
    public function findMatches(PaymentOrder $order): array
    {
        $out = [];
        foreach ($this->candidateTransactions($order) as $tx) {
            $score = $this->scoreMatch($order, $tx);
            if ($score >= self::IGNORE_BELOW) {
                $out[] = ['transaction' => $tx, 'score' => $score];
            }
        }

        usort($out, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $out;
    }

    public function scoreMatch(PaymentOrder $order, BankTransaction $tx): float
    {
        $submission = $order->bankTransferSubmissions()->orderByDesc('id')->first();

        $score = 0.0;
        $score += $this->scoreAmount($order, $tx);
        $score += $this->scoreReference($order, $tx);
        $score += $this->scoreDateProximity($order, $tx, $submission);
        $score += $this->scoreSender($tx, $submission);

        return min(100.0, round($score, 2));
    }

    public function autoMatch(PaymentOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            /** @var PaymentOrder|null $locked */
            $locked = PaymentOrder::query()->whereKey($order->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }
            if (in_array($locked->status, [
                PaymentOrderStatus::Approved,
                PaymentOrderStatus::Rejected,
                PaymentOrderStatus::Cancelled,
                PaymentOrderStatus::Expired,
            ], true)) {
                return;
            }

            $best    = null;
            $bestScore = -1.0;
            foreach ($this->candidateTransactions($locked) as $tx) {
                $s = $this->scoreMatch($locked, $tx);
                if ($s >= self::AUTO_CONFIRM_MIN && $s > $bestScore) {
                    $bestScore = $s;
                    $best      = $tx;
                }
            }

            if ($best !== null && $bestScore >= self::AUTO_CONFIRM_MIN) {
                $this->confirmMatch($locked, $best, ReconciliationMatchType::Auto, null, $bestScore, 'auto_match');

                return;
            }

            foreach ($this->candidateTransactions($locked) as $tx) {
                $s = $this->scoreMatch($locked, $tx);
                if ($s < self::REVIEW_MIN || $s < self::IGNORE_BELOW) {
                    continue;
                }
                if ($s >= self::AUTO_CONFIRM_MIN) {
                    continue;
                }

                $exists = ReconciliationMatch::query()
                    ->where('payment_order_id', $locked->id)
                    ->where('bank_transaction_id', $tx->id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                try {
                    ReconciliationMatch::query()->create([
                        'payment_order_id'    => $locked->id,
                        'bank_transaction_id' => $tx->id,
                        'score'               => $s,
                        'match_type'          => ReconciliationMatchType::Auto,
                        'status'              => ReconciliationMatchStatus::Pending,
                        'matched_by'          => null,
                        'decision_notes'      => null,
                    ]);
                } catch (\Throwable) {
                    continue;
                }
            }
        });
    }

    /**
     * @deprecated Phase 2 placeholder — retained no-op for backward callers.
     */
    public function markAsMatched(PaymentOrder $order, float $score): void
    {
        unset($order, $score);
    }

    public function confirmMatch(
        PaymentOrder $order,
        BankTransaction $tx,
        ReconciliationMatchType $type,
        ?int $matchedByUserId,
        float $score,
        string $auditContext,
    ): void {
        if ($tx->is_matched) {
            throw new \DomainException('Bank transaction is already matched.');
        }
        if ($order->hasConfirmedMatch()) {
            throw new \DomainException('Payment order already has a confirmed match.');
        }

        ReconciliationMatch::query()
            ->where('payment_order_id', $order->id)
            ->where('status', ReconciliationMatchStatus::Pending)
            ->where('bank_transaction_id', '!=', $tx->id)
            ->update([
                'status'         => ReconciliationMatchStatus::Rejected,
                'decision_notes' => 'superseded_by_selection',
            ]);

        /** @var ReconciliationMatch $match */
        $match = ReconciliationMatch::query()->updateOrCreate(
            [
                'payment_order_id'    => $order->id,
                'bank_transaction_id' => $tx->id,
            ],
            [
                'score'          => $score,
                'match_type'     => $type,
                'status'         => ReconciliationMatchStatus::Confirmed,
                'matched_by'     => $matchedByUserId,
                'decision_notes' => $auditContext,
            ],
        );

        $tx->is_matched = true;
        $tx->save();

        if (in_array($order->status, [PaymentOrderStatus::AwaitingReview, PaymentOrderStatus::PendingTransfer], true)) {
            $order->status = PaymentOrderStatus::Matched;
            $order->save();
        }

        $this->auditLogService->log(
            $matchedByUserId,
            'reconciliation_confirmed',
            'ReconciliationMatch',
            $match->id,
            null,
            [
                'payment_order_id'    => $order->id,
                'bank_transaction_id' => $tx->id,
                'score'               => (string) $score,
                'type'                => $type->value,
            ],
            ['context' => $auditContext],
        );
    }

    /**
     * @return Collection<int, BankTransaction>
     */
    private function candidateTransactions(PaymentOrder $order): Collection
    {
        return BankTransaction::query()
            ->where('is_matched', false)
            ->where('currency', $order->currency)
            ->whereDoesntHave('reconciliationMatches', function ($q): void {
                $q->whereIn('status', [
                    ReconciliationMatchStatus::Pending->value,
                    ReconciliationMatchStatus::Confirmed->value,
                ]);
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    private function scoreAmount(PaymentOrder $order, BankTransaction $tx): float
    {
        $o = (float) $order->total;
        $t = (float) $tx->amount;
        if ($o <= 0) {
            return 0.0;
        }
        if (abs($t - $o) < 0.01) {
            return 50.0;
        }
        $rel = abs($t - $o) / $o;
        if ($rel <= 0.005) {
            return 45.0;
        }
        if ($rel <= 0.02) {
            return 30.0;
        }

        return 0.0;
    }

    private function scoreReference(PaymentOrder $order, BankTransaction $tx): float
    {
        $needle = strtoupper($order->reference_code);
        $hay    = strtoupper(implode(' ', array_filter([
            (string) $tx->reference_extracted,
            (string) $tx->bank_reference,
            (string) $tx->description,
        ])));

        if ($hay === '') {
            return 0.0;
        }
        if (str_contains($hay, $needle)) {
            return 30.0;
        }
        if ($tx->reference_extracted !== null
            && strtoupper((string) $tx->reference_extracted) === $needle) {
            return 30.0;
        }

        return 0.0;
    }

    private function scoreDateProximity(PaymentOrder $order, BankTransaction $tx, ?BankTransferSubmission $submission): float
    {
        $anchor = $submission?->transfer_date?->format('Y-m-d') ?? $order->created_at?->format('Y-m-d');
        if ($anchor === null) {
            return 0.0;
        }
        $txDate = $tx->transaction_date?->format('Y-m-d');
        if ($txDate === null) {
            return 0.0;
        }
        $d1 = strtotime($anchor);
        $d2 = strtotime($txDate);
        if ($d1 === false || $d2 === false) {
            return 0.0;
        }
        $days = abs(($d2 - $d1) / 86400);
        if ($days <= 1) {
            return 10.0;
        }
        if ($days <= 3) {
            return 7.0;
        }
        if ($days <= 7) {
            return 4.0;
        }

        return 0.0;
    }

    private function scoreSender(BankTransaction $tx, ?BankTransferSubmission $submission): float
    {
        $a = trim((string) ($tx->sender_name ?? ''));
        $b = trim((string) ($submission?->sender_name ?? ''));
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if (strcasecmp($a, $b) === 0) {
            return 10.0;
        }
        $pct = 0;
        similar_text(mb_strtolower($a), mb_strtolower($b), $pct);
        if ($pct >= 85) {
            return 8.0;
        }
        if ($pct >= 70) {
            return 5.0;
        }

        return 0.0;
    }
}
