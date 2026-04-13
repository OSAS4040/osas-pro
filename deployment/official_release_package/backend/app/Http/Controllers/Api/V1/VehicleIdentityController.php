<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleIdentityToken;
use App\Services\VehicleIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VehicleIdentityController extends Controller
{
    public function __construct(
        private readonly VehicleIdentityService $vehicleIdentity,
    ) {}

    /**
     * عرض عام — بدون مصادقة؛ لا يُعاد VIN أو معرف داخلي في الحمولة.
     */
    public function publicShow(Request $request, string $token): JsonResponse
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return response()->json([
                'message' => 'Not found.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        $record = VehicleIdentityToken::query()
            ->where('token', $token)
            ->where('status', VehicleIdentityToken::STATUS_ACTIVE)
            ->first();

        $this->vehicleIdentity->logScan($record, $token, $request->ip(), $request->userAgent());

        if (! $record) {
            return response()->json([
                'message' => 'Not found.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        return response()->json([
            'data' => $this->vehicleIdentity->publicPayload($record),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * بعد تسجيل الدخول — ربط الرمز الممسوح بمعرف المركبة ضمن نفس المستأجر.
     */
    public function resolve(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('vehicles.view') && ! $user->hasPermission('fleet.vehicles.view')) {
            return response()->json([
                'message' => 'You do not have permission to open this vehicle record.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ]);

        $record = VehicleIdentityToken::query()
            ->where('token', $data['token'])
            ->where('status', VehicleIdentityToken::STATUS_ACTIVE)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'Not found.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        $vehicle = Vehicle::query()->find($record->vehicle_id);

        if (! $vehicle) {
            return response()->json([
                'message' => 'Not found.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        return response()->json([
            'data' => [
                'vehicle_id' => $vehicle->id,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function rotate(int $id): JsonResponse
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        $token = $this->vehicleIdentity->rotate($vehicle);

        return response()->json([
            'data' => [
                'public_url' => $token->publicUrl(),
                'public_code' => $token->public_code,
                'status' => $token->status,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function revoke(int $id): JsonResponse
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        $this->vehicleIdentity->revoke($vehicle);

        return response()->json([
            'message' => 'تم إبطال رابط هوية المركبة العام.',
            'data' => [
                'public_url' => null,
                'public_code' => null,
                'status' => 'revoked',
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * إصدار رابط جديد بعد الإبطال أو عند الحاجة (يستدعي إنشاء رمز نشط).
     */
    public function issue(int $id): JsonResponse
    {
        $vehicle = Vehicle::query()->findOrFail($id);
        $token = $this->vehicleIdentity->ensureActiveToken($vehicle);

        return response()->json([
            'data' => [
                'public_url' => $token->publicUrl(),
                'public_code' => $token->public_code,
                'status' => $token->status,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
