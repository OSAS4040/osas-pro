<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Enums\AccountType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('code', 'like', "%{$request->search}%")
                   ->orWhere('name', 'like', "%{$request->search}%");
            }))
            ->orderBy('code')
            ->with('parent')
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'data'     => $accounts,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $account = ChartOfAccount::where('company_id', $request->user()->company_id)
            ->with(['parent', 'children'])
            ->findOrFail($id);

        return response()->json(['data' => $account, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20',
            'name'        => 'required|string|max:255',
            'name_ar'     => 'nullable|string|max:255',
            'type'        => 'required|in:asset,liability,equity,revenue,expense',
            'sub_type'    => 'nullable|string|max:50',
            'parent_id'   => 'nullable|integer|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
        ]);

        $companyId = $request->user()->company_id;

        $exists = ChartOfAccount::where('company_id', $companyId)
            ->where('code', $data['code'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => "Account code '{$data['code']}' already exists."], 422);
        }

        $account = ChartOfAccount::create(array_merge($data, [
            'company_id' => $companyId,
            'is_system'  => false,
        ]));

        return response()->json(['data' => $account, 'trace_id' => app('trace_id')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $account = ChartOfAccount::where('company_id', $request->user()->company_id)->findOrFail($id);

        if ($account->is_system && $request->filled('code')) {
            return response()->json(['message' => 'System account code cannot be changed via API.'], 422);
        }
        if ($account->is_system && $request->filled('type')) {
            return response()->json(['message' => 'System account type cannot be changed via API.'], 422);
        }

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'name_ar'     => 'nullable|string|max:255',
            'sub_type'    => 'nullable|string|max:50',
            'parent_id'   => 'nullable|integer|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'sometimes|boolean',
        ]);

        $account->update($data);

        return response()->json(['data' => $account, 'trace_id' => app('trace_id')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $account = ChartOfAccount::where('company_id', $request->user()->company_id)->findOrFail($id);

        if ($account->is_system) {
            return response()->json(['message' => 'System accounts cannot be deleted.'], 422);
        }

        if ($account->journalLines()->exists()) {
            return response()->json(['message' => 'Cannot delete account with existing journal entries.'], 422);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted.', 'trace_id' => app('trace_id')]);
    }
}
