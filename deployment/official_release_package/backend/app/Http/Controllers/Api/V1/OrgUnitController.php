<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrgUnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = OrgUnit::query()
            ->when($request->parent_id === '' || $request->parent_id === 'null', fn ($b) => $b->whereNull('parent_id'))
            ->when($request->filled('parent_id') && $request->parent_id !== 'null', fn ($b) => $b->where('parent_id', (int) $request->parent_id))
            ->when($request->type, fn ($b) => $b->where('type', $request->type))
            ->when($request->boolean('active_only', true), fn ($b) => $b->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name');

        $data = $request->boolean('paginate')
            ? $q->paginate($request->integer('per_page', 50))
            : $q->get();

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    /**
     * شجرة كاملة (جذور مع children متداخلة بعمق واحد للطبقة التالية فقط — للعرض يُفضّل استدعاء متكرر من الواجهة أو التوسعة لاحقاً).
     */
    public function tree(Request $request): JsonResponse
    {
        $roots = OrgUnit::query()
            ->whereNull('parent_id')
            ->when($request->boolean('active_only', true), fn ($b) => $b->where('is_active', true))
            ->with(['children' => fn ($q) => $q->when($request->boolean('active_only', true), fn ($b) => $b->where('is_active', true))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->with(['children' => fn ($q2) => $q2->when($request->boolean('active_only', true), fn ($b) => $b->where('is_active', true))
                    ->orderBy('sort_order')
                    ->orderBy('name'),
                ]),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roots, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $data = $request->validate([
            'parent_id'   => [
                'nullable',
                'integer',
                Rule::exists('org_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'type'        => ['required', 'string', 'in:sector,department,division'],
            'name'        => ['required', 'string', 'max:255'],
            'name_ar'     => ['nullable', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:64'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $row = OrgUnit::create(array_merge($data, [
            'uuid'        => Str::uuid()->toString(),
            'company_id'  => $companyId,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => $data['is_active'] ?? true,
        ]));

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $unit = OrgUnit::findOrFail($id);
        $companyId = (int) $request->user()->company_id;

        $data = $request->validate([
            'parent_id'   => [
                'nullable',
                'integer',
                Rule::exists('org_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'type'        => ['sometimes', 'string', 'in:sector,department,division'],
            'name'        => ['sometimes', 'string', 'max:255'],
            'name_ar'     => ['nullable', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:64'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('parent_id', $data)) {
            $newParent = $data['parent_id'];
            if ($newParent !== null && (int) $newParent === $unit->id) {
                return response()->json(['message' => 'Cannot set parent to self.', 'trace_id' => app('trace_id')], 422);
            }
            if ($newParent !== null && $this->wouldCreateCycle($unit->id, (int) $newParent)) {
                return response()->json(['message' => 'Invalid parent: would create a cycle.', 'trace_id' => app('trace_id')], 422);
            }
        }

        $unit->update($data);

        return response()->json(['data' => $unit->fresh(), 'trace_id' => app('trace_id')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $unit = OrgUnit::findOrFail($id);

        if ($unit->children()->exists()) {
            return response()->json(['message' => 'Cannot delete org unit with child units.', 'trace_id' => app('trace_id')], 422);
        }

        if ($unit->users()->exists()) {
            return response()->json(['message' => 'Cannot delete org unit assigned to users. Clear org_unit_id first.', 'trace_id' => app('trace_id')], 422);
        }

        $unit->delete();

        return response()->json(['message' => 'Deleted.', 'trace_id' => app('trace_id')]);
    }

    private function wouldCreateCycle(int $unitId, int $newParentId): bool
    {
        $current = OrgUnit::find($newParentId);
        $guard   = 0;
        while ($current !== null && $guard < 32) {
            if ((int) $current->id === $unitId) {
                return true;
            }
            $current = $current->parent_id ? OrgUnit::find($current->parent_id) : null;
            $guard++;
        }

        return false;
    }
}
