<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractServiceItem;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\WorkOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ContractServiceItemController extends Controller
{
    public function index(Contract $contract): JsonResponse
    {
        $this->assertSameTenantContract($contract);

        $items = ContractServiceItem::query()
            ->where('contract_id', $contract->id)
            ->with('service:id,name,name_ar,code,is_active,description', 'branch:id,name,code')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $items, 'trace_id' => app('trace_id')]);
    }

    /**
     * معاينة انطباق بند (محفوظ أو مسودة) على عميل + مركبة + فرع + خدمة.
     */
    public function matchPreview(Request $request, Contract $contract): JsonResponse
    {
        $this->assertSameTenantContract($contract);
        $companyId = (int) app('tenant_company_id');

        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'service_item_id' => [
                'nullable',
                'integer',
                Rule::exists('contract_service_items', 'id')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->where('contract_id', $contract->id)),
            ],
            'draft' => 'nullable|array',
            'draft.branch_id' => 'nullable|integer',
            'draft.unit_price' => 'nullable|numeric',
            'draft.tax_rate' => 'nullable|numeric',
            'draft.discount_amount' => 'nullable|numeric',
            'draft.applies_to_all_vehicles' => 'boolean',
            'draft.vehicle_ids' => 'nullable|array',
            'draft.vehicle_ids.*' => 'integer',
        ]);

        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->where('id', $data['customer_id'])
            ->firstOrFail();

        if ((int) ($customer->pricing_contract_id ?? 0) !== (int) $contract->id) {
            return response()->json([
                'data' => [
                    'applies' => false,
                    'reason_ar' => 'العميل غير مربوط بهذا العقد في حقل «عقد التسعير» (pricing_contract_id).',
                ],
                'trace_id' => app('trace_id'),
            ]);
        }

        Vehicle::query()
            ->where('company_id', $companyId)
            ->where('id', $data['vehicle_id'])
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        $line = null;
        if (! empty($data['service_item_id'])) {
            $line = ContractServiceItem::query()
                ->where('company_id', $companyId)
                ->where('contract_id', $contract->id)
                ->where('id', (int) $data['service_item_id'])
                ->firstOrFail();
        } else {
            $d = $data['draft'] ?? [];
            if (! isset($d['unit_price'])) {
                throw ValidationException::withMessages(['draft.unit_price' => ['مطلوب لمعاينة مسودة البند.']]);
            }
            $appliesAll = (bool) ($d['applies_to_all_vehicles'] ?? true);
            $vids = $appliesAll ? null : ($d['vehicle_ids'] ?? null);
            $line = new ContractServiceItem([
                'company_id' => $companyId,
                'contract_id' => $contract->id,
                'service_id' => (int) $data['service_id'],
                'branch_id' => $d['branch_id'] ?? null,
                'unit_price' => (float) $d['unit_price'],
                'tax_rate' => isset($d['tax_rate']) ? (float) $d['tax_rate'] : null,
                'discount_amount' => (float) ($d['discount_amount'] ?? 0),
                'applies_to_all_vehicles' => $appliesAll,
                'vehicle_ids' => $vids,
                'status' => 'active',
            ]);
        }

        if ((int) $line->service_id !== (int) $data['service_id']) {
            return response()->json([
                'data' => [
                    'applies' => false,
                    'reason_ar' => 'الخدمة المختارة لا تطابق بند العقد.',
                ],
                'trace_id' => app('trace_id'),
            ]);
        }

        if (($line->status ?? 'active') !== 'active') {
            return response()->json([
                'data' => [
                    'applies' => false,
                    'reason_ar' => 'البند غير نشط.',
                ],
                'trace_id' => app('trace_id'),
            ]);
        }

        if ($line->branch_id !== null) {
            if ($branchId === null || (int) $line->branch_id !== $branchId) {
                return response()->json([
                    'data' => [
                        'applies' => false,
                        'reason_ar' => 'البند مقيّد بفرع محدد — اختر نفس فرع البند في اختبار الانطباق.',
                    ],
                    'trace_id' => app('trace_id'),
                ]);
            }
        }

        $vid = (int) $data['vehicle_id'];
        $vehicleOk = $line->applies_to_all_vehicles
            || in_array($vid, array_map('intval', $line->vehicle_ids ?? []), true);
        if (! $vehicleOk) {
            return response()->json([
                'data' => [
                    'applies' => false,
                    'reason_ar' => 'المركبة غير ضمن نطاق البند (مركبات محددة).',
                ],
                'trace_id' => app('trace_id'),
            ]);
        }

        $service = Service::query()
            ->where('company_id', $companyId)
            ->where('id', $data['service_id'])
            ->firstOrFail();

        $unit = max(0.0, (float) $line->unit_price - (float) $line->discount_amount);
        $tax = $line->tax_rate !== null ? (float) $line->tax_rate : (float) $service->tax_rate;

        return response()->json([
            'data' => [
                'applies' => true,
                'reason_ar' => 'ينطبق البند: تطابق العميل وعقد التسعير والخدمة والفرع (إن وُجد قيد) والمركبة.',
                'unit_price' => $unit,
                'tax_rate' => $tax,
                'pricing_source' => 'contract',
                'pricing_source_label_ar' => 'سعر عقد / اتفاقية (بند كتالوج تعاقدي)',
                'resolution_level' => 'contract_service_item',
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function itemUsage(Contract $contract, int $itemId): JsonResponse
    {
        $this->assertSameTenantContract($contract);
        $companyId = (int) app('tenant_company_id');

        ContractServiceItem::query()
            ->where('company_id', $companyId)
            ->where('contract_id', $contract->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $used = (float) WorkOrderItem::query()
            ->where('company_id', $companyId)
            ->where('pricing_contract_service_item_id', $itemId)
            ->sum('quantity');

        return response()->json([
            'data' => ['used_quantity' => $used],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function store(Request $request, Contract $contract): JsonResponse
    {
        $this->assertSameTenantContract($contract);
        $companyId = (int) app('tenant_company_id');

        $data = $request->validate([
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'unit_price' => 'required|numeric|min:0.0001',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'applies_to_all_vehicles' => 'boolean',
            'vehicle_ids' => 'nullable|array',
            'vehicle_ids.*' => 'integer',
            'max_total_quantity' => 'nullable|numeric|min:0.0001',
            'requires_internal_approval' => 'boolean',
            'status' => 'nullable|in:active,inactive',
            'priority' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);

        $this->normalizePricingAndVehicleScope($data);
        $this->assertNoConflictingDuplicate($contract, $data, null);

        $item = ContractServiceItem::create([
            'company_id' => $companyId,
            'contract_id' => $contract->id,
            'service_id' => (int) $data['service_id'],
            'unit_price' => $data['unit_price'],
            'tax_rate' => $data['tax_rate'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'branch_id' => $data['branch_id'] ?? null,
            'applies_to_all_vehicles' => $data['applies_to_all_vehicles'] ?? true,
            'vehicle_ids' => $data['vehicle_ids'] ?? null,
            'max_total_quantity' => $data['max_total_quantity'] ?? null,
            'requires_internal_approval' => $data['requires_internal_approval'] ?? false,
            'status' => $data['status'] ?? 'active',
            'priority' => $data['priority'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'data' => $item->load('service:id,name,name_ar,code,is_active,description', 'branch:id,name,code'),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function update(Request $request, Contract $contract, int $itemId): JsonResponse
    {
        $this->assertSameTenantContract($contract);
        $companyId = (int) app('tenant_company_id');

        $item = ContractServiceItem::query()
            ->where('company_id', $companyId)
            ->where('contract_id', $contract->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $data = $request->validate([
            'service_id' => [
                'sometimes',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'unit_price' => 'sometimes|numeric|min:0.0001',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'applies_to_all_vehicles' => 'boolean',
            'vehicle_ids' => 'nullable|array',
            'vehicle_ids.*' => 'integer',
            'max_total_quantity' => 'nullable|numeric|min:0.0001',
            'requires_internal_approval' => 'boolean',
            'status' => 'nullable|in:active,inactive',
            'priority' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);

        $row = [
            'service_id' => isset($data['service_id']) ? (int) $data['service_id'] : (int) $item->service_id,
            'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $item->branch_id,
            'unit_price' => isset($data['unit_price']) ? (float) $data['unit_price'] : (float) $item->unit_price,
            'discount_amount' => array_key_exists('discount_amount', $data) ? (float) $data['discount_amount'] : (float) $item->discount_amount,
            'applies_to_all_vehicles' => array_key_exists('applies_to_all_vehicles', $data)
                ? (bool) $data['applies_to_all_vehicles']
                : (bool) $item->applies_to_all_vehicles,
            'vehicle_ids' => array_key_exists('vehicle_ids', $data) ? $data['vehicle_ids'] : $item->vehicle_ids,
        ];
        $this->normalizePricingAndVehicleScope($row);
        $this->assertNoConflictingDuplicate($contract, $row, $item->id);

        if (array_key_exists('max_total_quantity', $data) && $data['max_total_quantity'] !== null) {
            $used = (float) WorkOrderItem::query()
                ->where('company_id', $companyId)
                ->where('pricing_contract_service_item_id', $item->id)
                ->sum('quantity');
            if ((float) $data['max_total_quantity'] < $used - 0.0001) {
                throw ValidationException::withMessages([
                    'max_total_quantity' => [
                        'الحد الأقصى أقل من الكمية المستهلكة فعليًا في أوامر العمل ('.round($used, 4).').',
                    ],
                ]);
            }
        }

        $item->service_id = $row['service_id'];
        $item->branch_id = $row['branch_id'];
        $item->unit_price = $row['unit_price'];
        $item->discount_amount = $row['discount_amount'];
        $item->applies_to_all_vehicles = $row['applies_to_all_vehicles'];
        $item->vehicle_ids = $row['vehicle_ids'];

        foreach (['tax_rate', 'max_total_quantity', 'requires_internal_approval', 'status', 'priority', 'notes'] as $k) {
            if (array_key_exists($k, $data)) {
                $item->{$k} = $data[$k];
            }
        }

        $item->save();

        return response()->json(['data' => $item->fresh()->load('service:id,name,name_ar,code,is_active,description', 'branch:id,name,code'), 'trace_id' => app('trace_id')]);
    }

    public function destroy(Contract $contract, int $itemId): JsonResponse
    {
        $this->assertSameTenantContract($contract);
        $companyId = (int) app('tenant_company_id');

        $item = ContractServiceItem::query()
            ->where('company_id', $companyId)
            ->where('contract_id', $contract->id)
            ->where('id', $itemId)
            ->firstOrFail();

        $used = (float) WorkOrderItem::query()
            ->where('company_id', $companyId)
            ->where('pricing_contract_service_item_id', $itemId)
            ->sum('quantity');

        if ($used > 0) {
            return response()->json([
                'message' => 'البند مرتبط بكميات في أوامر عمل سابقة. عطّل البند (غير نشط) بدل الحذف.',
                'code' => 'CONTRACT_LINE_IN_USE',
                'used_quantity' => $used,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        $item->delete();

        return response()->json(['message' => 'تم حذف بند العقد.', 'trace_id' => app('trace_id')]);
    }

    /**
     * @param  array<string, mixed>  $row  يجب أن يحتوي: unit_price, discount_amount, applies_to_all_vehicles, vehicle_ids
     */
    private function normalizePricingAndVehicleScope(array &$row): void
    {
        $appliesAll = (bool) ($row['applies_to_all_vehicles'] ?? true);
        if ($appliesAll) {
            $row['applies_to_all_vehicles'] = true;
            $row['vehicle_ids'] = null;
        } else {
            $ids = array_values(array_unique(array_filter(array_map('intval', $row['vehicle_ids'] ?? []))));
            if ($ids === []) {
                throw ValidationException::withMessages([
                    'vehicle_ids' => ['عند تقييد المركبات يجب تحديد مركبة واحدة على الأقل.'],
                ]);
            }
            $row['vehicle_ids'] = $ids;
        }

        $unit = (float) ($row['unit_price'] ?? 0);
        $disc = (float) ($row['discount_amount'] ?? 0);
        if ($disc > $unit + 0.0001) {
            throw ValidationException::withMessages([
                'discount_amount' => ['لا يجوز أن يتجاوز الخصم سعر الوحدة.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row  service_id, branch_id, applies_to_all_vehicles, vehicle_ids
     */
    private function assertNoConflictingDuplicate(Contract $contract, array $row, ?int $exceptId): void
    {
        $normV = static function (?array $ids, bool $all): string {
            if ($all) {
                return '*';
            }
            $ids = array_map('intval', $ids ?? []);
            sort($ids);

            return implode(',', $ids);
        };

        $want = $normV(
            isset($row['vehicle_ids']) && is_array($row['vehicle_ids']) ? $row['vehicle_ids'] : null,
            (bool) ($row['applies_to_all_vehicles'] ?? true),
        );

        $q = ContractServiceItem::query()
            ->where('contract_id', $contract->id)
            ->where('service_id', (int) $row['service_id']);

        $branchId = $row['branch_id'] ?? null;
        if ($branchId === null) {
            $q->whereNull('branch_id');
        } else {
            $q->where('branch_id', $branchId);
        }

        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }

        foreach ($q->get() as $ex) {
            $have = $normV($ex->vehicle_ids, (bool) $ex->applies_to_all_vehicles);
            if ($have === $want) {
                throw ValidationException::withMessages([
                    'service_id' => ['يوجد بند آخر بنفس الخدمة ونفس نطاق الفرع/المركبات — غيّر النطاق أو ادمج البنود لتفادي تضارب الأولوية.'],
                ]);
            }
        }
    }

    private function assertSameTenantContract(Contract $contract): void
    {
        if ((int) $contract->company_id !== (int) app('tenant_company_id')) {
            abort(404);
        }
    }
}
