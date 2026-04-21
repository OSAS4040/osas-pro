<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Intelligence\Events\CustomerCreated;
use App\Models\Customer;
use App\Services\IntelligentEventEmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Customers", description="Customer management")
 */
class CustomerController extends Controller
{
    public function __construct(
        private readonly IntelligentEventEmitter $intelligentEvents,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/customers",
     *     tags={"Customers"},
     *     summary="List customers",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::with('wallet')
            ->when($request->company_id, fn($q) => $q->where('company_id', (int) $request->company_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%")
                  ->orWhere('tax_number', $request->search);
            }))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $customers, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'        => 'required|in:b2c,b2b',
            'name'        => 'required|string|max:255',
            'name_ar'     => 'nullable|string|max:255',
            'email'       => 'nullable|email',
            'phone'       => 'nullable|string|max:20',
            'tax_number'  => 'nullable|string|max:50',
            'cr_number'   => 'nullable|string|max:50',
            'address'     => 'nullable|string',
            'city'        => 'nullable|string|max:100',
            'credit_limit'=> 'nullable|numeric|min:0',
        ]);

        $user     = $request->user();
        $customer = Customer::create(array_merge($data, [
            'uuid'       => Str::uuid(),
            'company_id' => $user->company_id,
            'branch_id'  => $user->branch_id,
        ]));

        $this->intelligentEvents->emit(new CustomerCreated(
            companyId: (int) $user->company_id,
            branchId: $user->branch_id ? (int) $user->branch_id : null,
            causedByUserId: (int) $user->id,
            customerId: $customer->id,
            customerUuid: (string) $customer->uuid,
            sourceContext: 'api.v1.customers.store',
        ));

        return response()->json(['data' => $customer, 'trace_id' => app('trace_id')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $customer = Customer::with(['wallet', 'vehicles'])->findOrFail($id);

        return response()->json(['data' => $customer, 'trace_id' => app('trace_id')]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $companyId = (int) $request->user()->company_id;

        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'name_ar'      => 'nullable|string',
            'email'        => 'nullable|email',
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string',
            'city'         => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active'    => 'nullable|boolean',
            'customer_group_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customer_groups', 'id')->where('company_id', $companyId)],
            'pricing_contract_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('contracts', 'id')->where('company_id', $companyId)],
            'customer_pricing_profile' => 'nullable|string|max:32|in:standard,contract,special_pricing,group_pricing,cash,credit',
        ]);

        $customer->update($data);
        $customer->increment('version');

        return response()->json(['data' => $customer, 'trace_id' => app('trace_id')]);
    }

    public function destroy(int $id): JsonResponse
    {
        Customer::findOrFail($id)->delete();

        return response()->json(['message' => 'Customer deleted.', 'trace_id' => app('trace_id')]);
    }
}
