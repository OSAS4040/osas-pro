<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = CustomerGroup::where('company_id', $user->company_id)
            ->orderBy('name')
            ->paginate($request->per_page ?? 50);

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['company_id'] = $user->company_id;
        $group = CustomerGroup::create($data);

        return response()->json(['data' => $group, 'trace_id' => app('trace_id')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $group = CustomerGroup::where('company_id', $user->company_id)->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
        $group->update($data);

        return response()->json(['data' => $group->fresh(), 'trace_id' => app('trace_id')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        CustomerGroup::where('company_id', $user->company_id)->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted.', 'trace_id' => app('trace_id')]);
    }
}
