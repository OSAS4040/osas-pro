<?php

namespace App\Services\Messaging;

use App\Models\Company;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WhatsAppOutboundService
{
    public const TRIGGER_DELIVERED = 'wo_delivered';

    public const TRIGGER_COMPLETED = 'wo_completed';

    public static function triggerKey(string $kind): string
    {
        return match ($kind) {
            'delivered' => self::TRIGGER_DELIVERED,
            'completed' => self::TRIGGER_COMPLETED,
            default => '',
        };
    }

    public function sendOperationalWorkOrderMessage(WorkOrder $workOrder, string $kind): void
    {
        if (! in_array($kind, ['delivered', 'completed'], true)) {
            return;
        }

        $company = Company::query()->find($workOrder->company_id);
        if (! $company) {
            return;
        }

        $whatsapp = is_array($company->settings) ? ($company->settings['whatsapp'] ?? []) : [];
        $triggers = is_array($whatsapp['triggers'] ?? null) ? $whatsapp['triggers'] : [];
        $triggerKey = self::triggerKey($kind);

        if (($triggers[$triggerKey] ?? false) !== true) {
            Log::debug('whatsapp.work_order.skip_trigger_disabled', [
                'work_order_id' => $workOrder->id,
                'company_id'    => $company->id,
                'kind'          => $kind,
                'trigger'       => $triggerKey,
            ]);

            return;
        }

        $phone = $this->resolveDestinationPhone($workOrder);
        if ($phone === null) {
            Log::info('whatsapp.work_order.skip_no_phone', [
                'work_order_id' => $workOrder->id,
                'company_id'    => $company->id,
                'kind'          => $kind,
            ]);

            return;
        }

        $body = $this->buildMessageBody($workOrder, $company, $kind);
        $provider = (string) ($whatsapp['provider'] ?? 'platform');

        match ($provider) {
            'twilio' => $this->sendViaTwilio($company, $phone, $body, $workOrder->id),
            'custom_api' => $this->sendViaCustomApi($company, $phone, $body, $workOrder->id),
            default => Log::info('whatsapp.work_order.skip_platform_provider', [
                'work_order_id' => $workOrder->id,
                'company_id'    => $company->id,
                'provider'      => $provider,
            ]),
        };
    }

    private function resolveDestinationPhone(WorkOrder $wo): ?string
    {
        $raw = trim((string) ($wo->driver_phone ?? ''));
        if ($raw === '') {
            $wo->loadMissing('customer');
            $raw = trim((string) ($wo->customer?->phone ?? ''));
        }
        if ($raw === '') {
            return null;
        }

        return $this->toE164Digits($raw);
    }

    private function toE164Digits(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }
        if (str_starts_with($digits, '966')) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
            return '966'.substr($digits, 1);
        }
        if (strlen($digits) === 9 && ($digits[0] ?? '') === '5') {
            return '966'.$digits;
        }
        if (strlen($digits) >= 10) {
            return $digits;
        }

        return null;
    }

    private function buildMessageBody(WorkOrder $wo, Company $company, string $kind): string
    {
        $wo->loadMissing('vehicle');
        $plate = $wo->vehicle?->plate_number ?? '-';
        $name = $company->name;
        $ref = $wo->order_number;

        return match ($kind) {
            'delivered' => "مرحباً، تم تسليم مركبتك ({$plate}). رقم أمر العمل: {$ref}. شكراً لثقتكم — {$name}",
            default => "تنبيه: أصبحت مركبتك ({$plate}) جاهزة للاستلام. أمر عمل: {$ref}. — {$name}",
        };
    }

    private function sendViaTwilio(Company $company, string $toDigits, string $body, int $workOrderId): void
    {
        $whatsapp = is_array($company->settings) ? ($company->settings['whatsapp'] ?? []) : [];
        $config = is_array($whatsapp['config'] ?? null) ? $whatsapp['config'] : [];

        $sid = trim((string) ($config['twilio_sid'] ?? ''));
        $token = trim((string) ($config['twilio_token'] ?? ''));
        $from = trim((string) ($config['twilio_from'] ?? ''));

        if ($sid === '' || $token === '' || $from === '') {
            Log::warning('whatsapp.work_order.skip_twilio_incomplete', [
                'work_order_id' => $workOrderId,
                'company_id'    => $company->id,
            ]);

            return;
        }

        $fromWa = $this->formatTwilioWhatsAppAddress($from);
        $toWa = $this->formatTwilioWhatsAppAddress($toDigits);

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(15)
                ->connectTimeout(5)
                ->post($url, [
                    'From' => $fromWa,
                    'To'   => $toWa,
                    'Body' => $body,
                ]);

            if ($response->successful()) {
                Log::info('whatsapp.work_order.twilio_sent', [
                    'work_order_id' => $workOrderId,
                    'company_id'    => $company->id,
                    'to_suffix'     => $this->maskPhoneSuffix($toDigits),
                    'http_status'   => $response->status(),
                ]);

                return;
            }

            throw new \RuntimeException('Twilio HTTP '.$response->status());
        } catch (\Throwable $e) {
            Log::error('whatsapp.work_order.twilio_error', [
                'work_order_id' => $workOrderId,
                'company_id'    => $company->id,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function sendViaCustomApi(Company $company, string $toDigits, string $body, int $workOrderId): void
    {
        $whatsapp = is_array($company->settings) ? ($company->settings['whatsapp'] ?? []) : [];
        $config = is_array($whatsapp['config'] ?? null) ? $whatsapp['config'] : [];

        $url = trim((string) ($config['custom_api_url'] ?? ''));
        $key = trim((string) ($config['custom_api_key'] ?? ''));
        $from = trim((string) ($config['custom_from'] ?? ''));

        if ($url === '') {
            Log::warning('whatsapp.work_order.skip_custom_api_no_url', [
                'work_order_id' => $workOrderId,
                'company_id'    => $company->id,
            ]);

            return;
        }

        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($key !== '') {
            $headers['Authorization'] = 'Bearer '.$key;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->connectTimeout(5)
                ->post($url, [
                    'to'            => $toDigits,
                    'from'          => $from,
                    'body'          => $body,
                    'work_order_id' => $workOrderId,
                ]);

            if ($response->successful()) {
                Log::info('whatsapp.work_order.custom_api_sent', [
                    'work_order_id' => $workOrderId,
                    'company_id'    => $company->id,
                    'to_suffix'     => $this->maskPhoneSuffix($toDigits),
                    'http_status'   => $response->status(),
                ]);

                return;
            }

            throw new \RuntimeException('Custom WhatsApp API HTTP '.$response->status());
        } catch (\Throwable $e) {
            Log::error('whatsapp.work_order.custom_api_error', [
                'work_order_id' => $workOrderId,
                'company_id'    => $company->id,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function formatTwilioWhatsAppAddress(string $raw): string
    {
        if (str_starts_with($raw, 'whatsapp:')) {
            return $raw;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return 'whatsapp:+'.$digits;
    }

    private function maskPhoneSuffix(string $digits): string
    {
        if (strlen($digits) <= 4) {
            return '****';
        }

        return '***'.substr($digits, -4);
    }

    /**
     * رسالة يدوية (مثل مشاركة أمر العمل) — لا يعتمد على تفعيل triggers الإشعارات التشغيلية.
     */
    public function sendManualTextToDriverPhone(WorkOrder $workOrder, string $body): void
    {
        $company = Company::query()->find($workOrder->company_id);
        if (! $company) {
            return;
        }

        $raw = trim((string) ($workOrder->driver_phone ?? ''));
        if ($raw === '') {
            throw new \DomainException('لا يوجد رقم سائق مسجّل في أمر العمل.');
        }

        $phone = $this->toE164Digits($raw);
        if ($phone === null) {
            throw new \DomainException('رقم السائق غير صالح.');
        }

        $whatsapp = is_array($company->settings) ? ($company->settings['whatsapp'] ?? []) : [];
        $provider = (string) ($whatsapp['provider'] ?? 'platform');

        match ($provider) {
            'twilio' => $this->sendViaTwilio($company, $phone, $body, $workOrder->id),
            'custom_api' => $this->sendViaCustomApi($company, $phone, $body, $workOrder->id),
            default => throw new \DomainException('لم يتم ضبط مزوّد واتساب (Twilio أو واجهة مخصّصة) في إعدادات الشركة.'),
        };
    }
}
