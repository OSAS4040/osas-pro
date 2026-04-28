<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WorkOrderBatch;
use App\Models\WorkOrderBatchItem;
use App\Services\WorkOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates work orders for one chunk of batch items (each WO in its own service-level transaction).
 */
final class ProcessWorkOrderBulkChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<int>  $itemIds
     */
    public function __construct(
        public readonly int $batchId,
        public readonly array $itemIds,
    ) {}

    public function backoff(): array
    {
        return [5, 20, 60];
    }

    public function handle(WorkOrderService $workOrders): void
    {
        $batch = WorkOrderBatch::query()->find($this->batchId);
        if (! $batch) {
            return;
        }

        foreach ($this->itemIds as $itemId) {
            $this->processOneItem($batch, (int) $itemId, $workOrders);
        }

        $this->finalizeBatchIfDone($this->batchId);
    }

    private function processOneItem(WorkOrderBatch $batch, int $itemId, WorkOrderService $workOrders): void
    {
        try {
            DB::transaction(function () use ($batch, $itemId, $workOrders): void {
                $item = WorkOrderBatchItem::query()
                    ->where('work_order_batch_id', $batch->id)
                    ->whereKey($itemId)
                    ->lockForUpdate()
                    ->first();

                if (! $item || $item->status !== 'pending') {
                    return;
                }
                if ($item->work_order_id !== null) {
                    $item->update(['status' => 'succeeded', 'error_message' => null]);

                    return;
                }

                $payload = is_array($item->payload) ? $item->payload : [];

                $order = $workOrders->create(
                    [
                        'customer_id' => (int) ($payload['customer_id'] ?? $item->customer_id),
                        'vehicle_id' => (int) ($payload['vehicle_id'] ?? $item->vehicle_id),
                        'items' => $payload['items'] ?? [],
                    ],
                    (int) $batch->company_id,
                    (int) $batch->branch_id,
                    (int) $batch->created_by_user_id,
                );

                $item->update([
                    'status' => 'succeeded',
                    'work_order_id' => $order->id,
                    'error_message' => null,
                ]);

                Log::info('work_orders.bulk.item_succeeded', [
                    'batch_id' => $batch->id,
                    'batch_item_id' => $item->id,
                    'work_order_id' => $order->id,
                ]);
            });
        } catch (\Throwable $e) {
            $item = WorkOrderBatchItem::query()
                ->where('work_order_batch_id', $batch->id)
                ->whereKey($itemId)
                ->first();
            if ($item && $item->status === 'pending') {
                $item->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            Log::warning('work_orders.bulk.item_failed', [
                'batch_id' => $batch->id,
                'batch_item_id' => $itemId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function finalizeBatchIfDone(int $batchId): void
    {
        DB::transaction(function () use ($batchId): void {
            $batch = WorkOrderBatch::query()->lockForUpdate()->find($batchId);
            if (! $batch || $batch->status !== 'processing') {
                return;
            }
            $stillPending = WorkOrderBatchItem::query()
                ->where('work_order_batch_id', $batchId)
                ->where('status', 'pending')
                ->exists();
            if ($stillPending) {
                return;
            }
            $batch->update(['status' => 'completed']);
            Log::info('work_orders.bulk.batch_completed', [
                'batch_uuid' => $batch->uuid,
                'batch_id' => $batch->id,
            ]);
        });
    }
}
