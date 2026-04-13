<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CustomerReportingPulseRequest;
use App\Services\Reporting\CustomerPulseSummaryReporter;
use Illuminate\Http\JsonResponse;

final class CustomerReportingController extends Controller
{
    public function pulseSummary(
        CustomerReportingPulseRequest $request,
        CustomerPulseSummaryReporter $reporter,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $payload = $reporter->build($user, $request->validated());

        return response()->json($payload);
    }
}
