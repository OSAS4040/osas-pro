<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\SupplierContract;
use App\Models\User;
use App\Services\AlertService;
use App\Support\TenantBusinessFeatures;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * تنبيه داخلي (لوحة + اختياري بريد/واتساب من إعدادات الشركة) قبل انتهاء عقد المورد.
 * منفصل عن سجل المستندات العام لتفادي خلط السياسات.
 */
class SendSupplierContractExpiryNotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(AlertService $alerts): void
    {
        $today = now()->startOfDay();

        try {
            SupplierContract::query()
                ->withoutGlobalScope('tenant')
                ->whereNotNull('expires_at')
                ->with(['supplier:id,name', 'createdBy:id,name'])
                ->chunkById(100, function ($contracts) use ($alerts, $today) {
                    foreach ($contracts as $contract) {
                        try {
                            $company = Company::query()->find($contract->company_id);
                            if ($company === null || ! TenantBusinessFeatures::isEnabled($company, 'supplier_contract_mgmt')) {
                                continue;
                            }

                            $settings = is_array($company->settings) ? $company->settings : [];
                            $cfg      = is_array($settings['supplier_contract_notifications'] ?? null)
                                ? $settings['supplier_contract_notifications']
                                : [];
                            /** @var list<int> $reminderDays */
                            $reminderDays = array_values(array_filter(
                                array_map('intval', (array) ($cfg['reminder_days'] ?? [30, 7, 1])),
                                fn ($n) => $n >= 0
                            ));

                            if ($reminderDays === []) {
                                $reminderDays = [30, 7, 1];
                            }

                            $expDate = $contract->expires_at->copy()->startOfDay();
                            $diff    = $today->diffInDays($expDate, false);

                            if (! in_array($diff, $reminderDays, true) && $diff >= 0) {
                                continue;
                            }
                            if ($diff < 0 && ! in_array(0, $reminderDays, true)) {
                                continue;
                            }

                            $supplierName = $contract->supplier?->name ?? 'مورد';
                            $title        = (string) $contract->title;
                            $message      = $diff < 0
                                ? "انتهى عقد المورد «{$supplierName}»: {$title}"
                                : "تنبيه عقد مورد: «{$title}» لـ «{$supplierName}» ينتهي بعد {$diff} يوم";

                            if (($cfg['in_app'] ?? true) === true) {
                                $recipients = User::query()
                                    ->withoutGlobalScope('tenant')
                                    ->where('company_id', $company->id)
                                    ->whereIn('role', ['owner', 'manager'])
                                    ->get(['id']);

                                if ($recipients->isEmpty()) {
                                    $alerts->fire(
                                        $company->id,
                                        'supplier_contract.expiry',
                                        $message,
                                        'warning',
                                        SupplierContract::class,
                                        $contract->id,
                                        ['contract_id' => $contract->id, 'supplier_id' => $contract->supplier_id],
                                    );
                                } else {
                                    foreach ($recipients as $u) {
                                        $alerts->fire(
                                            $company->id,
                                            'supplier_contract.expiry',
                                            $message,
                                            'warning',
                                            SupplierContract::class,
                                            $contract->id,
                                            ['contract_id' => $contract->id, 'supplier_id' => $contract->supplier_id],
                                            (int) $u->id,
                                        );
                                    }
                                }
                            }

                            if (($cfg['email'] ?? false) === true && ! empty($company->email)) {
                                try {
                                    Mail::raw($message."\n\nWorkshopOS", function ($m) use ($company) {
                                        $m->to($company->email)->subject('تنبيه عقد مورد');
                                    });
                                } catch (\Throwable) {
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
                                                'to'      => $phone,
                                                'message' => $message,
                                                'api_key' => (string) ($wa['custom_api_key'] ?? ''),
                                            ]);
                                        } catch (\Throwable) {
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                });
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
