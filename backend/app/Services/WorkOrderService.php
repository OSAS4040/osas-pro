<?php

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Intelligence\Events\WorkOrderCreated;
use App\Intelligence\Events\WorkOrderStatusChanged;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkOrderService
{
    public function __construct(
        private readonly IntelligentEventEmitter $intelligentEvents,
    ) {}

    public function create(array $data, int $companyId, int $branchId, int $userId): WorkOrder
    {
        return DB::transaction(function () use ($data, $companyId, $branchId, $userId) {
            $counter     = WorkOrder::where('company_id', $companyId)->count() + 1;
            $orderNumber = sprintf('WO-%d-%06d', $companyId, $counter);

            $estimatedTotal = 0;
            $itemsData      = [];

            foreach ($data['items'] ?? [] as $item) {
                $lineSubtotal = $item['quantity'] * $item['unit_price'];
                $lineTax      = $lineSubtotal * (($item['tax_rate'] ?? 15) / 100);
                $lineTotal    = $lineSubtotal + $lineTax;
                $estimatedTotal += $lineTotal;

                $itemsData[] = [
                    'company_id'      => $companyId,
                    'item_type'       => $item['item_type'],
                    'product_id'      => $item['product_id'] ?? null,
                    'name'            => $item['name'],
                    'sku'             => $item['sku'] ?? null,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_rate'        => $item['tax_rate'] ?? 15,
                    'tax_amount'      => $lineTax,
                    'subtotal'        => $lineSubtotal,
                    'total'           => $lineTotal,
                ];
            }

            $order = WorkOrder::create([
                'uuid'                    => Str::uuid(),
                'company_id'              => $companyId,
                'branch_id'               => $branchId,
                'customer_id'             => $data['customer_id'],
                'vehicle_id'              => $data['vehicle_id'],
                'created_by_user_id'      => $userId,
                'assigned_technician_id'  => $data['assigned_technician_id'] ?? null,
                'order_number'            => $orderNumber,
                'work_order_number'       => $data['work_order_number'] ?? null,
                'status'                  => WorkOrderStatus::Pending,
                'priority'                => $data['priority'] ?? 'normal',
                'customer_complaint'      => $data['customer_complaint'] ?? null,
                'mileage_in'              => $data['mileage_in'] ?? null,
                'odometer_reading'        => $data['odometer_reading'] ?? null,
                'driver_name'             => $data['driver_name'] ?? null,
                'driver_phone'            => $data['driver_phone'] ?? null,
                'technician_notes'        => $data['notes'] ?? $data['technician_notes'] ?? null,
                'estimated_total'         => $estimatedTotal,
                'trace_id'                => app('trace_id'),
            ]);

            foreach ($itemsData as $item) {
                WorkOrderItem::create(array_merge($item, ['work_order_id' => $order->id]));
            }

            DB::afterCommit(function () use ($order, $companyId, $branchId, $userId) {
                $this->intelligentEvents->emit(new WorkOrderCreated(
                    companyId: $companyId,
                    branchId: $branchId,
                    causedByUserId: $userId,
                    workOrderId: $order->id,
                    orderNumber: $order->order_number,
                    status: $order->status->value,
                    sourceContext: 'WorkOrderService::create',
                ));
            });

            // Ensure DB defaults (e.g. version) are loaded for optimistic locking / API payloads.
            $order->refresh();

            return $order;
        });
    }

    /**
     * Transition work order status with optimistic locking.
     *
     * @throws \DomainException  when transition is not allowed
     * @throws \RuntimeException when optimistic lock conflict is detected
     */
    public function transition(WorkOrder $order, WorkOrderStatus $newStatus, array $meta = []): WorkOrder
    {
        return DB::transaction(function () use ($order, $newStatus, $meta) {
            $fromStatusValue = $order->status->value;

            $fresh = WorkOrder::where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->version !== $order->version) {
                throw new \RuntimeException(
                    "Work order #{$order->id} was modified by another request (version conflict). Please reload and try again."
                );
            }

            if (! $fresh->canTransitionTo($newStatus)) {
                $from = $fresh->status->value;
                $to   = $newStatus->value;

                Log::warning('work_order.invalid_transition', [
                    'work_order_id' => $fresh->id,
                    'from_status'   => $from,
                    'to_status'     => $to,
                    'trace_id'      => app('trace_id'),
                ]);

                throw new \DomainException(
                    "Cannot transition work order #{$fresh->id} from [{$from}] to [{$to}]."
                );
            }

            $update = [
                'status'  => $newStatus,
                'version' => $fresh->version + 1,
            ];

            if ($newStatus === WorkOrderStatus::InProgress && ! $fresh->started_at) {
                $update['started_at'] = now();
            }

            if ($newStatus === WorkOrderStatus::Completed) {
                $update['completed_at'] = now();
                if (isset($meta['technician_notes'])) {
                    $update['technician_notes'] = $meta['technician_notes'];
                }
                if (isset($meta['mileage_out'])) {
                    $update['mileage_out'] = $meta['mileage_out'];
                }
                if (isset($meta['diagnosis'])) {
                    $update['diagnosis'] = $meta['diagnosis'];
                }
            }

            if ($newStatus === WorkOrderStatus::Delivered) {
                $update['delivered_at'] = now();
            }

            $fresh->update($update);

            Log::info('work_order.transition', [
                'work_order_id' => $fresh->id,
                'from_status'   => $order->status->value,
                'to_status'     => $newStatus->value,
                'trace_id'      => app('trace_id'),
            ]);

            DB::afterCommit(function () use ($fresh, $fromStatusValue, $newStatus) {
                $this->intelligentEvents->emit(new WorkOrderStatusChanged(
                    companyId: (int) $fresh->company_id,
                    branchId: $fresh->branch_id ? (int) $fresh->branch_id : null,
                    causedByUserId: auth()->id() ? (int) auth()->id() : null,
                    workOrderId: $fresh->id,
                    fromStatus: $fromStatusValue,
                    toStatus: $newStatus->value,
                    sourceContext: 'WorkOrderService::transition',
                ));
            });

            return $fresh->refresh();
        });
    }

    public function update(WorkOrder $order, array $data): WorkOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $fresh = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($fresh->version !== $order->version) {
                throw new \RuntimeException(
                    "Work order #{$order->id} was modified concurrently. Please reload and try again."
                );
            }

            $allowed = ['assigned_technician_id', 'priority', 'customer_complaint',
                        'diagnosis', 'technician_notes', 'mileage_in', 'mileage_out',
                        'odometer_reading', 'driver_name', 'driver_phone', 'notes',
                        'estimated_total'];

            $payload = array_intersect_key($data, array_flip($allowed));
            $payload['version'] = $fresh->version + 1;

            $fresh->update($payload);

            if (isset($data['items'])) {
                $this->replaceItems($fresh, $data['items']);
            }

            return $fresh->refresh();
        });
    }

    private function replaceItems(WorkOrder $order, array $items): void
    {
        WorkOrderItem::where('work_order_id', $order->id)->delete();

        $total = 0;
        foreach ($items as $item) {
            $sub   = $item['quantity'] * $item['unit_price'];
            $tax   = $sub * (($item['tax_rate'] ?? 15) / 100);
            $total += $sub + $tax;

            WorkOrderItem::create([
                'company_id'      => $order->company_id,
                'work_order_id'   => $order->id,
                'item_type'       => $item['item_type'],
                'product_id'      => $item['product_id'] ?? null,
                'name'            => $item['name'],
                'sku'             => $item['sku'] ?? null,
                'quantity'        => $item['quantity'],
                'unit_price'      => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate'        => $item['tax_rate'] ?? 15,
                'tax_amount'      => $tax,
                'subtotal'        => $sub,
                'total'           => $sub + $tax,
            ]);
        }

        $order->update(['estimated_total' => $total]);
    }
}
