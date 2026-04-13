<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformAnnouncementBannerRequest;
use App\Models\PlatformAnnouncementBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformAnnouncementBannerController extends Controller
{
    /**
     * عرض الإعلان لأي مستخدم مُصدَّق داخل مستأجر (للشريط في الواجهة).
     */
    public function show(Request $request): JsonResponse
    {
        $b = PlatformAnnouncementBanner::theOne();

        return response()->json([
            'data'     => $b->toPublicBannerPayload(),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * نفس البيانات الكاملة للمحرّر — لمشغّلي المنصة فقط (للنموذج في لوحة الأدمن).
     */
    public function adminShow(Request $request): JsonResponse
    {
        $b = PlatformAnnouncementBanner::theOne();

        return response()->json([
            'data'     => $this->toAdminPayload($b),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function update(UpdatePlatformAnnouncementBannerRequest $request): JsonResponse
    {
        $b = PlatformAnnouncementBanner::theOne();

        $validated = $request->validated();
        $b->fill($validated);
        $b->dismiss_token = Str::random(32);
        $b->save();

        return response()->json([
            'data'     => $this->toAdminPayload($b->fresh()),
            'message'  => 'تم حفظ إعدادات الشريط الإعلاني.',
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toAdminPayload(PlatformAnnouncementBanner $b): array
    {
        return [
            'is_enabled'    => (bool) $b->is_enabled,
            'title'         => $b->title,
            'message'       => $b->message,
            'link_url'      => $b->link_url,
            'link_text'     => $b->link_text,
            'variant'       => in_array($b->variant, ['info', 'success', 'warning', 'promo'], true)
                ? (string) $b->variant
                : 'promo',
            'dismissible'   => (bool) $b->dismissible,
            'dismiss_token' => (string) $b->dismiss_token,
            'updated_at'    => $b->updated_at?->toIso8601String(),
        ];
    }
}
