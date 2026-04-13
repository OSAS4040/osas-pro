<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServicePricingPolicyType;
use App\Http\Controllers\Controller;
use App\Models\ServicePricingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServicePricingPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = ServicePricingPolicy::query()
            ->where('company_id', $user->company_id)
            ->with(['service:id,name,name_ar', 'customer:id,name', 'customerGroup:id,name', 'contract:id,title'])
            ->when($request->service_id, fn ($b) => $b->where('service_id', (int) $request->service_id))
            ->when($request->status, fn ($b) => $b->where('status', $request->status))
            ->orderByDesc('priority')
            ->orderByDesc('id');

        return response()->json(['data' => $q->paginate($request->per_page ?? 50), 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $this->validatedPayload($request, false);
        $data['company_id'] = $user->company_id;
        $policy = ServicePricingPolicy::create($data);

        return response()->json(['data' => $policy, 'trace_id' => app('trace_id')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $policy = ServicePricingPolicy::where('company_id', $user->company_id)->findOrFail($id);
        $policy->update($this->validatedPayload($request, true));

        return response()->json(['data' => $policy->fresh(), 'trace_id' => app('trace_id')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        ServicePricingPolicy::where('company_id', $user->company_id)->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted.', 'trace_id' => app('trace_id')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $isUpdate): array
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $types = array_map(fn ($c) => $c->value, ServicePricingPolicyType::cases());
        $req = $isUpdate ? 'sometimes' : 'required';

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'policy_type' => [$req, Rule::in($types)],
            'service_id' => [$req, 'integer', Rule::exists('services', 'id')->where('company_id', $companyId)],
            'unit_price' => [$req, 'numeric', 'min:0.0001'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => [$req, Rule::in(['active', 'inactive'])],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('company_id', $companyId)],
            'contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')->where('company_id', $companyId)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! isset($data['policy_type'])) {
            return $data;
        }

        $type = ServicePricingPolicyType::from($data['policy_type']);
        match ($type) {
            ServicePricingPolicyType::CustomerSpecific => empty($data['customer_id'])
                ? throw \Illuminate\Validation\ValidationException::withMessages(['customer_id' => 'مطلوب لسياسة عميل محدد.'])
                : null,
            ServicePricingPolicyType::CustomerGroup => empty($data['customer_group_id'])
                ? throw \Illuminate\Validation\ValidationException::withMessages(['customer_group_id' => 'مطلوب لسياسة مجموعة.'])
                : null,
            ServicePricingPolicyType::Contract => empty($data['contract_id'])
                ? throw \Illuminate\Validation\ValidationException::withMessages(['contract_id' => 'مطلوب لسياسة عقد.'])
                : null,
            ServicePricingPolicyType::General => null,
        };

        if ($type === ServicePricingPolicyType::General) {
            $data['customer_id'] = null;
            $data['customer_group_id'] = null;
            $data['contract_id'] = null;
        }
        if ($type === ServicePricingPolicyType::CustomerSpecific) {
            $data['customer_group_id'] = null;
            $data['contract_id'] = null;
        }
        if ($type === ServicePricingPolicyType::CustomerGroup) {
            $data['customer_id'] = null;
            $data['contract_id'] = null;
        }
        if ($type === ServicePricingPolicyType::Contract) {
            $data['customer_id'] = null;
            $data['customer_group_id'] = null;
        }

        return $data;
    }
}
