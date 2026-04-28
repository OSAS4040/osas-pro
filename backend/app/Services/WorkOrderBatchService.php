<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\WorkOrderBatch;
use App\Models\WorkOrderBatchItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WorkOrderBatchService
{
    public function __construct(
        private readonly WorkOrderService $workOrderService,
        private readonly BillingModelPolicyService $billingModelPolicy,
    ) {}

    /**
     * Synchronous batch used by the sensitive-preview UI: one HTTP transaction creates every work order.
     * For large bursts (hundreds/thousands of vehicles) use {@see WorkOrderBulkSubmissionService} + queue chunks instead.
     *
     * @param  list<array{customer_id:int, vehicle_id:int, items?: array}>  $lines
     */
    public function processBatch(int $companyId, int $branchId, int $userId, array $lines, ?string $notes = null): WorkOrderBatch
    {
        $this->billingModelPolicy->assertTenantMayOperate($companyId);

        return DB::transaction(function () use ($companyId, $branchId, $userId, $lines, $notes) {
            $batch = WorkOrderBatch::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'created_by_user_id' => $userId,
                'status' => 'processing',
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $item = WorkOrderBatchItem::create([
                    'work_order_batch_id' => $batch->id,
                    'vehicle_id' => (int) $line['vehicle_id'],
                    'customer_id' => (int) $line['customer_id'],
                    'status' => 'pending',
                    'payload' => $line,
                ]);

                try {
                    $wo = $this->workOrderService->create(
                        [
                            'customer_id' => (int) $line['customer_id'],
                            'vehicle_id' => (int) $line['vehicle_id'],
                            'items' => $line['items'] ?? [],
                        ],
                        $companyId,
                        $branchId,
                        $userId,
                    );
                    $item->update([
                        'status' => 'succeeded',
                        'work_order_id' => $wo->id,
                        'error_message' => null,
                    ]);
                } catch (\Throwable $e) {
                    $item->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            $batch->update(['status' => 'completed']);

            return $batch->load('items');
        });
    }
}
