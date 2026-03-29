<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Services", description="Service catalog management")
 */
class ServiceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/services",
     *     tags={"Services"},
     *     summary="List services",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $services = Service::with(['branch', 'createdBy'])
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', $request->search);
            }))
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $services, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/services",
     *     tags={"Services"},
     *     summary="Create a service",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","base_price"},
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="base_price", type="number"),
     *         @OA\Property(property="tax_rate", type="number"),
     *         @OA\Property(property="estimated_minutes", type="integer"),
     *         @OA\Property(property="code", type="string")
     *     )),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create(array_merge(
            $request->validated(),
            [
                'company_id'         => $request->user()->company_id,
                'created_by_user_id' => $request->user()->id,
            ]
        ));

        return response()->json(['data' => $service, 'trace_id' => app('trace_id')], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/services/{id}",
     *     tags={"Services"},
     *     summary="Get a service",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $service = Service::with(['branch', 'createdBy'])->findOrFail($id);

        return response()->json(['data' => $service, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/services/{id}",
     *     tags={"Services"},
     *     summary="Update a service",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(UpdateServiceRequest $request, int $id): JsonResponse
    {
        $service = Service::findOrFail($id);
        $service->update($request->validated());

        return response()->json(['data' => $service->fresh(), 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/services/{id}",
     *     tags={"Services"},
     *     summary="Delete a service (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        Service::findOrFail($id)->delete();

        return response()->json(['message' => 'Service deleted.', 'trace_id' => app('trace_id')]);
    }
}
