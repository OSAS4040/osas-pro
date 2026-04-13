<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AuthSuspiciousLoginSignal;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * PR5: failed-attempt counters (cache) and minimal suspicious-login signal rows (DB).
 */
final class AuthSecurityTelemetryService
{
    private static bool $skipSuspiciousSignalsDb = false;

    private static bool $missingSuspiciousTableLogged = false;

    public function recordInvalidPasswordLogin(Request $request): void
    {
        $identifier = Str::lower(trim((string) ($request->input('email') ?: $request->input('identifier') ?: '')));
        $salt = 'pwd|'.$identifier;
        $fp = $this->subjectFingerprint($request, $salt);

        $prefix = (string) config('auth_security.cache_prefix', 'auth_sec_v1');
        $window = (int) config('auth_security.failed_password_login.window_seconds', 900);
        $burstAt = (int) config('auth_security.failed_password_login.burst_signal_after_attempts', 25);

        $key = $prefix.':pwd_fail:'.$fp;
        $count = $this->bumpCounter($key, $window);

        if ($count === $burstAt) {
            $this->insertSignal(
                'failed_login_burst',
                'password',
                $fp,
                $request,
                ['attempts_in_window' => $count, 'window_seconds' => $window],
            );
        }
    }

    public function recordInvalidOtpVerification(Request $request, string $phoneNormalized): void
    {
        $salt = 'otp|'.$phoneNormalized;
        $fp = $this->subjectFingerprint($request, $salt);

        $prefix = (string) config('auth_security.cache_prefix', 'auth_sec_v1');
        $window = (int) config('auth_security.failed_otp_verify.window_seconds', 900);
        $burstAt = (int) config('auth_security.failed_otp_verify.burst_signal_after_attempts', 12);

        $key = $prefix.':otp_fail:'.$fp;
        $count = $this->bumpCounter($key, $window);

        if ($count === $burstAt) {
            $this->insertSignal(
                'failed_otp_verify_burst',
                'otp_phone',
                $fp,
                $request,
                ['attempts_in_window' => $count, 'window_seconds' => $window],
            );
        }
    }

    public function recordRateLimitedIfAuthEndpoint(Request $request): void
    {
        if ($request->is('api/v1/auth/login')) {
            $this->maybeRecordRateLimitSignal('rate_limited_login', 'password', $request);

            return;
        }
        if ($request->is('api/v1/auth/phone/verify-otp')) {
            $this->maybeRecordRateLimitSignal('rate_limited_otp_verify', 'otp_phone', $request);

            return;
        }
        if ($request->is('api/v1/auth/phone/request-otp')) {
            $this->maybeRecordRateLimitSignal('rate_limited_otp_resend', 'otp_phone', $request);
        }
    }

    /**
     * @return array{count: int, key: string}
     */
    public function debugPasswordFailureState(Request $request): array
    {
        $identifier = Str::lower(trim((string) ($request->input('email') ?: $request->input('identifier') ?: '')));
        $fp = $this->subjectFingerprint($request, 'pwd|'.$identifier);
        $prefix = (string) config('auth_security.cache_prefix', 'auth_sec_v1');
        $key = $prefix.':pwd_fail:'.$fp;

        return [
            'key'   => $key,
            'count' => (int) Cache::get($key, 0),
        ];
    }

    /**
     * @return array{count: int, key: string}
     */
    public function debugOtpFailureState(Request $request, string $phoneNormalized): array
    {
        $fp = $this->subjectFingerprint($request, 'otp|'.$phoneNormalized);
        $prefix = (string) config('auth_security.cache_prefix', 'auth_sec_v1');
        $key = $prefix.':otp_fail:'.$fp;

        return [
            'key'   => $key,
            'count' => (int) Cache::get($key, 0),
        ];
    }

    private function bumpCounter(string $key, int $windowSeconds): int
    {
        $current = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $current, now()->addSeconds(max(60, $windowSeconds)));

        return $current;
    }

    private function maybeRecordRateLimitSignal(string $signalType, string $channel, Request $request): void
    {
        $salt = 'rl|'.$signalType;
        $fp = $this->subjectFingerprint($request, $salt);
        $prefix = (string) config('auth_security.cache_prefix', 'auth_sec_v1');
        $debounce = (int) config('auth_security.rate_limit_signal_debounce_seconds', 60);
        $debounceKey = $prefix.':debounce:'.$signalType.':'.$fp;

        if (! Cache::add($debounceKey, 1, now()->addSeconds(max(10, $debounce)))) {
            return;
        }

        $this->insertSignal($signalType, $channel, $fp, $request, []);
    }

    private function subjectFingerprint(Request $request, string $salt): string
    {
        return hash('sha256', (string) $request->ip().'|'.$salt);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function insertSignal(
        string $signalType,
        string $channel,
        string $subjectFingerprint,
        Request $request,
        array $payload,
    ): void {
        if (self::$skipSuspiciousSignalsDb) {
            return;
        }

        $ua = substr((string) $request->userAgent(), 0, 512);
        $uaHash = $ua !== '' ? hash('sha256', $ua) : null;

        try {
            AuthSuspiciousLoginSignal::query()->create([
                'signal_type'          => $signalType,
                'channel'              => $channel,
                'subject_fingerprint'  => $subjectFingerprint,
                'ip_address'           => $request->ip(),
                'user_agent_hash'      => $uaHash,
                'trace_id'             => $this->resolveTraceId(),
                'payload'              => $payload !== [] ? $payload : null,
            ]);
        } catch (QueryException $e) {
            if (! $this->isMissingSuspiciousSignalsTable($e)) {
                throw $e;
            }
            $this->markSuspiciousSignalsUnavailable();
        } catch (Throwable $e) {
            if (! $this->isMissingSuspiciousSignalsTable($e)) {
                throw $e;
            }
            $this->markSuspiciousSignalsUnavailable();
        }
    }

    private function markSuspiciousSignalsUnavailable(): void
    {
        self::$skipSuspiciousSignalsDb = true;
        if (! self::$missingSuspiciousTableLogged) {
            self::$missingSuspiciousTableLogged = true;
            Log::warning('Auth security telemetry skipped: relation auth_suspicious_login_signals is missing. Run: php artisan migrate');
        }
    }

    private function isMissingSuspiciousSignalsTable(Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $state = (string) ($e->errorInfo[0] ?? '');
            if (in_array($state, ['42P01', '42S02'], true)) {
                $msg = strtolower($e->getMessage());

                return str_contains($msg, 'auth_suspicious_login_signals');
            }
        }

        $msg = strtolower($e->getMessage());
        if (! str_contains($msg, 'auth_suspicious_login_signals')) {
            return false;
        }

        return str_contains($msg, '42p01')
            || str_contains($msg, '42s02')
            || str_contains($msg, 'does not exist')
            || str_contains($msg, "doesn't exist")
            || str_contains($msg, 'undefined table')
            || str_contains($msg, 'no such table');
    }

    private function resolveTraceId(): ?string
    {
        if (! app()->bound('trace_id')) {
            return null;
        }
        $raw = app('trace_id');

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    public static function maskFingerprint(string $hex64): string
    {
        if (strlen($hex64) <= 12) {
            return $hex64;
        }

        return substr($hex64, 0, 8).'…'.substr($hex64, -4);
    }
}
