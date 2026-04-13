<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompanyReceivableEntryType;
use App\Models\Company;
use App\Models\CompanyReceivableLedger;
use Illuminate\Support\Facades\DB;

final class CreditLimitService
{
    /**
     * Net open receivable exposure for company (charges − reversals).
     */
    public function netOpenExposure(int $companyId): string
    {
        $net = CompanyReceivableLedger::query()
            ->where('company_id', $companyId)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END),0)
                - COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END),0)
                AS net
            ', [CompanyReceivableEntryType::Charge->value, CompanyReceivableEntryType::Reversal->value])
            ->value('net');

        return (string) ($net ?? '0');
    }

    public function assertWithinLimit(Company $company, string $additionalAmount): void
    {
        if ($company->credit_limit === null) {
            return;
        }

        $limit = (string) $company->credit_limit;
        $net = $this->netOpenExposure((int) $company->id);
        $proposed = bcadd($net, $additionalAmount, 4);

        if (bccomp($proposed, $limit, 4) > 0) {
            throw new \DomainException('تجاوز حد الائتمان المعتمد للشركة.');
        }
    }

    public function refreshRunningBalance(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            $running = '0';
            $rows = CompanyReceivableLedger::query()
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $r) {
                if ($r->entry_type === CompanyReceivableEntryType::Charge) {
                    $running = bcadd($running, (string) $r->amount, 4);
                } else {
                    $running = bcsub($running, (string) $r->amount, 4);
                }
                if ((string) $r->running_balance_company !== $running) {
                    DB::table('company_receivables_ledger')->where('id', $r->id)->update([
                        'running_balance_company' => $running,
                    ]);
                }
            }
        });
    }
}
