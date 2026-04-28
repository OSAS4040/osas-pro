<?php

declare(strict_types=1);

namespace App\Support\Wallet;

use App\Models\Company;

/**
 * Resolves bank accounts shown on wallet top-up transfer instruction PDFs.
 * Primary: company.settings.wallet_treasury_accounts[]
 * Fallback: companies.bank_name / companies.iban when no custom list.
 *
 * @phpstan-type TreasuryRow array{bank_name: string, iban: ?string, account_number: ?string, beneficiary_label: ?string}
 */
final class WalletTreasuryAccountsResolver
{
    /**
     * @return list<TreasuryRow>
     */
    public static function forCompany(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $rows = $settings['wallet_treasury_accounts'] ?? null;
        $out = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $bankName = trim((string) ($r['bank_name'] ?? ''));
                if ($bankName === '') {
                    continue;
                }
                $iban = trim((string) ($r['iban'] ?? ''));
                $accountNumber = trim((string) ($r['account_number'] ?? ''));
                $beneficiaryLabel = trim((string) ($r['beneficiary_label'] ?? ''));

                $out[] = [
                    'bank_name' => $bankName,
                    'iban' => $iban !== '' ? $iban : null,
                    'account_number' => $accountNumber !== '' ? $accountNumber : null,
                    'beneficiary_label' => $beneficiaryLabel !== '' ? $beneficiaryLabel : null,
                ];
            }
        }

        if ($out !== []) {
            return $out;
        }

        $iban = trim((string) ($company->getAttribute('iban') ?? ''));
        $bankName = trim((string) ($company->getAttribute('bank_name') ?? ''));
        if ($iban !== '' || $bankName !== '') {
            $out[] = [
                'bank_name' => $bankName !== '' ? $bankName : 'حساب المنشأة',
                'iban' => $iban !== '' ? $iban : null,
                'account_number' => null,
                'beneficiary_label' => null,
            ];
        }

        return $out;
    }
}
