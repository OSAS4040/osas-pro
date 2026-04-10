<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * عمليات: مطابقة بيانات النشر مع الواجهة (حقن build-time).
 */
class SystemVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'version'     => (string) config('deployment.version'),
                'commit'      => (string) config('deployment.commit'),
                'branch'      => (string) config('deployment.branch'),
                'build_time'  => config('deployment.build_time'),
                'environment' => (string) config('deployment.environment'),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
