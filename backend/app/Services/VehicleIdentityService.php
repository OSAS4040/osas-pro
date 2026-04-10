<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Vehicle;
use App\Models\VehicleIdentityScanEvent;
use App\Models\VehicleIdentityToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VehicleIdentityService
{
    public function ensureActiveToken(Vehicle $vehicle): VehicleIdentityToken
    {
        return DB::transaction(function () use ($vehicle) {
            $existing = VehicleIdentityToken::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('status', VehicleIdentityToken::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->createActiveToken($vehicle);
        });
    }

    public function rotate(Vehicle $vehicle): VehicleIdentityToken
    {
        return DB::transaction(function () use ($vehicle) {
            $active = VehicleIdentityToken::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('status', VehicleIdentityToken::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($active) {
                $active->update([
                    'status' => VehicleIdentityToken::STATUS_REPLACED,
                    'revoked_at' => now(),
                ]);
            }

            $new = $this->createActiveToken($vehicle);

            if ($active) {
                $active->update(['replaced_by_id' => $new->id]);
            }

            return $new->fresh();
        });
    }

    public function revoke(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle) {
            $active = VehicleIdentityToken::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('status', VehicleIdentityToken::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $active) {
                return;
            }

            $active->update([
                'status' => VehicleIdentityToken::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);
        });
    }

    /**
     * حمولة البطاقة الرقمية: إصدار تلقائي فقط عند أول زيارة (لا سجلات سابقة).
     * بعد الإبطال لا يُعاد إنشاء الرمز تلقائياً — يستخدم المستخدم «إصدار رابط جديد».
     *
     * @return array{public_url: ?string, public_code: ?string, status: string} status may be active|revoked|unavailable (no DB table)
     */
    public function identityPayloadForDigitalCard(Vehicle $vehicle): array
    {
        if (! Schema::hasTable('vehicle_identity_tokens')) {
            return [
                'public_url' => null,
                'public_code' => null,
                'status' => 'unavailable',
            ];
        }

        $active = VehicleIdentityToken::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('status', VehicleIdentityToken::STATUS_ACTIVE)
            ->first();

        if ($active) {
            return [
                'public_url' => $active->publicUrl(),
                'public_code' => $active->public_code,
                'status' => $active->status,
            ];
        }

        $hadAny = VehicleIdentityToken::query()->where('vehicle_id', $vehicle->id)->exists();

        if (! $hadAny) {
            $created = $this->ensureActiveToken($vehicle);

            return [
                'public_url' => $created->publicUrl(),
                'public_code' => $created->public_code,
                'status' => $created->status,
            ];
        }

        return [
            'public_url' => null,
            'public_code' => null,
            'status' => 'revoked',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(VehicleIdentityToken $token): array
    {
        $vehicle = Vehicle::withoutGlobalScopes()
            ->select(['id', 'company_id', 'make', 'model', 'year', 'plate_number'])
            ->find($token->vehicle_id);

        $company = Company::query()
            ->select(['id', 'name', 'name_ar'])
            ->find($token->company_id);

        $displayName = $company?->name_ar ?: $company?->name ?: '—';

        return [
            'product' => [
                'name' => 'أسس برو',
                'name_en' => 'OSAS Pro',
                'tagline' => 'هوية رقمية للمركبة — مسح آمن للتحقق من المركبة المرتبطة بمركز الخدمة.',
            ],
            'public_code' => $token->public_code,
            'company_name' => $displayName,
            'vehicle' => [
                'make' => $vehicle?->make,
                'model' => $vehicle?->model,
                'year' => $vehicle?->year,
                'plate_masked' => $this->maskPlate($vehicle?->plate_number),
            ],
            'login_hint' => 'لعرض التفاصيل الكاملة والسجل، سجّل الدخول بحساب مرتبط بنفس مركز الخدمة.',
        ];
    }

    public function logScan(?VehicleIdentityToken $token, string $rawToken, ?string $ip, ?string $userAgent): void
    {
        $prefix = strlen($rawToken) >= 12 ? substr($rawToken, 0, 12) : $rawToken;

        VehicleIdentityScanEvent::query()->create([
            'vehicle_identity_token_id' => $token?->id,
            'token_prefix' => $prefix,
            'ip_address' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 2000) : null,
            'created_at' => now(),
        ]);
    }

    private function createActiveToken(Vehicle $vehicle): VehicleIdentityToken
    {
        $attempts = 0;

        while ($attempts < 12) {
            $attempts++;

            $tokenValue = bin2hex(random_bytes(32));
            $publicCode = $this->generatePublicCode();

            try {
                return VehicleIdentityToken::query()->create([
                    'vehicle_id' => $vehicle->id,
                    'company_id' => $vehicle->company_id,
                    'token' => $tokenValue,
                    'public_code' => $publicCode,
                    'status' => VehicleIdentityToken::STATUS_ACTIVE,
                ]);
            } catch (QueryException) {
                continue;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique vehicle identity token.');
    }

    private function generatePublicCode(): string
    {
        $seg = static fn () => strtoupper(Str::random(4));

        return 'VH-'.$seg().'-'.$seg();
    }

    private function maskPlate(?string $plate): ?string
    {
        if ($plate === null || $plate === '') {
            return null;
        }

        $t = trim($plate);
        if (mb_strlen($t) <= 4) {
            return '****';
        }

        return '***'.mb_substr($t, -4);
    }
}
