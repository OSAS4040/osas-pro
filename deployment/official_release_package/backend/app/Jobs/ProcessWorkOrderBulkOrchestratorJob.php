<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WorkOrderBatch;
use App\Models\WorkOrderBatchItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Splits a bulk_api batch into {@see ProcessWorkOrderBulkChunkJob} chunks (queue throughput).
 */
final class ProcessWorkOrderBulkOrchestratorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $batchId) {}

    public function handle(): void
    {
        $batch = WorkOrderBatch::query()->find($this->batchId);
        if (! $batch || $batch->source !== 'bulk_api') {
            return;
        }
        if ($batch->status !== 'queued') {
            return;
        }

        $batch->update(['status' => 'processing']);

        $itemIds = WorkOrderBatchItem::query()
            ->where('work_order_batch_id', $batch->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $chunkSize = max(1, (int) config('work_orders.bulk_chunk_size', 50));
        $queue = (string) config('work_orders.bulk_queue', 'default');

        foreach (array_chunk($itemIds, $chunkSize) as $chunk) {
            ProcessWorkOrderBulkChunkJob::dispatch($this->batchId, $chunk)->onQueue($queue);
        }
    }
}
