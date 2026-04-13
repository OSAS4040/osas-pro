<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthPersonalAccessToken;
use App\Models\User;
use App\Services\Auth\AuthLoginEventRecorder;
use App\Support\Auth\IpAddressSummarizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthSessionController extends Controller
{
    /**
     * When Sanctum resolves the user from the web guard first, `currentAccessToken()` may be a
     * TransientToken while the caller still sent a Bearer PAT. Prefer the PAT from Authorization
     * when it belongs to the authenticated user.
     */
    private function resolveActivePersonalAccessToken(Request $request, User $user): ?AuthPersonalAccessToken
    {
        $raw = $request->bearerToken();
        if (is_string($raw) && $raw !== '' && str_contains($raw, '|')) {
            $candidate = AuthPersonalAccessToken::findToken($raw);
            if ($candidate instanceof AuthPersonalAccessToken
                && (int) $candidate->tokenable_id === (int) $user->id
                && $candidate->tokenable_type === $user->getMorphClass()) {
                return $candidate;
            }
        }

        $attached = $user->currentAccessToken();

        return $attached instanceof AuthPersonalAccessToken ? $attached : null;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->resolveActivePersonalAccessToken($request, $user);

        $morph = $user->getMorphClass();

        $rows = AuthPersonalAccessToken::query()
            ->where('tokenable_type', $morph)
            ->where('tokenable_id', $user->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'auth_channel', 'ip_address', 'user_agent_summary', 'last_used_at', 'created_at']);

        $data = $rows->map(function (AuthPersonalAccessToken $t) use ($current): array {
            return [
                'id'                   => $t->id,
                'device_name'          => $t->name,
                'auth_channel'         => $t->auth_channel,
                'ip_summary'           => IpAddressSummarizer::summarize($t->ip_address),
                'user_agent_summary'   => $t->user_agent_summary,
                'last_used_at'         => $t->last_used_at?->toIso8601String(),
                'created_at'           => $t->created_at?->toIso8601String(),
                'is_current'           => $current !== null && (int) $t->id === (int) $current->id,
            ];
        });

        return response()->json([
            'data'     => $data,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $morph = $user->getMorphClass();

        $token = AuthPersonalAccessToken::query()
            ->where('tokenable_type', $morph)
            ->where('tokenable_id', $user->id)
            ->whereKey($id)
            ->first();

        if ($token === null) {
            return response()->json([
                'message'  => 'الجلسة غير موجودة.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        $current = $this->resolveActivePersonalAccessToken($request, $user);
        if ($current !== null && (int) $token->id === (int) $current->id) {
            return response()->json([
                'message'  => 'استخدم تسجيل الخروج لإنهاء الجلسة الحالية.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        app(AuthLoginEventRecorder::class)->revokeSession($user, (int) $token->id, $request);
        $token->delete();

        return response()->json([
            'message'  => 'تم إنهاء الجلسة.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function revokeOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $this->resolveActivePersonalAccessToken($request, $user);
        if ($current === null) {
            return response()->json([
                'message'  => 'لا توجد جلسة حالية.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $morph = $user->getMorphClass();

        app(AuthLoginEventRecorder::class)->revokeOtherSessions($user, $request);

        AuthPersonalAccessToken::query()
            ->where('tokenable_type', $morph)
            ->where('tokenable_id', $user->id)
            ->where('id', '!=', $current->id)
            ->delete();

        return response()->json([
            'message'  => 'تم إنهاء الجلسات الأخرى.',
            'trace_id' => app('trace_id'),
        ]);
    }
}
