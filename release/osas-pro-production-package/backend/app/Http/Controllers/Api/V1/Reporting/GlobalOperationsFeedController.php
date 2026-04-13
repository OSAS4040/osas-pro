<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\GlobalOperationsFeedRequest;
use App\Services\Reporting\GlobalOperationsFeedReporter;
use Illuminate\Http\JsonResponse;

final class GlobalOperationsFeedController extends Controller
{
    public function index(
        GlobalOperationsFeedRequest $request,
        GlobalOperationsFeedReporter $reporter,
    ): JsonResponse {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        return response()->json($reporter->build($user, $request->validated()));
    }
}
