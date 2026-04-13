<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Models\AuthSuspiciousLoginSignal;
use App\Services\Auth\AuthSecurityTelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only internal listing of PR5 suspicious-login signals (masked fingerprints).
 */
final class AuthSuspiciousLoginSignalsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        $rows = AuthSuspiciousLoginSignal::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'signal_type', 'channel', 'subject_fingerprint', 'ip_address', 'trace_id', 'created_at']);

        $data = $rows->map(static function ($row) {
            return [
                'id'                      => $row->id,
                'signal_type'             => $row->signal_type,
                'channel'                 => $row->channel,
                'subject_fingerprint_mask'=> AuthSecurityTelemetryService::maskFingerprint((string) $row->subject_fingerprint),
                'ip_address'              => $row->ip_address,
                'trace_id'                => $row->trace_id,
                'created_at'              => $row->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data'     => $data,
            'trace_id' => app('trace_id'),
        ]);
    }
}
