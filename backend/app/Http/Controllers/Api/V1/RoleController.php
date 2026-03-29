<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Roles", description="Company-scoped role management")
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/roles",
     *     tags={"Roles"},
     *     summary="List all roles (system + company-scoped)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $roles = Role::with('permissions')
            ->withoutGlobalScope('tenant')
            ->where(fn($q) => $q
                ->whereNull('company_id')
                ->orWhere('company_id', $companyId)
            )
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $roles, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles",
     *     tags={"Roles"},
     *     summary="Create a custom company role",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Senior Technician"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'company_id'  => $request->user()->company_id,
            'name'        => $data['name'],
            'guard_name'  => 'sanctum',
            'description' => $data['description'] ?? null,
            'is_system'   => false,
        ]);

        if (! empty($data['permissions'])) {
            $permIds = Permission::whereIn('name', $data['permissions'])->pluck('id');
            $role->permissions()->sync($permIds);
        }

        return response()->json([
            'data'     => $role->load('permissions'),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}",
     *     tags={"Roles"},
     *     summary="Get a role with its permissions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $companyId = request()->user()->company_id;

        $role = Role::with('permissions')
            ->withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where(fn($q) => $q
                ->whereNull('company_id')
                ->orWhere('company_id', $companyId)
            )
            ->firstOrFail();

        return response()->json(['data' => $role, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{id}",
     *     tags={"Roles"},
     *     summary="Update a custom role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $role = Role::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('is_system', false)
            ->firstOrFail();

        $data = $request->validated();
        $role->update($data);

        if (array_key_exists('permissions', $data)) {
            $permIds = Permission::whereIn('name', $data['permissions'] ?? [])->pluck('id');
            $role->permissions()->sync($permIds);
        }

        return response()->json(['data' => $role->load('permissions'), 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/roles/{id}",
     *     tags={"Roles"},
     *     summary="Delete a custom role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $role = Role::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('is_system', false)
            ->firstOrFail();

        $role->delete();

        return response()->json(['message' => 'Role deleted.', 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles/{id}/assign",
     *     tags={"Roles"},
     *     summary="Assign a role to a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"user_id"}, @OA\Property(property="user_id", type="integer"))),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $companyId = $request->user()->company_id;
        $targetUser = \App\Models\User::where('id', $request->user_id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $role = Role::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->firstOrFail();

        $targetUser->assignRole($role);

        return response()->json(['message' => 'Role assigned.', 'trace_id' => app('trace_id')]);
    }
}
