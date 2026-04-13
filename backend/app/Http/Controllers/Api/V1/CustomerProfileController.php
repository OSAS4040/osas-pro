<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Customers\Profile\CustomerProfileAssembler;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WAVE 3 / PR14 — customer operational hub (read-only).
 */
final class CustomerProfileController extends Controller
{
    public function show(Request $request, int $id, CustomerProfileAssembler $assembler): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $customer = Customer::query()->findOrFail($id);

        if (! $user->hasPermission('cross_branch_access') && $user->branch_id && (int) $customer->branch_id !== (int) $user->branch_id) {
            abort(403, 'Customer is outside your branch scope.');
        }

        $includeFinancial = $user->hasPermission('reports.financial.view');
        $dto = $assembler->assemble($customer, $user, $includeFinancial);

        return response()->json([
            'data' => $dto->toArray(),
            'meta' => [
                'financial_metrics_included' => $includeFinancial,
                'read_only' => true,
                'intelligence_version' => 'v1',
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
