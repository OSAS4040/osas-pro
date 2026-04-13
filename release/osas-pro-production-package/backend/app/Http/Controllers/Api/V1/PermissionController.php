<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Permissions", description="Permission catalogue")
 */
class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/permissions",
     *     tags={"Permissions"},
     *     summary="List all available permissions grouped by category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Permissions grouped by category",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object", description="Key = group, value = array of permission names"),
     *             @OA\Property(property="trace_id", type="string")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $grouped = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group')
            ->map(fn($items) => $items->map(fn($p) => [
                'name'        => $p->name,
                'description' => $p->description,
            ])->values());

        return response()->json(['data' => $grouped, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/permissions/my",
     *     tags={"Permissions"},
     *     summary="Get permissions for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function my(Request $request): JsonResponse
    {
        $user        = $request->user();
        $roleKey     = $user->role instanceof \App\Enums\UserRole ? $user->role->value : $user->role;
        $configPerms = config('permissions.roles.' . $roleKey, []);

        $all = in_array('*', $configPerms)
            ? config('permissions.all_permissions', [])
            : $configPerms;

        return response()->json([
            'data'     => [
                'role'        => $roleKey,
                'permissions' => $all,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
