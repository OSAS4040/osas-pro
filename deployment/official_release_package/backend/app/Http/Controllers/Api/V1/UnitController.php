<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Units", description="Units of measure and conversions")
 */
class UnitController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/units",
     *     tags={"Units"},
     *     summary="List units of measure",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $units = Unit::withoutGlobalScope('tenant')
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->where('is_active', true)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $units, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/units",
     *     tags={"Units"},
     *     summary="Create a custom unit of measure",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","symbol"},
     *             @OA\Property(property="name", type="string", example="Box"),
     *             @OA\Property(property="symbol", type="string", example="box"),
     *             @OA\Property(property="type", type="string", example="quantity"),
     *             @OA\Property(property="is_base", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create(array_merge(
            $request->validated(),
            [
                'company_id' => $request->user()->company_id,
                'is_system'  => false,
            ]
        ));

        return response()->json(['data' => $unit, 'trace_id' => app('trace_id')], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/units/{id}",
     *     tags={"Units"},
     *     summary="Update a custom unit",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $unit = Unit::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->where('is_system', false)
            ->firstOrFail();

        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:80'],
            'name_ar' => ['nullable', 'string', 'max:80'],
            'symbol'  => [
                'sometimes', 'string', 'max:20',
                Rule::unique('units', 'symbol')->where('company_id', $companyId)->ignore($id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit->update($data);

        return response()->json(['data' => $unit, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/units/{id}",
     *     tags={"Units"},
     *     summary="Delete a custom unit",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $unit = Unit::withoutGlobalScope('tenant')
            ->where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->where('is_system', false)
            ->firstOrFail();

        $unit->delete();

        return response()->json(['message' => 'Unit deleted.', 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/units/conversions",
     *     tags={"Units"},
     *     summary="List unit conversions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function conversions(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $conversions = UnitConversion::withoutGlobalScope('tenant')
            ->where(fn($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))
            ->with(['fromUnit', 'toUnit'])
            ->get();

        return response()->json(['data' => $conversions, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/units/conversions",
     *     tags={"Units"},
     *     summary="Create a unit conversion",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_unit_id","to_unit_id","factor"},
     *             @OA\Property(property="from_unit_id", type="integer"),
     *             @OA\Property(property="to_unit_id", type="integer"),
     *             @OA\Property(property="factor", type="number", format="float", example=12)
     *         )
     *     ),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function storeConversion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_unit_id' => ['required', 'integer', 'exists:units,id'],
            'to_unit_id'   => ['required', 'integer', 'exists:units,id', 'different:from_unit_id'],
            'factor'       => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $companyId = $request->user()->company_id;

        $conversion = UnitConversion::updateOrCreate(
            [
                'company_id'   => $companyId,
                'from_unit_id' => $data['from_unit_id'],
                'to_unit_id'   => $data['to_unit_id'],
            ],
            ['factor' => $data['factor'], 'is_active' => true]
        );

        return response()->json([
            'data'     => $conversion->load(['fromUnit', 'toUnit']),
            'trace_id' => app('trace_id'),
        ], 201);
    }
}
