<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Companies\Profile\CompanyProfileAssembler;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WAVE 3 / PR13 — company operational hub (read-only aggregates).
 */
final class CompanyProfileController extends Controller
{
    public function show(Request $request, int $id, CompanyProfileAssembler $assembler): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $company = Company::query()->findOrFail($id);
        $this->authorize('view', $company);

        $dto = $assembler->assemble($company, $user);
        $includeFinancial = $user->hasPermission('reports.financial.view');

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
