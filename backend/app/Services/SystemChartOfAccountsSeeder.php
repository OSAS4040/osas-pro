<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Idempotent system Chart of Accounts for each tenant (company_id scoped).
 */
final class SystemChartOfAccountsSeeder
{
    /**
     * @return list<array{code: string, name: string, name_ar: string|null, type: string, sub_type: string|null, sort_order: int}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code'       => '1010',
                'name'       => 'Cash on Hand',
                'name_ar'    => 'النقدية في الصندوق',
                'type'       => 'asset',
                'sub_type'   => 'cash',
                'sort_order' => 10,
            ],
            [
                'code'       => '1020',
                'name'       => 'Cash / Bank – Wallet funding',
                'name_ar'    => 'نقد / بنك – تمويل المحافظ',
                'type'       => 'asset',
                'sub_type'   => 'cash',
                'sort_order' => 11,
            ],
            [
                'code'       => '1200',
                'name'       => 'Accounts Receivable',
                'name_ar'    => 'الذمم المدينة',
                'type'       => 'asset',
                'sub_type'   => 'receivable',
                'sort_order' => 20,
            ],
            [
                'code'       => '2300',
                'name'       => 'VAT Payable',
                'name_ar'    => 'ضريبة القيمة المضافة مستحقة',
                'type'       => 'liability',
                'sub_type'   => 'vat',
                'sort_order' => 40,
            ],
            [
                'code'       => '2410',
                'name'       => 'Fleet Main Wallet Deposits',
                'name_ar'    => 'ودائع محفظة الأسطول الرئيسية',
                'type'       => 'liability',
                'sub_type'   => 'wallet',
                'sort_order' => 55,
            ],
            [
                'code'       => '2420',
                'name'       => 'Vehicle Wallet Deposits',
                'name_ar'    => 'ودائع محافظ المركبات',
                'type'       => 'liability',
                'sub_type'   => 'wallet',
                'sort_order' => 56,
            ],
            [
                'code'       => '4100',
                'name'       => 'Service / Sales Revenue',
                'name_ar'    => 'إيرادات الخدمات / المبيعات',
                'type'       => 'revenue',
                'sub_type'   => 'service',
                'sort_order' => 70,
            ],
            [
                'code'       => '5200',
                'name'       => 'Cost of Goods Sold',
                'name_ar'    => 'تكلفة البضاعة المباعة',
                'type'       => 'expense',
                'sub_type'   => 'cogs',
                'sort_order' => 80,
            ],
        ];
    }

    public static function ensureForCompany(int $companyId): void
    {
        $now = now();

        foreach (self::definitions() as $account) {
            $exists = DB::table('chart_of_accounts')
                ->where('company_id', $companyId)
                ->where('code', $account['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('chart_of_accounts')->insert([
                'company_id'  => $companyId,
                'code'        => $account['code'],
                'name'        => $account['name'],
                'name_ar'     => $account['name_ar'],
                'type'        => $account['type'],
                'sub_type'    => $account['sub_type'],
                'parent_id'   => null,
                'is_active'   => true,
                'is_system'   => true,
                'description' => null,
                'sort_order'  => $account['sort_order'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }
}
