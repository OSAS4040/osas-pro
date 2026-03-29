<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bundle\StoreBundleRequest;
use App\Models\Bundle;
use App\Models\BundleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Bundles", description="Service/product bundle management")
 */
class BundleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/bundles",
     *     tags={"Bundles"},
     *     summary="List bundles",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $bundles = Bundle::with(['items.service', 'items.product', 'branch'])
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('code', $request->search);
            }))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $bundles, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/bundles",
     *     tags={"Bundles"},
     *     summary="Create a bundle",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name"},
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="base_price", type="number"),
     *         @OA\Property(property="override_item_prices", type="boolean"),
     *         @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *     )),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(StoreBundleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $bundle = DB::transaction(function () use ($data, $user) {
            $bundle = Bundle::create(array_merge(
                $data,
                [
                    'company_id'         => $user->company_id,
                    'created_by_user_id' => $user->id,
                ]
            ));

            foreach ($data['items'] ?? [] as $i => $item) {
                BundleItem::create([
                    'bundle_id'           => $bundle->id,
                    'item_type'           => $item['item_type'],
                    'service_id'          => $item['service_id'] ?? null,
                    'product_id'          => $item['product_id'] ?? null,
                    'quantity'            => $item['quantity'],
                    'unit_price_override' => $item['unit_price_override'] ?? null,
                    'notes'               => $item['notes'] ?? null,
                    'sort_order'          => $item['sort_order'] ?? $i,
                ]);
            }

            return $bundle;
        });

        return response()->json([
            'data'     => $bundle->load(['items.service', 'items.product']),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/bundles/{id}",
     *     tags={"Bundles"},
     *     summary="Get a bundle",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $bundle = Bundle::with(['items.service', 'items.product', 'branch', 'createdBy'])->findOrFail($id);

        return response()->json([
            'data'     => array_merge($bundle->toArray(), ['calculated_total' => $bundle->calculateTotal()]),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/bundles/{id}",
     *     tags={"Bundles"},
     *     summary="Update a bundle",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(StoreBundleRequest $request, int $id): JsonResponse
    {
        $data   = $request->validated();
        $bundle = Bundle::findOrFail($id);

        DB::transaction(function () use ($bundle, $data) {
            $bundle->update($data);

            if (isset($data['items'])) {
                BundleItem::where('bundle_id', $bundle->id)->delete();

                foreach ($data['items'] as $i => $item) {
                    BundleItem::create([
                        'bundle_id'           => $bundle->id,
                        'item_type'           => $item['item_type'],
                        'service_id'          => $item['service_id'] ?? null,
                        'product_id'          => $item['product_id'] ?? null,
                        'quantity'            => $item['quantity'],
                        'unit_price_override' => $item['unit_price_override'] ?? null,
                        'notes'               => $item['notes'] ?? null,
                        'sort_order'          => $item['sort_order'] ?? $i,
                    ]);
                }
            }
        });

        return response()->json([
            'data'     => $bundle->fresh(['items.service', 'items.product']),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/bundles/{id}",
     *     tags={"Bundles"},
     *     summary="Delete a bundle",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        Bundle::findOrFail($id)->delete();

        return response()->json(['message' => 'Bundle deleted.', 'trace_id' => app('trace_id')]);
    }
}
