<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Config\AssignVerticalProfileRequest;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Subscription;
use App\Models\VerticalProfile;
use App\Support\SubscriptionQuota;
use App\Services\Config\ResolvedConfigVisibilityService;
use App\Services\Config\VerticalProfileGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Branches", description="Branch management")
 */
class BranchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/branches",
     *     tags={"Branches"},
     *     summary="List branches for current company",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $query = Branch::query()
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('is_main')
            ->orderBy('name');

        if ($request->boolean('for_map')) {
            return response()->json([
                'data'     => $query->get(),
                'trace_id' => app('trace_id'),
            ]);
        }

        $branches = $query->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        return response()->json(['data' => $branches, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/branches",
     *     tags={"Branches"},
     *     summary="Create a branch",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        SubscriptionQuota::assertCanCreateBranch((int) $request->user()->company_id);

        $branch = Branch::create(array_merge(
            $request->validated(),
            [
                'uuid'       => Str::uuid(),
                'company_id' => $request->user()->company_id,
            ]
        ));

        return response()->json(['data' => $branch, 'trace_id' => app('trace_id')], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/branches/{id}",
     *     tags={"Branches"},
     *     summary="Get a branch",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $branch = Branch::with('users')->findOrFail($id);

        $this->authorize('view', $branch);

        return response()->json(['data' => $branch, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/branches/{id}",
     *     tags={"Branches"},
     *     summary="Update a branch",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(UpdateBranchRequest $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $this->authorize('update', $branch);

        $branch->update($request->validated());

        return response()->json(['data' => $branch->fresh(), 'trace_id' => app('trace_id')]);
    }

    public function assignVerticalProfile(
        AssignVerticalProfileRequest $request,
        int $id,
        VerticalProfileGovernanceService $governance
    ): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $this->authorize('update', $branch);

        $branch = $governance->assignBranchProfile(
            branch: $branch,
            verticalProfileCode: $request->input('vertical_profile_code'),
            actorUserId: (int) $request->user()->id,
            reason: $request->input('reason')
        );

        return response()->json(['data' => $branch, 'trace_id' => app('trace_id')]);
    }

    public function effectiveConfig(
        int $id,
        ResolvedConfigVisibilityService $visibility
    ): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $this->authorize('view', $branch);

        $plan = Subscription::query()->where('company_id', $branch->company_id)->latest('id')->value('plan');
        $verticalCode = $branch->vertical_profile_code
            ?: ($branch->company?->vertical_profile_code ?: null);
        if ($verticalCode === null) {
            $verticalCode = VerticalProfile::query()->where('is_active', true)->orderBy('id')->value('code');
        }

        $resolved = $visibility->resolveForBranch((int) $branch->company_id, (int) $branch->id, $plan, $verticalCode, (int) $this->resolveActorId());

        return response()->json([
            'data' => [
                'context' => [
                    'company_id' => $branch->company_id,
                    'plan' => $plan,
                    'vertical' => $verticalCode,
                    'branch_id' => $branch->id,
                ],
                'config' => $resolved,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function resolveActorId(): int
    {
        return (int) optional(request()->user())->id;
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/branches/{id}",
     *     tags={"Branches"},
     *     summary="Delete a branch",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $this->authorize('delete', $branch);

        $branch->delete();

        return response()->json(['message' => 'Branch deleted.', 'trace_id' => app('trace_id')]);
    }
}
