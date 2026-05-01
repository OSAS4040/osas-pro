<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcessWorkOrderBulkOrchestratorJob;
use App\Models\Vehicle;
use App\Models\WorkOrderBatch;
use App\Models\WorkOrderBatchItem;
use App\Support\WorkOrders\WorkOrderBulkServiceTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class WorkOrderBulkSubmissionService
{
    public function __construct(
        private readonly BillingModelPolicyService $billingModelPolicy,
    ) {}

    /**
     * @param  list<int>  $vehicleIds
     * @return array{batch: WorkOrderBatch, replayed: bool}
     */
    public function submit(
        int $companyId,
        int $branchId,
        int $userId,
        array $vehicleIds,
        string $serviceCode,
        ?string $notes,
        ?string $idempotencyKey,
    ): array {
        $this->billingModelPolicy->assertTenantMayOperate($companyId);

        $max = (int) config('work_orders.bulk_max_vehicles', 500);
        $vehicleIds = array_values(array_unique(array_map('intval', $vehicleIds)));
        if ($vehicleIds === []) {
            throw new \DomainException('vehicle_ids must contain at least one vehicle id.');
        }
        if (count($vehicleIds) > $max) {
            throw new \DomainException("vehicle_ids exceeds maximum of {$max}.");
        }

        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : '';
        if ($idempotencyKey !== '') {
            $existing = WorkOrderBatch::query()
                ->where('company_id', $companyId)
                ->where('source', 'bulk_api')
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return ['batch' => $existing->load('items'), 'replayed' => true];
            }
        }

        $lines = $this->resolveLinesForVehicles($companyId, $branchId, $vehicleIds, $serviceCode);

        $batch = DB::transaction(function () use ($companyId, $branchId, $userId, $lines, $notes, $serviceCode, $idempotencyKey) {
            $batch = WorkOrderBatch::create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'created_by_user_id' => $userId,
                'status' => 'queued',
                'notes' => $notes,
                'bulk_service_code' => $serviceCode,
                'source' => 'bulk_api',
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            ]);

            foreach ($lines as $line) {
                WorkOrderBatchItem::create([
                    'work_order_batch_id' => $batch->id,
                    'vehicle_id' => (int) $line['vehicle_id'],
                    'customer_id' => (int) $line['customer_id'],
                    'status' => 'pending',
                    'payload' => $line,
                ]);
            }

            return $batch;
        });

        Log::info('work_orders.bulk.batch_queued', [
            'batch_uuid' => $batch->uuid,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'vehicle_count' => count($lines),
            'service_code' => $serviceCode,
            'chunk_size' => (int) config('work_orders.bulk_chunk_size', 50),
        ]);

        $this->dispatchOrchestrator($batch->id);

        return ['batch' => $batch->load('items'), 'replayed' => false];
    }

    private function dispatchOrchestrator(int $batchId): void
    {
        if (app()->runningUnitTests() && config('work_orders.bulk_inline_in_tests', true)) {
            ProcessWorkOrderBulkOrchestratorJob::dispatchSync($batchId);

            return;
        }

        ProcessWorkOrderBulkOrchestratorJob::dispatch($batchId)->afterResponse();
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return list<array{customer_id:int, vehicle_id:int, items: list<array<string, mixed>>}>
     */
    private function resolveLinesForVehicles(int $companyId, int $branchId, array $vehicleIds, string $serviceCode): array
    {
        $itemsTemplate = WorkOrderBulkServiceTemplates::linesFor($serviceCode);

        $byId = Vehicle::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereIn('id', $vehicleIds)
            ->get()
            ->keyBy('id');
        $lines = [];
        foreach ($vehicleIds as $vid) {
            $v = $byId->get($vid);
            if (! $v) {
                throw new \DomainException("Vehicle {$vid} not found for this company/branch.");
            }
            $lines[] = [
                'customer_id' => (int) $v->customer_id,
                'vehicle_id' => (int) $v->id,
                'items' => $itemsTemplate,
            ];
        }

        return $lines;
    }
}
