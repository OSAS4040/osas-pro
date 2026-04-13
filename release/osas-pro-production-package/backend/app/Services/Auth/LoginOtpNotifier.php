<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class LoginOtpNotifier
{
    /**
     * @return array{sms: bool, email: bool}
     */
    public function deliver(User $user, string $code, int $ttlSeconds): array
    {
        $channel = strtolower((string) config('saas.login_otp_channel', 'email'));
        if (! in_array($channel, ['email', 'sms', 'both'], true)) {
            $channel = 'email';
        }

        $smsOk = false;
        $mailOk = false;

        if (in_array($channel, ['sms', 'both'], true)) {
            $smsOk = $this->sendSms($user, $code, $ttlSeconds);
        }

        if (in_array($channel, ['email', 'both'], true) || ($channel === 'sms' && ! $smsOk)) {
            $mailOk = $this->sendEmail($user, $code, $ttlSeconds);
        }

        return ['sms' => $smsOk, 'email' => $mailOk];
    }

    public function composeMessage(string $code, int $ttl): string
    {
        return "رمز التحقق لتسجيل الدخول: {$code}\n\nالصلاحية: {$ttl} ثانية.\nإن لم تطلب ذلك، غيّر كلمة المرور وتواصل مع المسؤول.";
    }

    public function describeDelivery(bool $smsOk, bool $mailOk): string
    {
        if ($smsOk && $mailOk) {
            return 'تم إرسال رمز التحقق إلى جوالك وبريدك الإلكتروني.';
        }
        if ($smsOk) {
            return 'تم إرسال رمز التحقق إلى جوالك.';
        }

        return 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.';
    }

    private function sendEmail(User $user, string $code, int $ttl): bool
    {
        $body = $this->composeMessage($code, $ttl);
        try {
            Mail::raw(
                $body,
                function ($message) use ($user): void {
                    $message->to($user->email)
                        ->subject((string) config('app.name').' — رمز التحقق');
                }
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('login.otp_mail_failed', [
                'error'    => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }
    }

    private function sendSms(User $user, string $code, int $ttl): bool
    {
        $raw = trim((string) $user->phone);
        if ($raw === '') {
            Log::info('login.otp_sms_skipped_no_phone', [
                'user_id'  => $user->id,
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }

        $sid = trim((string) config('saas.twilio_account_sid'));
        $token = trim((string) config('saas.twilio_auth_token'));
        $from = trim((string) config('saas.twilio_sms_from'));
        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('login.otp_sms_skipped_no_twilio_config', ['trace_id' => app('trace_id')]);

            return false;
        }

        $to = $this->toE164($raw);
        if ($to === null) {
            Log::warning('login.otp_sms_invalid_phone', [
                'user_id'  => $user->id,
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }

        $msg = $this->composeMessage($code, $ttl);

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
            $res = Http::asForm()->withBasicAuth($sid, $token)->timeout(15)->post($url, [
                'To'   => $to,
                'From' => $from,
                'Body' => $msg,
            ]);
            if (! $res->successful()) {
                Log::warning('login.otp_sms_http_error', [
                    'status'   => $res->status(),
                    'trace_id' => app('trace_id'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('login.otp_sms_exception', [
                'error'    => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }
    }

    private function toE164(string $raw): ?string
    {
        $digits = PhoneNormalizer::digitsOnly($raw);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            return '+966'.substr($digits, 1);
        }
        if (str_starts_with($digits, '966')) {
            return '+'.$digits;
        }
        if (strlen($digits) === 9 && $digits[0] === '5') {
            return '+966'.$digits;
        }

        return '+'.$digits;
    }
}
