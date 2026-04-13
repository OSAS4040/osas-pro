<?php

declare(strict_types=1);

namespace App\Services\PhoneRegistration;

use App\Models\PhoneOtp;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class PhoneOtpService
{
    public const PURPOSE_REGISTER_LOGIN = 'phone_register_login';

    public function normalizedPhone(string $raw): string
    {
        return PhoneNormalizer::normalizeForStorage($raw);
    }

    /**
     * @throws ValidationException
     */
    public function requestOtp(string $phoneRaw, Request $request): void
    {
        $phone = $this->normalizedPhone($phoneRaw);
        if ($phone === '') {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير صالح.']]);
        }

        $this->throttleSend($phone, $request);

        $lockKey = 'phone_otp_verify_lock:'.$phone;
        if (Cache::has($lockKey)) {
            throw ValidationException::withMessages([
                'phone' => ['محاولات كثيرة. حاول لاحقاً.'],
            ]);
        }

        PhoneOtp::query()
            ->where('phone', $phone)
            ->where('purpose', self::PURPOSE_REGISTER_LOGIN)
            ->whereNull('verified_at')
            ->delete();

        $code = (string) random_int(100000, 999999);
        $ttl  = (int) config('saas.phone_otp_ttl_seconds', 300);

        PhoneOtp::query()->create([
            'phone'          => $phone,
            'otp_code_hash'  => Hash::make($code),
            'purpose'        => self::PURPOSE_REGISTER_LOGIN,
            'expires_at'     => now()->addSeconds($ttl),
            'max_attempts'   => (int) config('saas.phone_otp_max_attempts', 8),
            'ip_address'     => $request->ip(),
            'user_agent'     => substr((string) $request->userAgent(), 0, 2000),
        ]);

        $this->dispatchSms($phone, $code, $ttl);

        if (config('app.debug')) {
            Log::info('phone_otp.issued', [
                'phone_suffix' => substr($phone, -4),
                'trace_id'     => app('trace_id'),
            ]);
        }
    }

    /**
     * @return array{valid: bool, reason?: string}
     */
    public function verifyOtp(string $phoneRaw, string $otpDigits, Request $request): array
    {
        $phone = $this->normalizedPhone($phoneRaw);
        if ($phone === '') {
            return ['valid' => false, 'reason' => 'invalid_phone'];
        }

        $lockKey = 'phone_otp_verify_lock:'.$phone;
        if (Cache::has($lockKey)) {
            return ['valid' => false, 'reason' => 'locked'];
        }

        /** @var PhoneOtp|null $row */
        $row = PhoneOtp::query()
            ->where('phone', $phone)
            ->where('purpose', self::PURPOSE_REGISTER_LOGIN)
            ->whereNull('verified_at')
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            return ['valid' => false, 'reason' => 'not_found'];
        }

        if ($row->expires_at->isPast()) {
            return ['valid' => false, 'reason' => 'expired'];
        }

        if ((int) $row->attempts_count >= (int) $row->max_attempts) {
            Cache::put($lockKey, true, now()->addMinutes(5));

            return ['valid' => false, 'reason' => 'max_attempts'];
        }

        $cleanOtp = preg_replace('/\D/', '', $otpDigits) ?? '';
        if (! Hash::check($cleanOtp, $row->otp_code_hash)) {
            $row->increment('attempts_count');
            if ((int) $row->fresh()->attempts_count >= (int) $row->max_attempts) {
                Cache::put($lockKey, true, now()->addMinutes(5));
            }

            return ['valid' => false, 'reason' => 'bad_code'];
        }

        $row->forceFill(['verified_at' => now()])->save();

        return ['valid' => true];
    }

    /**
     * @throws ValidationException
     */
    private function throttleSend(string $phone, Request $request): void
    {
        $ip = (string) $request->ip();
        $kPhone = 'phone_otp_send_count:'.$phone;
        $kIp    = 'phone_otp_send_ip:'.$ip;

        $phoneHits = (int) Cache::get($kPhone, 0);
        if ($phoneHits >= (int) config('saas.phone_otp_send_max_per_phone_window', 5)) {
            throw ValidationException::withMessages(['phone' => ['طلبات إرسال كثيرة لهذا الرقم. حاول لاحقاً.']]);
        }

        $ipHits = (int) Cache::get($kIp, 0);
        if ($ipHits >= (int) config('saas.phone_otp_send_max_per_ip_window', 30)) {
            throw ValidationException::withMessages(['phone' => ['طلبات إرسال كثيرة. حاول لاحقاً.']]);
        }

        Cache::put($kPhone, $phoneHits + 1, now()->addMinutes(15));
        Cache::put($kIp, $ipHits + 1, now()->addMinutes(15));
    }

    private function dispatchSms(string $phoneDigits, string $code, int $ttlSeconds): void
    {
        $fake = (string) config('saas.phone_otp_fake_plaintext', '');
        if ($fake !== '' && ! app()->environment('production')) {
            Log::warning('phone_otp.fake_plaintext_enabled', [
                'phone_suffix' => substr($phoneDigits, -4),
                'code'         => $code,
                'ttl'          => $ttlSeconds,
                'trace_id'     => app('trace_id'),
            ]);

            return;
        }

        $sid   = trim((string) config('saas.twilio_account_sid'));
        $token = trim((string) config('saas.twilio_auth_token'));
        $from  = trim((string) config('saas.twilio_sms_from'));
        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('phone_otp.sms_skipped_no_twilio', [
                'phone_suffix' => substr($phoneDigits, -4),
                'code'         => app()->environment('production') ? '[redacted]' : $code,
                'trace_id'     => app('trace_id'),
            ]);

            return;
        }

        $to = $this->toE164($phoneDigits);
        if ($to === null) {
            return;
        }

        $body = "رمز التحقق: {$code}\nصلاحية {$ttlSeconds} ثانية.";

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
            Http::asForm()->withBasicAuth($sid, $token)->timeout(15)->post($url, [
                'To'   => $to,
                'From' => $from,
                'Body' => $body,
            ]);
        } catch (\Throwable $e) {
            Log::warning('phone_otp.sms_failed', [
                'error'    => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ]);
        }
    }

    private function toE164(string $digits): ?string
    {
        $d = PhoneNormalizer::digitsOnly($digits);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '0') && strlen($d) >= 10) {
            return '+966'.substr($d, 1);
        }
        if (str_starts_with($d, '966')) {
            return '+'.$d;
        }
        if (strlen($d) === 9 && $d[0] === '5') {
            return '+966'.$d;
        }

        return '+'.$d;
    }
}
