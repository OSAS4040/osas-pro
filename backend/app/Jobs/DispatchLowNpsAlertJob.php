<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NpsRating;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Surfaces very low NPS scores to operators (logs + queue hook; extend with mail/push later).
 */
final class DispatchLowNpsAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $npsRatingId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $rating = NpsRating::query()->find($this->npsRatingId);
        if ($rating === null) {
            return;
        }

        if ($rating->score > 2) {
            return;
        }

        Log::warning('customer.nps.low_score', [
            'nps_rating_id' => $rating->id,
            'company_id' => $rating->company_id,
            'score' => $rating->score,
            'channel' => $rating->channel,
            'invoice_id' => $rating->invoice_id,
            'work_order_id' => $rating->work_order_id,
            'customer_id' => $rating->customer_id,
            'comment_preview' => $rating->comment !== null && $rating->comment !== ''
                ? mb_substr((string) $rating->comment, 0, 200)
                : null,
        ]);
    }
}
