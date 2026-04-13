<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Enums\UserRole;
use App\Support\SubscriptionQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Users", description="User management")
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="List users",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="role", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'branch_id'  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'role'       => ['sometimes', 'nullable', 'string', Rule::in(UserRole::values())],
            'is_active'  => ['sometimes', 'nullable', 'boolean'],
            'search'     => ['sometimes', 'nullable', 'string', 'max:120'],
            'page'       => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page'   => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::with(['branch', 'orgUnit'])
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->branch_id))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')->toString()))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $raw = trim($request->string('search')->toString());
                if ($raw === '') {
                    return;
                }
                $like = '%'.addcslashes($raw, '%_\\').'%';
                $q->where(function ($qq) use ($like) {
                    $qq->where('name', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like);
                });
            })
            ->orderBy('name')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        $users->setCollection(
            $users->getCollection()->map(fn (User $u) => $u->makeHidden(['password']))
        );

        return response()->json(['data' => $users, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="Create a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        SubscriptionQuota::assertCanCreateUser((int) $request->user()->company_id);

        $user = User::create(array_merge(
            $request->validated(),
            [
                'uuid'       => Str::uuid(),
                'company_id' => $request->user()->company_id,
            ]
        ));
        $user->load(['branch', 'orgUnit']);

        return response()->json([
            'data'     => $user->makeHidden(['password']),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Get a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['branch', 'orgUnit'])->findOrFail($id);

        $this->authorize('view', $user);

        return response()->json(['data' => $user->makeHidden(['password']), 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Update a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $this->authorize('update', $user);

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        return response()->json([
            'data'     => $user->fresh(['branch', 'orgUnit'])->makeHidden(['password']),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Delete a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(['message' => 'User deleted.', 'trace_id' => app('trace_id')]);
    }
}
