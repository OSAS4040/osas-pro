<?php

namespace App\Services;

use App\Enums\ServicePricingPolicyType;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkOrderPricingSource;
use App\Models\Contract;
use App\Models\ContractServiceItem;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\ServicePricingPolicy;
use App\Models\Subscription;
use App\Models\WorkOrderItem;
use App\Services\WorkOrderPricing\ResolvedWorkOrderLinePrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * نقطة الحسم الوحيدة لتسعير بنود أمر العمل المرتبطة بخدمة (service_id).
 *
 * الأولوية الرسمية بعد اعتماد كتالوج العقد:
 * بند عقد (contract_service_items) عند ارتباط العميل بعقد فعّال ومطابقة نطاق الفرع/المركبة
 * ← ثم سياسات التسعير: عميل محدد → مجموعة → عقد (service_pricing_policies) → عام
 * ← ثم سعر الخدمة الأساسي (إن وُجدت سياسة عامة أو تسعير متقدّم مفعّل أو مسار غير أسطول)
 *
 * بوابة الأسطول: إذا كان للعميل عقد فعّال مربوط (pricing_contract_id) فلا يُقبل إلا خدمات مدرجة في بنود العقد
 * ضمن نطاق الفرع/المركبة — وإلا يُرفض الطلب صراحة.
 */
class WorkOrderPricingResolverService
{
    public const NO_PRICE_MESSAGE = 'لا يوجد سعر معتمد لهذه الخدمة ضمن سياسة التسعير الحالية. يرجى التواصل مع الشركة.';

    public const FLEET_CONTRACT_SCOPE_MESSAGE = 'هذه الخدمة غير مشمولة في عقدكم الحالي أو خارج نطاق المركبة/الفرع المعتمد لبنود التعاقد.';

    public function resolve(
        int $companyId,
        int $customerId,
        int $serviceId,
        ?int $branchId,
        ?Carbon $onDate = null,
        ?int $vehicleId = null,
        bool $fleetOrigin = false,
        float $requestedQuantity = 1.0,
    ): ResolvedWorkOrderLinePrice {
        $on = $onDate ?? Carbon::now();
        $onDateStr = $on->toDateString();

        $service = \App\Models\Service::query()
            ->where('company_id', $companyId)
            ->where('id', $serviceId)
            ->where('is_active', true)
            ->first();

        if ($service === null) {
            throw new \DomainException(
                'لا تتوفر خدمة نشطة بهذا المعرف ضمن شركتك.'
            );
        }

        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->where('id', $customerId)
            ->firstOrFail();

        $advanced = $this->advancedPricingEnabled($companyId);
        $resolved = null;

        if ($customer->pricing_contract_id) {
            $contract = Contract::query()
                ->where('company_id', $companyId)
                ->where('id', $customer->pricing_contract_id)
                ->first();
            if ($contract !== null && $this->contractIsEffective($contract, $onDateStr)) {
                $line = $this->pickContractServiceLine($companyId, $contract->id, $serviceId, $branchId, $vehicleId);
                if ($line !== null) {
                    $this->assertContractLineCapacity($line, $customerId, $requestedQuantity);
                    $tax = $line->tax_rate !== null
                        ? (float) $line->tax_rate
                        : (float) $service->tax_rate;
                    $unit = (float) $line->unit_price - (float) $line->discount_amount;
                    if ($unit < 0) {
                        $unit = 0.0;
                    }
                    $resolved = new ResolvedWorkOrderLinePrice(
                        unitPrice: $unit,
                        taxRate: $tax,
                        source: WorkOrderPricingSource::Contract,
                        policyId: null,
                        resolutionLevel: 'contract_service_item',
                        notes: $line->notes,
                        contractServiceItemId: (int) $line->id,
                    );
                } elseif ($fleetOrigin) {
                    throw new \DomainException(self::FLEET_CONTRACT_SCOPE_MESSAGE);
                }
            }
        }

        if ($resolved === null && $advanced) {
            $policy = $this->resolvePolicyRow($companyId, $customer, $serviceId, $branchId, $onDateStr);
            if ($policy !== null) {
                $tax = $policy->tax_rate !== null
                    ? (float) $policy->tax_rate
                    : (float) $service->tax_rate;

                $resolved = new ResolvedWorkOrderLinePrice(
                    unitPrice: (float) $policy->unit_price,
                    taxRate: $tax,
                    source: $this->mapPolicyTypeToSource($policy->policy_type),
                    policyId: (int) $policy->id,
                    resolutionLevel: $policy->policy_type->value,
                    notes: $policy->notes,
                    contractServiceItemId: null,
                );
            }
        }

        if ($resolved === null) {
            $resolved = new ResolvedWorkOrderLinePrice(
                unitPrice: (float) $service->base_price,
                taxRate: (float) $service->tax_rate,
                source: WorkOrderPricingSource::GeneralServiceBase,
                policyId: null,
                resolutionLevel: 'general_service_base',
                notes: $advanced ? null : 'التسعير المتقدم غير مفعّل في الباقة — يُستخدم سعر الخدمة الأساسي.',
                contractServiceItemId: null,
            );
        }

        if ($resolved->unitPrice <= 0) {
            throw new \DomainException(self::NO_PRICE_MESSAGE);
        }

        return $resolved;
    }

    private function pickContractServiceLine(
        int $companyId,
        int $contractId,
        int $serviceId,
        ?int $branchId,
        ?int $vehicleId,
    ): ?ContractServiceItem {
        $candidates = ContractServiceItem::query()
            ->where('company_id', $companyId)
            ->where('contract_id', $contractId)
            ->where('service_id', $serviceId)
            ->where('status', 'active')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId !== null) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $line) {
            if ($this->contractLineMatchesVehicle($line, $vehicleId)) {
                return $line;
            }
        }

        return null;
    }

    private function contractLineMatchesVehicle(ContractServiceItem $line, ?int $vehicleId): bool
    {
        if ($line->applies_to_all_vehicles) {
            return true;
        }
        $ids = $line->vehicle_ids ?? [];
        if ($vehicleId === null) {
            return $ids === [];
        }

        return in_array($vehicleId, array_map('intval', $ids), true);
    }

    private function assertContractLineCapacity(ContractServiceItem $line, int $customerId, float $requestedQty): void
    {
        $cap = $line->max_total_quantity;
        if ($cap === null) {
            return;
        }
        $capVal = (float) $cap;
        if ($capVal <= 0) {
            return;
        }
        $used = (float) WorkOrderItem::query()
            ->where('company_id', $line->company_id)
            ->where('pricing_contract_service_item_id', $line->id)
            ->whereHas('workOrder', fn ($q) => $q->where('customer_id', $customerId))
            ->sum('quantity');

        if ($used + $requestedQty > $capVal + 0.0001) {
            throw new \DomainException(
                'تم بلوغ الحد الأقصى المسموح به لاستخدام هذه الخدمة ضمن بند العقد. يرجى التواصل مع الشركة.'
            );
        }
    }

    private function mapPolicyTypeToSource(ServicePricingPolicyType $t): WorkOrderPricingSource
    {
        return match ($t) {
            ServicePricingPolicyType::CustomerSpecific => WorkOrderPricingSource::CustomerSpecific,
            ServicePricingPolicyType::CustomerGroup => WorkOrderPricingSource::CustomerGroup,
            ServicePricingPolicyType::Contract => WorkOrderPricingSource::Contract,
            ServicePricingPolicyType::General => WorkOrderPricingSource::GeneralPolicy,
        };
    }

    private function advancedPricingEnabled(int $companyId): bool
    {
        $sub = Subscription::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::GracePeriod])
            ->orderByDesc('id')
            ->first();

        if ($sub === null) {
            return true;
        }

        $plan = Plan::findBySlug((string) $sub->plan);
        if ($plan === null) {
            return true;
        }

        return (bool) ($plan->features['work_order_advanced_pricing'] ?? false);
    }

    private function basePolicyQuery(int $companyId, int $serviceId, string $onDateStr, ?int $branchId): \Illuminate\Database\Eloquent\Builder
    {
        return ServicePricingPolicy::query()
            ->where('company_id', $companyId)
            ->where('service_id', $serviceId)
            ->where('status', 'active')
            ->where(function ($q) use ($onDateStr) {
                $q->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $onDateStr);
            })
            ->where(function ($q) use ($onDateStr) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $onDateStr);
            })
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId !== null) {
                    $q->orWhere('branch_id', $branchId);
                }
            });
    }

    private function resolvePolicyRow(
        int $companyId,
        Customer $customer,
        int $serviceId,
        ?int $branchId,
        string $onDateStr,
    ): ?ServicePricingPolicy {
        $picked = $this->pickWinningPolicy(
            $this->basePolicyQuery($companyId, $serviceId, $onDateStr, $branchId)
                ->where('policy_type', ServicePricingPolicyType::CustomerSpecific)
                ->where('customer_id', $customer->id)
                ->get()
        );
        if ($picked !== null) {
            return $picked;
        }

        if ($customer->customer_group_id) {
            $picked = $this->pickWinningPolicy(
                $this->basePolicyQuery($companyId, $serviceId, $onDateStr, $branchId)
                    ->where('policy_type', ServicePricingPolicyType::CustomerGroup)
                    ->where('customer_group_id', $customer->customer_group_id)
                    ->get()
            );
            if ($picked !== null) {
                return $picked;
            }
        }

        if ($customer->pricing_contract_id) {
            $contract = Contract::query()
                ->where('company_id', $companyId)
                ->where('id', $customer->pricing_contract_id)
                ->first();
            if ($contract !== null && $this->contractIsEffective($contract, $onDateStr)) {
                $picked = $this->pickWinningPolicy(
                    $this->basePolicyQuery($companyId, $serviceId, $onDateStr, $branchId)
                        ->where('policy_type', ServicePricingPolicyType::Contract)
                        ->where('contract_id', $contract->id)
                        ->get()
                );
                if ($picked !== null) {
                    return $picked;
                }
            }
        }

        return $this->pickWinningPolicy(
            $this->basePolicyQuery($companyId, $serviceId, $onDateStr, $branchId)
                ->where('policy_type', ServicePricingPolicyType::General)
                ->whereNull('customer_id')
                ->whereNull('customer_group_id')
                ->whereNull('contract_id')
                ->get()
        );
    }

    private function contractIsEffective(Contract $contract, string $onDateStr): bool
    {
        if (($contract->status ?? '') !== 'active') {
            return false;
        }

        return $contract->start_date->format('Y-m-d') <= $onDateStr
            && $contract->end_date->format('Y-m-d') >= $onDateStr;
    }

    /**
     * @param  Collection<int, ServicePricingPolicy>  $candidates
     */
    private function pickWinningPolicy(Collection $candidates): ?ServicePricingPolicy
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sort(function (ServicePricingPolicy $a, ServicePricingPolicy $b): int {
            if ((int) $a->priority !== (int) $b->priority) {
                return (int) $b->priority <=> (int) $a->priority;
            }
            $ad = $a->effective_from?->format('Y-m-d') ?? '1970-01-01';
            $bd = $b->effective_from?->format('Y-m-d') ?? '1970-01-01';

            return strcmp($bd, $ad);
        })->first();
    }
}
