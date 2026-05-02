<?php

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Intelligence\Events\WorkOrderCreated;
use App\Intelligence\Events\WorkOrderStatusChanged;
use App\Jobs\NotifyCustomerWorkOrderWhatsAppJob;
use App\Models\Company;
use App\Models\OrgUnit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\WorkOrderPricing\ResolvedWorkOrderLinePrice;
use App\Support\TenantBusinessFeatures;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkOrderService
{
    public function __construct(
        private readonly IntelligentEventEmitter $intelligentEvents,
        private readonly BillingModelPolicyService $billingModelPolicy,
        private readonly WorkOrderApprovedCreditBridge $approvedCreditBridge,
        private readonly WorkOrderPricingResolverService $pricingResolver,
    ) {}

    /**
     * HTTP requests bind trace_id via middleware; queue/console contexts do not.
     */
    private function resolveTraceId(): string
    {
        if (app()->bound('trace_id')) {
            $v = app('trace_id');

            return is_string($v) && $v !== '' ? $v : Str::uuid()->toString();
        }

        return Str::uuid()->toString();
    }

    public function create(array $data, int $companyId, int $branchId, int $userId): WorkOrder
    {
        $startedAt = microtime(true);

        $itemsIn = $data['items'] ?? [];
        if (! is_array($itemsIn) || count($itemsIn) < 1) {
            throw new \DomainException('يجب إضافة بند خدمة أو منتج واحد على الأقل.');
        }

        $order = DB::transaction(function () use ($data, $companyId, $branchId, $userId) {
            $this->billingModelPolicy->assertTenantMayOperate($companyId);

            $customerId = (int) $data['customer_id'];
            $orgUnitId = isset($data['org_unit_id']) ? (int) $data['org_unit_id'] : $this->resolveCreatorOrgUnitId($userId, $companyId);
            if ($customerId > 0 && $orgUnitId > 0) {
                $sectorPrefix = $this->resolveSectorPrefix($companyId, $orgUnitId);
                $nextSuffix = $this->allocateNextScopedOrderNumberSuffix((int) $companyId, $customerId, $orgUnitId, $sectorPrefix);
                $orderNumber = sprintf('WO-%d-%s-C%d-%06d', $companyId, $sectorPrefix, $customerId, $nextSuffix);
            } else {
                $nextSuffix  = $this->allocateNextOrderNumberSuffix((int) $companyId);
                $orderNumber = sprintf('WO-%d-%06d', $companyId, $nextSuffix);
            }

            $fleetOrigin = ($data['created_by_side'] ?? '') === 'fleet';
            [$itemsData, $estimatedTotal] = $this->compileWorkOrderLineItems(
                $data['items'],
                $companyId,
                (int) $data['customer_id'],
                $branchId,
                $fleetOrigin,
                isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            );

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
                'status'                  => $this->resolveInitialWorkOrderStatus($companyId),
                'priority'                => $data['priority'] ?? 'normal',
                'customer_complaint'      => $data['customer_complaint'] ?? null,
                'mileage_in'              => $data['mileage_in'] ?? null,
                'odometer_reading'        => $data['odometer_reading'] ?? null,
                'driver_name'             => $data['driver_name'] ?? null,
                'driver_phone'            => $data['driver_phone'] ?? null,
                'technician_notes'        => $data['notes'] ?? $data['technician_notes'] ?? null,
                'estimated_total'         => $estimatedTotal,
                'trace_id'                => $this->resolveTraceId(),
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

        if ($order->status === WorkOrderStatus::Approved) {
            $this->approvedCreditBridge->onApproved($order->fresh(), $userId);
        }

        $warnMs = (int) config('observability.work_order_create_warn_ms', 0);
        if ($warnMs > 0) {
            $elapsedMs = (microtime(true) - $startedAt) * 1000;
            if ($elapsedMs >= $warnMs) {
                Log::warning('perf.work_order_create_slow', [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'work_order_id' => $order->id,
                    'duration_ms' => round($elapsedMs, 2),
                    'threshold_ms' => $warnMs,
                    'trace_id' => $this->resolveTraceId(),
                ]);
            }
        }

        return $order;
    }

    /**
     * Atomic O(1) per-company suffix for `order_number` (format WO-{company}-{6 digits}).
     * Uses `work_order_sequences` + single-row upsert (PostgreSQL).
     */
    private function allocateNextOrderNumberSuffix(int $companyId): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new \RuntimeException('work_order_sequences requires PostgreSQL.');
        }

        $now = now();

        // First row for a company: start at max(existing formatted order_number suffix) + 1 so we never
        // collide after legacy data or seeders inserted work_orders before a sequence row existed.
        $row = DB::selectOne(
            'insert into work_order_sequences (company_id, last_allocated, created_at, updated_at)
             select ?::bigint,
                    coalesce((
                        select max(cast(right(order_number, 6) as integer))
                        from work_orders
                        where company_id = ?
                          and order_number ~ (\'^WO-\' || ?::text || \'-[0-9]{6}$\')
                    ), 0) + 1,
                    ?,
                    ?
             on conflict (company_id) do update
             set last_allocated = work_order_sequences.last_allocated + 1,
                 updated_at = excluded.updated_at
             returning last_allocated',
            [$companyId, $companyId, $companyId, $now, $now],
        );

        if ($row === null || ! isset($row->last_allocated)) {
            throw new \RuntimeException('Failed to allocate work order sequence.');
        }

        return (int) $row->last_allocated;
    }

    private function allocateNextScopedOrderNumberSuffix(int $companyId, int $customerId, int $orgUnitId, string $sectorPrefix): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new \RuntimeException('work_order_scope_sequences requires PostgreSQL.');
        }

        $now = now();
        $pattern = '^WO-'.$companyId.'-'.$sectorPrefix.'-C'.$customerId.'-[0-9]{6}$';
        $row = DB::selectOne(
            'insert into work_order_scope_sequences (company_id, customer_id, org_unit_id, last_allocated, created_at, updated_at)
             select ?::bigint, ?::bigint, ?::bigint,
                    coalesce((
                        select max(cast(right(order_number, 6) as integer))
                        from work_orders
                        where company_id = ?
                          and customer_id = ?
                          and order_number ~ ?
                    ), 0) + 1,
                    ?, ?
             on conflict (company_id, customer_id, org_unit_id) do update
             set last_allocated = work_order_scope_sequences.last_allocated + 1,
                 updated_at = excluded.updated_at
             returning last_allocated',
            [$companyId, $customerId, $orgUnitId, $companyId, $customerId, $pattern, $now, $now],
        );

        if ($row === null || ! isset($row->last_allocated)) {
            throw new \RuntimeException('Failed to allocate scoped work order sequence.');
        }

        return (int) $row->last_allocated;
    }

    /**
     * ورش الخدمة (service_center) تبدأ أمر العمل معتمداً حتى يتابع الفني التنفيذ مباشرة.
     * أنشطة أخرى (مثل retail / fleet_operator) تبقي الطلب في قائمة انتظار اعتماد المدير.
     * شريك تنفيذ المنصة يعمل كمزوّد خدمة — يُسمح بالتنفيذ دون طابور اعتماد مدير حتى لو كان نوع النشاط retail/fleet_operator.
     */
    private function resolveInitialWorkOrderStatus(int $companyId): WorkOrderStatus
    {
        if (TenantBusinessFeatures::isPlatformExecutionPartnerTenant($companyId)) {
            return WorkOrderStatus::Approved;
        }

        $company = Company::query()->find($companyId);
        $settings = $company && is_array($company->settings) ? $company->settings : [];
        $profile = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $businessType = (string) ($profile['business_type'] ?? 'service_center');

        return match ($businessType) {
            'retail', 'fleet_operator' => WorkOrderStatus::PendingManagerApproval,
            default => WorkOrderStatus::Approved,
        };
    }

    private function resolveCreatorOrgUnitId(int $userId, int $companyId): int
    {
        return (int) (User::query()
            ->where('company_id', $companyId)
            ->whereKey($userId)
            ->value('org_unit_id') ?? 0);
    }

    private function resolveSectorPrefix(int $companyId, int $orgUnitId): string
    {
        $current = OrgUnit::query()->where('company_id', $companyId)->whereKey($orgUnitId)->first();
        $candidate = $current;
        $guard = 0;
        while ($current !== null && $guard < 16) {
            if ($current->type === OrgUnit::TYPE_SECTOR) {
                $candidate = $current;
                break;
            }
            $parentId = (int) ($current->parent_id ?? 0);
            if ($parentId <= 0) {
                break;
            }
            $current = OrgUnit::query()->where('company_id', $companyId)->whereKey($parentId)->first();
            if ($current !== null) {
                $candidate = $current;
            }
            $guard++;
        }

        if ($candidate === null) {
            return 'ORG'.$orgUnitId;
        }

        $raw = (string) ($candidate->code ?: $candidate->name ?: 'ORG'.$candidate->id);
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', Str::ascii($raw)));

        return $normalized !== '' ? substr($normalized, 0, 8) : ('ORG'.$candidate->id);
    }

    /**
     * Transition work order status with optimistic locking.
     *
     * @throws \DomainException  when transition is not allowed
     * @throws \RuntimeException when optimistic lock conflict is detected
     */
    public function transition(WorkOrder $order, WorkOrderStatus $newStatus, array $meta = []): WorkOrder
    {
        $fromStatusValue = $order->status->value;

        $fresh = DB::transaction(function () use ($order, $newStatus, $meta) {
            $fresh = WorkOrder::where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->version !== $order->version) {
                throw new \RuntimeException(
                    "Work order #{$order->id} was modified by another request (version conflict). Please reload and try again."
                );
            }

            if ($newStatus === WorkOrderStatus::Approved) {
                $lineCount = WorkOrderItem::query()->where('work_order_id', $fresh->id)->count();
                if ($lineCount < 1) {
                    throw new \DomainException('لا يمكن اعتماد أمر عمل بلا بنود خدمة أو منتج.');
                }
            }

            if (! $fresh->canTransitionTo($newStatus)) {
                $from = $fresh->status->value;
                $to   = $newStatus->value;

                Log::warning('work_order.invalid_transition', [
                    'work_order_id' => $fresh->id,
                    'from_status'   => $from,
                    'to_status'     => $to,
                    'trace_id'      => $this->resolveTraceId(),
                ]);

                throw new \DomainException(
                    "Work order status transition {$from} -> {$to} is not allowed."
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
                $nextMileageOut = array_key_exists('mileage_out', $meta)
                    ? (int) $meta['mileage_out']
                    : ($fresh->mileage_out !== null ? (int) $fresh->mileage_out : null);
                $afterImages = is_array($fresh->after_service_images) ? $fresh->after_service_images : [];

                if ($nextMileageOut === null || $nextMileageOut < 0) {
                    throw new \DomainException('لا يمكن إكمال أمر العمل بدون العداد النهائي (mileage_out).');
                }
                if (count($afterImages) < 1) {
                    throw new \DomainException('لا يمكن إكمال أمر العمل بدون صورة واحدة على الأقل بعد الخدمة.');
                }

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
                'trace_id'      => $this->resolveTraceId(),
            ]);

            $refreshed = $fresh->refresh();

            if ($newStatus === WorkOrderStatus::Approved) {
                $actor = auth()->id() ? (int) auth()->id() : (int) $refreshed->created_by_user_id;
                $this->approvedCreditBridge->onApproved($refreshed, $actor);
            }

            return $refreshed;
        });

        $this->intelligentEvents->emit(new WorkOrderStatusChanged(
            companyId: (int) $fresh->company_id,
            branchId: $fresh->branch_id ? (int) $fresh->branch_id : null,
            causedByUserId: auth()->id() ? (int) auth()->id() : null,
            workOrderId: $fresh->id,
            fromStatus: $fromStatusValue,
            toStatus: $newStatus->value,
            sourceContext: 'WorkOrderService::transition',
        ));

        if ($newStatus === WorkOrderStatus::Delivered) {
            if (! config('whatsapp_work_order_notifications.enabled')) {
                Log::info('whatsapp.work_order.dispatch_skipped_feature_disabled', [
                    'work_order_id' => $fresh->id,
                    'company_id'    => (int) $fresh->company_id,
                    'kind'          => 'delivered',
                ]);
            } else {
                Bus::dispatch(new NotifyCustomerWorkOrderWhatsAppJob(
                    workOrderId: $fresh->id,
                    companyId: (int) $fresh->company_id,
                    kind: 'delivered',
                ));
            }
        }

        if ($newStatus === WorkOrderStatus::Completed) {
            if (! config('whatsapp_work_order_notifications.enabled')) {
                Log::info('whatsapp.work_order.dispatch_skipped_feature_disabled', [
                    'work_order_id' => $fresh->id,
                    'company_id'    => (int) $fresh->company_id,
                    'kind'          => 'completed',
                ]);
            } else {
                Bus::dispatch(new NotifyCustomerWorkOrderWhatsAppJob(
                    workOrderId: $fresh->id,
                    companyId: (int) $fresh->company_id,
                    kind: 'completed',
                ));
            }
        }

        return $fresh;
    }

    public function update(WorkOrder $order, array $data): WorkOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $fresh = WorkOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $fleetOrigin = (($data['created_by_side'] ?? '') === 'fleet');

            if ($fresh->version !== $order->version) {
                throw new \RuntimeException(
                    "Work order #{$order->id} was modified concurrently. Please reload and try again."
                );
            }

            if (! $this->isDirectlyEditable($fresh->status)) {
                throw new \DomainException('لا يمكن تعديل أمر العمل بعد اعتماده أو أثناء مراجعة الإلغاء.');
            }

            $allowed = ['assigned_technician_id', 'priority', 'customer_complaint',
                        'diagnosis', 'technician_notes', 'mileage_in', 'mileage_out',
                        'odometer_reading', 'driver_name', 'driver_phone', 'notes',
                        'estimated_total'];

            $payload = array_intersect_key($data, array_flip($allowed));
            $payload['version'] = $fresh->version + 1;

            $fresh->update($payload);

            if (isset($data['items'])) {
                $this->replaceItems($fresh, $data['items'], $fleetOrigin);
            }

            return $fresh->refresh();
        });
    }

    /**
     * System-only transitions that bypass {@see WorkOrder::canTransitionTo()} (cancellation workflow).
     */
    public function applySystemStatusChange(WorkOrder $order, WorkOrderStatus $newStatus, array $meta = []): WorkOrder
    {
        return DB::transaction(function () use ($order, $newStatus, $meta) {
            $fresh = WorkOrder::withoutGlobalScopes()
                ->where('id', $order->id)
                ->where('company_id', $order->company_id)
                ->lockForUpdate()
                ->firstOrFail();

            $update = array_merge([
                'status' => $newStatus,
                'version' => $fresh->version + 1,
            ], $meta);

            $fresh->update($update);

            Log::info('work_order.system_status_change', [
                'work_order_id' => $fresh->id,
                'to_status' => $newStatus->value,
                'trace_id' => $this->resolveTraceId(),
            ]);

            return $fresh->refresh();
        });
    }

    private function isDirectlyEditable(WorkOrderStatus $status): bool
    {
        return in_array($status, [
            WorkOrderStatus::Draft,
            WorkOrderStatus::PendingManagerApproval,
        ], true);
    }

    private function replaceItems(WorkOrder $order, array $items, bool $fleetOrigin = false): void
    {
        if ($items === []) {
            throw new \DomainException('لا يمكن حذف جميع البنود — يجب الإبقاء على بند خدمة أو منتج واحد على الأقل.');
        }

        WorkOrderItem::where('work_order_id', $order->id)->delete();

        [$rows, $total] = $this->compileWorkOrderLineItems(
            $items,
            (int) $order->company_id,
            (int) $order->customer_id,
            $order->branch_id !== null ? (int) $order->branch_id : null,
            $fleetOrigin,
            $order->vehicle_id !== null ? (int) $order->vehicle_id : null,
        );

        foreach ($rows as $row) {
            WorkOrderItem::create(array_merge($row, ['work_order_id' => $order->id]));
        }

        $order->update(['estimated_total' => $total]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsIn
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function compileWorkOrderLineItems(
        array $itemsIn,
        int $companyId,
        int $customerId,
        ?int $branchId,
        bool $fleetOrigin,
        ?int $vehicleId = null,
    ): array {
        $itemsData      = [];
        $estimatedTotal = 0;

        foreach ($itemsIn as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            if ($quantity <= 0) {
                throw new \DomainException('كمية البند غير صالحة.');
            }

            if ($fleetOrigin && empty($item['service_id'])) {
                throw new \DomainException(
                    'يجب اختيار الخدمة المعتمدة من القائمة — لا يُقبل إرسال الطلب دون خدمة محدّدة، ولا يُعتمد أي سعر يدوي من بوابة العميل.'
                );
            }

            $resolved = null;
            $unitPrice = 0.0;
            $taxRate = 15.0;
            $lineName = (string) ($item['name'] ?? '');
            $serviceId = isset($item['service_id']) ? (int) $item['service_id'] : null;

            if ($serviceId !== null) {
                $resolved = $this->pricingResolver->resolve(
                    $companyId,
                    $customerId,
                    $serviceId,
                    $branchId,
                    null,
                    $vehicleId,
                    $fleetOrigin,
                    $quantity,
                );
                $unitPrice = $resolved->unitPrice;
                $taxRate = $resolved->taxRate;
                $service = \App\Models\Service::query()
                    ->where('company_id', $companyId)
                    ->where('id', $serviceId)
                    ->firstOrFail();
                $lineName = $service->name_ar ?: $service->name;
            } else {
                if ($fleetOrigin) {
                    throw new \DomainException(
                        'يجب اختيار الخدمة المعتمدة من القائمة — لا يُقبل إنشاء الطلب بلا خدمة محدّدة.'
                    );
                }
                $unitPrice = (float) $item['unit_price'];
                $taxRate = (float) ($item['tax_rate'] ?? 15);
                if ($lineName === '') {
                    throw new \DomainException('اسم البند مطلوب عند عدم ربط الخدمة من الكتالوج.');
                }
            }

            $lineSubtotal = $quantity * $unitPrice;
            $lineTax      = $lineSubtotal * ($taxRate / 100);
            $lineTotal    = $lineSubtotal + $lineTax;
            $estimatedTotal += $lineTotal;

            $row = [
                'company_id'      => $companyId,
                'item_type'       => $item['item_type'],
                'product_id'      => $item['product_id'] ?? null,
                'service_id'      => $serviceId,
                'name'            => $lineName,
                'sku'             => $item['sku'] ?? null,
                'quantity'        => $quantity,
                'unit_price'      => $unitPrice,
                'discount_amount' => $item['discount_amount'] ?? 0,
                'tax_rate'        => $taxRate,
                'tax_amount'      => $lineTax,
                'subtotal'        => $lineSubtotal,
                'total'           => $lineTotal,
                'pricing_resolved_by_system' => $serviceId !== null,
                'pricing_resolved_at'        => $serviceId !== null ? now() : null,
                'pricing_source'             => null,
                'pricing_policy_id'          => null,
                'pricing_contract_service_item_id' => null,
                'pricing_notes'              => null,
            ];

            if ($resolved instanceof ResolvedWorkOrderLinePrice) {
                $row['pricing_source'] = $resolved->source->value;
                $row['pricing_policy_id'] = $resolved->policyId;
                $row['pricing_contract_service_item_id'] = $resolved->contractServiceItemId;
                $row['pricing_notes'] = $resolved->notes;
            }

            $itemsData[] = $row;
        }

        return [$itemsData, $estimatedTotal];
    }
}
