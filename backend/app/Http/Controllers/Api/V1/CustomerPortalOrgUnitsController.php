<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CustomerPortalOrgUnitsController extends Controller
{
    public function tree(Request $request): JsonResponse
    {
        $companyId = $this->assertCustomerAndGetCompanyId($request);
        $activeOnly = $request->boolean('active_only', false);

        $units = OrgUnit::query()
            ->where('company_id', $companyId)
            ->when($activeOnly, static fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'type', 'name', 'name_ar', 'code', 'is_active']);

        $grouped = [];
        foreach ($units as $unit) {
            $parentKey = $unit->parent_id === null ? 'root' : (string) $unit->parent_id;
            if (! isset($grouped[$parentKey])) {
                $grouped[$parentKey] = [];
            }
            $grouped[$parentKey][] = $unit;
        }

        $build = function (?int $parentId) use (&$build, $grouped): array {
            $key = $parentId === null ? 'root' : (string) $parentId;
            $rows = $grouped[$key] ?? [];
            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id' => $row->id,
                    'parent_id' => $row->parent_id,
                    'type' => $row->type,
                    'name' => $row->name,
                    'name_ar' => $row->name_ar,
                    'code' => $row->code,
                    'is_active' => (bool) $row->is_active,
                    'children' => $build((int) $row->id),
                ];
            }

            return $out;
        };

        return response()->json([
            'data' => $build(null),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->assertCustomerAndGetCompanyId($request);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'type' => ['required', 'string', Rule::in(OrgUnit::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $row = OrgUnit::create(array_merge($data, [
            'uuid' => Str::uuid()->toString(),
            'company_id' => $companyId,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]));

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->assertCustomerAndGetCompanyId($request);
        $unit = OrgUnit::query()->where('company_id', $companyId)->findOrFail($id);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'type' => ['sometimes', 'string', Rule::in(OrgUnit::TYPES)],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('parent_id', $data)) {
            $newParent = $data['parent_id'];
            if ($newParent !== null && (int) $newParent === $unit->id) {
                return response()->json(['message' => 'Cannot set parent to self.', 'trace_id' => app('trace_id')], 422);
            }
            if ($newParent !== null && $this->wouldCreateCycle((int) $unit->id, (int) $newParent, $companyId)) {
                return response()->json(['message' => 'Invalid parent: would create a cycle.', 'trace_id' => app('trace_id')], 422);
            }
        }

        $unit->update($data);

        return response()->json(['data' => $unit->fresh(), 'trace_id' => app('trace_id')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->assertCustomerAndGetCompanyId($request);
        $unit = OrgUnit::query()->where('company_id', $companyId)->findOrFail($id);

        if ($unit->children()->exists()) {
            return response()->json(['message' => 'Cannot delete org unit with child units.', 'trace_id' => app('trace_id')], 422);
        }
        if ($unit->users()->exists()) {
            return response()->json(['message' => 'Cannot delete org unit assigned to users. Clear org_unit_id first.', 'trace_id' => app('trace_id')], 422);
        }

        $unit->delete();

        return response()->json(['message' => 'Deleted.', 'trace_id' => app('trace_id')]);
    }

    private function assertCustomerAndGetCompanyId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->role->isCustomer()) {
            abort(403, 'هذه الخاصية متاحة لحسابات العملاء فقط.');
        }

        return (int) $user->company_id;
    }

    private function wouldCreateCycle(int $unitId, int $newParentId, int $companyId): bool
    {
        $current = OrgUnit::query()->where('company_id', $companyId)->find($newParentId);
        $guard = 0;
        while ($current !== null && $guard < 32) {
            if ((int) $current->id === $unitId) {
                return true;
            }
            $parentId = (int) ($current->parent_id ?? 0);
            $current = $parentId > 0 ? OrgUnit::query()->where('company_id', $companyId)->find($parentId) : null;
            $guard++;
        }

        return false;
    }
}
