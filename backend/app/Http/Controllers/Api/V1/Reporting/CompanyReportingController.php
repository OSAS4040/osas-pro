<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CompanyReportingPulseRequest;
use App\Services\Reporting\CompanyPulseSummaryReporter;
use Illuminate\Http\JsonResponse;

final class CompanyReportingController extends Controller
{
    public function pulseSummary(
        CompanyReportingPulseRequest $request,
        CompanyPulseSummaryReporter $reporter,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $payload = $reporter->build($user, $request->validated());

        return response()->json($payload);
    }
}
