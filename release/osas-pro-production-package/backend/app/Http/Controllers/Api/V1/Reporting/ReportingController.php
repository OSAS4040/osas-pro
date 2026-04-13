<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ReportingWorkOrderSummaryRequest;
use App\Services\Reporting\WorkOrderOperationalSummaryReporter;
use Illuminate\Http\JsonResponse;

/**
 * WAVE 2 / PR7 — unified read-only reporting entrypoints (no legacy ReportController coupling).
 */
final class ReportingController extends Controller
{
    public function workOrderSummary(
        ReportingWorkOrderSummaryRequest $request,
        WorkOrderOperationalSummaryReporter $reporter,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $payload = $reporter->build($user, $request->validated());

        return response()->json($payload);
    }
}
