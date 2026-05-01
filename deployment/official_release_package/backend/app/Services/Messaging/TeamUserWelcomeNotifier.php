<?php

namespace App\Services\Messaging;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamUserWelcomeNotifier
{
    /**
     * @return array{sms: bool, whatsapp: bool}
     */
    public function send(User $user, string $plainPassword): array
    {
        $user->loadMissing('company');
        $company = $user->company;
        if (! $company instanceof Company) {
            return ['sms' => false, 'whatsapp' => false];
        }

        $phone = $this->normalizePhone((string) ($user->phone ?? ''));
        if ($phone === null) {
            Log::info('team_user_welcome.skip_no_phone', [
                'user_id' => $user->id,
                'trace_id' => app('trace_id'),
            ]);

            return ['sms' => false, 'whatsapp' => false];
        }

        $message = $this->buildWelcomeMessage($user, $plainPassword);

        $whatsapp = $this->sendWhatsApp($company, $phone, $message, (int) $user->id);
        $sms = false;
        if (! $whatsapp) {
            $sms = $this->sendSms($phone, $message, (int) $user->id);
        }

        return ['sms' => $sms, 'whatsapp' => $whatsapp];
    }

    private function buildWelcomeMessage(User $user, string $plainPassword): string
    {
        $loginUrl = rtrim((string) config('app.url', ''), '/');
        if ($loginUrl === '') {
            $loginUrl = 'https://portal.osuspro.com/login';
        }

        $name = trim((string) $user->name) !== '' ? (string) $user->name : 'مستخدم';

        return implode("\n", [
            "مرحبًا {$name}",
            'تم تفعيل حسابك في منصة أسس برو.',
            "رابط الدخول: {$loginUrl}",
            "البريد الإلكتروني: {$user->email}",
            "كلمة المرور: {$plainPassword}",
            'يرجى تغيير كلمة المرور بعد أول تسجيل دخول.',
        ]);
    }

    private function sendSms(string $phone, string $body, int $userId): bool
    {
        $sid = trim((string) config('saas.twilio_account_sid'));
        $token = trim((string) config('saas.twilio_auth_token'));
        $from = trim((string) config('saas.twilio_sms_from'));
        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('team_user_welcome.sms_skipped_no_twilio_config', [
                'user_id' => $userId,
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }

        try {
            $res = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => '+'.$phone,
                    'Body' => $body,
                ]);

            if (! $res->successful()) {
                Log::warning('team_user_welcome.sms_http_error', [
                    'user_id' => $userId,
                    'status' => $res->status(),
                    'body' => $res->body(),
                    'trace_id' => app('trace_id'),
                ]);

                return false;
            }

            Log::info('team_user_welcome.sms_sent', [
                'user_id' => $userId,
                'trace_id' => app('trace_id'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('team_user_welcome.sms_exception', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }
    }

    private function sendWhatsApp(Company $company, string $phone, string $body, int $userId): bool
    {
        $whatsapp = is_array($company->settings) ? ($company->settings['whatsapp'] ?? []) : [];
        $provider = strtolower(trim((string) ($whatsapp['provider'] ?? '')));
        if ($provider === '') {
            return false;
        }

        if ($provider === 'twilio') {
            return $this->sendWhatsAppViaTwilio($whatsapp, $phone, $body, $userId);
        }

        if ($provider === 'custom_api') {
            return $this->sendWhatsAppViaCustomApi($whatsapp, $phone, $body, $userId);
        }

        return false;
    }

    private function sendWhatsAppViaTwilio(array $whatsapp, string $phone, string $body, int $userId): bool
    {
        $config = is_array($whatsapp['config'] ?? null) ? $whatsapp['config'] : [];
        $sid = trim((string) ($config['twilio_account_sid'] ?? config('saas.twilio_account_sid')));
        $token = trim((string) ($config['twilio_auth_token'] ?? config('saas.twilio_auth_token')));
        $from = trim((string) ($config['twilio_whatsapp_from'] ?? ''));

        if ($sid === '' || $token === '' || $from === '') {
            return false;
        }

        try {
            $res = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $this->formatTwilioWhatsAppAddress($from),
                    'To' => $this->formatTwilioWhatsAppAddress($phone),
                    'Body' => $body,
                ]);

            if (! $res->successful()) {
                Log::warning('team_user_welcome.whatsapp_twilio_http_error', [
                    'user_id' => $userId,
                    'status' => $res->status(),
                    'trace_id' => app('trace_id'),
                ]);

                return false;
            }

            Log::info('team_user_welcome.whatsapp_twilio_sent', [
                'user_id' => $userId,
                'trace_id' => app('trace_id'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('team_user_welcome.whatsapp_twilio_exception', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }
    }

    private function sendWhatsAppViaCustomApi(array $whatsapp, string $phone, string $body, int $userId): bool
    {
        $config = is_array($whatsapp['config'] ?? null) ? $whatsapp['config'] : [];
        $url = trim((string) ($config['api_url'] ?? ''));
        if ($url === '') {
            return false;
        }

        $token = trim((string) ($config['api_token'] ?? ''));
        $headers = ['Accept' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $res = Http::withHeaders($headers)
                ->timeout(8)
                ->post($url, [
                    'to' => '+'.$phone,
                    'message' => $body,
                    'context' => 'team_user_welcome',
                    'user_id' => $userId,
                ]);

            if (! $res->successful()) {
                Log::warning('team_user_welcome.whatsapp_custom_http_error', [
                    'user_id' => $userId,
                    'status' => $res->status(),
                    'trace_id' => app('trace_id'),
                ]);

                return false;
            }

            Log::info('team_user_welcome.whatsapp_custom_sent', [
                'user_id' => $userId,
                'trace_id' => app('trace_id'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('team_user_welcome.whatsapp_custom_exception', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ]);

            return false;
        }
    }

    private function formatTwilioWhatsAppAddress(string $raw): string
    {
        if (str_starts_with($raw, 'whatsapp:')) {
            return $raw;
        }

        $normalized = $this->normalizePhone($raw);

        return 'whatsapp:+'.($normalized ?? preg_replace('/\D+/', '', $raw));
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '966'.substr($digits, 1);
        }
        if (strlen($digits) < 9) {
            return null;
        }

        return $digits;
    }
}
