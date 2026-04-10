<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SendDocumentExpiryNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(AlertService $alerts): void
    {
        $today = now()->startOfDay();

        try {
            Company::query()->chunkById(100, function ($companies) use ($alerts, $today) {
                foreach ($companies as $company) {
                    try {
                        $settings = is_array($company->settings) ? $company->settings : [];
                        $docs = is_array($settings['documents_registry'] ?? null) ? $settings['documents_registry'] : [];
                        $cfg = is_array($settings['documents_notifications'] ?? null) ? $settings['documents_notifications'] : [];
                        $reminderDays = array_values(array_filter(array_map('intval', (array) ($cfg['reminder_days'] ?? [30, 7, 1])), fn ($n) => $n >= 0));

                        if ($docs === [] || $reminderDays === []) {
                            continue;
                        }

                        foreach ($docs as $doc) {
                            $exp = isset($doc['expires']) ? (string) $doc['expires'] : '';
                            if ($exp === '') {
                                continue;
                            }

                            try {
                                $expDate = \Illuminate\Support\Carbon::parse($exp)->startOfDay();
                            } catch (\Throwable) {
                                continue;
                            }

                            $diff = $today->diffInDays($expDate, false);
                            if (!in_array($diff, $reminderDays, true) && $diff >= 0) {
                                continue;
                            }
                            if ($diff < 0 && !in_array(0, $reminderDays, true)) {
                                continue;
                            }

                            $title = (string) ($doc['title'] ?? 'مستند');
                            $reference = (string) ($doc['reference'] ?? '');
                            $message = $diff < 0
                                ? "انتهت صلاحية المستند {$title}" . ($reference !== '' ? " ({$reference})" : '')
                                : "تنبيه: المستند {$title} ينتهي بعد {$diff} يوم" . ($reference !== '' ? " ({$reference})" : '');

                            if (($cfg['in_app'] ?? true) === true) {
                                $recipients = User::query()
                                    ->where('company_id', $company->id)
                                    ->whereIn('role', ['owner', 'manager', 'admin'])
                                    ->get(['id']);
                                if ($recipients->isEmpty()) {
                                    $alerts->fire($company->id, 'document.expiry', $message, 'warning', Company::class, $company->id, ['document' => $doc]);
                                } else {
                                    foreach ($recipients as $u) {
                                        $alerts->fire($company->id, 'document.expiry', $message, 'warning', Company::class, $company->id, ['document' => $doc], (int) $u->id);
                                    }
                                }
                            }

                            if (($cfg['email'] ?? false) === true && !empty($company->email)) {
                                try {
                                    Mail::raw($message . "\n\nWorkshopOS", function ($m) use ($company) {
                                        $m->to($company->email)->subject('تنبيه صلاحية مستند');
                                    });
                                } catch (\Throwable) {
                                    // ignore failures, keep job resilient
                                }
                            }

                            if (($cfg['whatsapp'] ?? false) === true) {
                                $wa = is_array($settings['whatsapp'] ?? null) ? $settings['whatsapp'] : [];
                                $phone = (string) ($company->phone ?? '');
                                if ($phone !== '' && ($wa['provider'] ?? '') === 'custom_api') {
                                    $url = (string) ($wa['custom_api_url'] ?? '');
                                    if ($url !== '') {
                                        try {
                                            Http::timeout(6)->post($url, [
                                                'to' => $phone,
                                                'message' => $message,
                                                'api_key' => (string) ($wa['custom_api_key'] ?? ''),
                                            ]);
                                        } catch (\Throwable) {
                                            // ignore provider errors
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        report($e);
                        // never fail entire daily sweep because of one company payload
                    }
                }
            });
        } catch (\Throwable $e) {
            report($e);
            // keep scheduler healthy; retry on next scheduled run.
        }
    }
}

