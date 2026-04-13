<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Suppliers", description="Supplier management")
 */
class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::when($request->search, fn($q) => $q->where('name', 'ilike', "%{$request->search}%"))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $suppliers, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'name_ar'       => 'nullable|string|max:255',
            'email'         => 'nullable|email',
            'phone'         => 'nullable|string|max:20',
            'tax_number'    => 'nullable|string|max:50',
            'cr_number'     => 'nullable|string|max:50',
            'address'       => 'nullable|string',
            'city'          => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'credit_limit'  => 'nullable|numeric|min:0',
        ]);

        $user     = $request->user();
        $supplier = Supplier::create(array_merge($data, [
            'uuid'                => Str::uuid(),
            'company_id'          => $user->company_id,
            'created_by_user_id'  => $user->id,
        ]));

        return response()->json(['data' => $supplier, 'trace_id' => app('trace_id')], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => Supplier::findOrFail($id), 'trace_id' => app('trace_id')]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => 'nullable|email',
            'phone'         => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string',
            'credit_limit'  => 'nullable|numeric|min:0',
            'is_active'     => 'nullable|boolean',
        ]);

        $supplier->update($data);
        $supplier->increment('version');

        return response()->json(['data' => $supplier, 'trace_id' => app('trace_id')]);
    }

    public function destroy(int $id): JsonResponse
    {
        Supplier::findOrFail($id)->delete();

        return response()->json(['message' => 'Supplier deleted.', 'trace_id' => app('trace_id')]);
    }
}
