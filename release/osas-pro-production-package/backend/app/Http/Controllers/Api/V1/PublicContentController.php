<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformAnnouncementBanner;
use Illuminate\Http\JsonResponse;

class PublicContentController extends Controller
{
    public function landingPlans(): JsonResponse
    {
        return response()->json([
            'data'     => config('landing', []),
            'trace_id' => app('trace_id'),
        ]);
    }

    /** إعلان المنصة — بدون مصادقة (صفحات الهبوط والدخول). */
    public function platformAnnouncementBanner(): JsonResponse
    {
        $b = PlatformAnnouncementBanner::theOne();

        return response()->json([
            'data'     => $b->toPublicBannerPayload(),
            'trace_id' => app('trace_id'),
        ]);
    }
}
