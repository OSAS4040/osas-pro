<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\PlatformReportingPulseRequest;
use App\Services\Reporting\PlatformPulseSummaryReporter;
use Illuminate\Http\JsonResponse;

final class PlatformReportingController extends Controller
{
    public function pulseSummary(
        PlatformReportingPulseRequest $request,
        PlatformPulseSummaryReporter $reporter,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $payload = $reporter->build($user, $request->validated());

        return response()->json($payload);
    }
}
