<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Products", description="Product and service catalog")
 */
class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="List products",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="product_type", in="query", @OA\Schema(type="string", enum={"physical","service","consumable","labor"})),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="track_inventory", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'unit', 'purchaseUnit'])
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('barcode', $request->search)
                  ->orWhere('sku', $request->search);
            }))
            ->when($request->product_type, fn($q) => $q->where('product_type', $request->product_type))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->has('track_inventory'), fn($q) => $q->where('track_inventory', $request->boolean('track_inventory')))
            ->orderBy('name')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $products, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="Create a product",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","sale_price"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="product_type", type="string", enum={"physical","service","consumable","labor"}),
     *             @OA\Property(property="unit_id", type="integer"),
     *             @OA\Property(property="purchase_unit_id", type="integer"),
     *             @OA\Property(property="sku", type="string"),
     *             @OA\Property(property="barcode", type="string"),
     *             @OA\Property(property="sale_price", type="number"),
     *             @OA\Property(property="cost_price", type="number"),
     *             @OA\Property(property="tax_rate", type="number"),
     *             @OA\Property(property="track_inventory", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (!isset($validated['track_inventory']) && $request->has('track_stock')) {
            $validated['track_inventory'] = $request->boolean('track_stock');
        }

        $product = Product::create(array_merge(
            $validated,
            [
                'uuid'               => Str::uuid(),
                'company_id'         => $request->user()->company_id,
                'created_by_user_id' => $request->user()->id,
            ]
        ));

        if ($product->track_inventory) {
            $initialQty = (float) ($request->input('stock_quantity', $request->input('quantity', 0)));
            Inventory::firstOrCreate(
                ['company_id' => $product->company_id, 'product_id' => $product->id],
                [
                    'branch_id'         => $request->user()->branch_id,
                    'quantity'          => $initialQty,
                    'reserved_quantity' => 0,
                    'reorder_point'     => $request->input('reorder_point', 0),
                ]
            );
        }

        return response()->json([
            'data'     => $product->load(['category', 'unit', 'purchaseUnit']),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Get a product",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'unit', 'purchaseUnit', 'inventory'])->findOrFail($id);

        return response()->json(['data' => $product, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Update a product",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());
        $product->increment('version');

        return response()->json([
            'data'     => $product->fresh(['category', 'unit']),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Delete a product (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        if (! request()->user()?->hasPermission('products.delete')) {
            return response()->json(['message' => 'Unauthorized.', 'trace_id' => app('trace_id')], 403);
        }

        Product::findOrFail($id)->delete();

        return response()->json(['message' => 'Product deleted.', 'trace_id' => app('trace_id')]);
    }
}
