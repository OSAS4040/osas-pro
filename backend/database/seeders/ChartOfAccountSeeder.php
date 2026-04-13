<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        $this->seedForCompany($company);
    }

    public function seedForCompany(Company $company): void
    {
        $accounts = $this->getDefaultAccounts();

        foreach ($accounts as $account) {
            ChartOfAccount::firstOrCreate(
                ['company_id' => $company->id, 'code' => $account['code']],
                array_merge($account, ['company_id' => $company->id, 'is_system' => true]),
            );
        }
    }

    public function getDefaultAccounts(): array
    {
        return [
            // ══ أصول (Assets) ══
            ['code' => '1000', 'name' => 'Current Assets',          'name_ar' => 'الأصول المتداولة',     'type' => 'asset',     'sub_type' => 'header',      'sort_order' => 10],
            ['code' => '1010', 'name' => 'Cash on Hand',            'name_ar' => 'النقدية',              'type' => 'asset',     'sub_type' => 'cash',        'sort_order' => 11],
            ['code' => '1020', 'name' => 'Bank Account',            'name_ar' => 'الحساب البنكي',        'type' => 'asset',     'sub_type' => 'bank',        'sort_order' => 12],
            ['code' => '1030', 'name' => 'Wallet — Cash',           'name_ar' => 'محفظة نقدية',          'type' => 'asset',     'sub_type' => 'wallet',      'sort_order' => 13],
            ['code' => '1040', 'name' => 'Wallet — Promotional',    'name_ar' => 'محفظة ترويجية',        'type' => 'asset',     'sub_type' => 'wallet',      'sort_order' => 14],
            ['code' => '1050', 'name' => 'Wallet — Reserved',       'name_ar' => 'محفظة محجوزة',         'type' => 'asset',     'sub_type' => 'wallet',      'sort_order' => 15],
            ['code' => '1200', 'name' => 'Accounts Receivable',     'name_ar' => 'ذمم مدينة',            'type' => 'asset',     'sub_type' => 'receivable',  'sort_order' => 20],
            ['code' => '1210', 'name' => 'Fleet Receivable',        'name_ar' => 'ذمم أسطول',            'type' => 'asset',     'sub_type' => 'receivable',  'sort_order' => 21],
            ['code' => '1300', 'name' => 'Inventory',               'name_ar' => 'المخزون',              'type' => 'asset',     'sub_type' => 'inventory',   'sort_order' => 30],
            ['code' => '1400', 'name' => 'Prepaid Expenses',        'name_ar' => 'مصروفات مدفوعة مقدماً', 'type' => 'asset',    'sub_type' => 'prepaid',     'sort_order' => 40],

            // ══ التزامات (Liabilities) ══
            ['code' => '2000', 'name' => 'Current Liabilities',     'name_ar' => 'الالتزامات المتداولة',  'type' => 'liability', 'sub_type' => 'header',     'sort_order' => 50],
            ['code' => '2100', 'name' => 'Accounts Payable',        'name_ar' => 'ذمم دائنة',            'type' => 'liability', 'sub_type' => 'payable',    'sort_order' => 51],
            ['code' => '2200', 'name' => 'Deferred Revenue',        'name_ar' => 'إيرادات مؤجلة',        'type' => 'liability', 'sub_type' => 'deferred',   'sort_order' => 52],
            ['code' => '2300', 'name' => 'VAT Payable (Output)',    'name_ar' => 'ضريبة القيمة المضافة', 'type' => 'liability', 'sub_type' => 'vat',        'sort_order' => 53],
            ['code' => '2310', 'name' => 'VAT Receivable (Input)',  'name_ar' => 'ضريبة قيمة مضافة مدخلات', 'type' => 'asset', 'sub_type' => 'vat',        'sort_order' => 54],

            // ══ حقوق الملكية (Equity) ══
            ['code' => '3000', 'name' => 'Owner Equity',            'name_ar' => 'حقوق المالك',          'type' => 'equity',    'sub_type' => 'equity',     'sort_order' => 60],
            ['code' => '3100', 'name' => 'Retained Earnings',       'name_ar' => 'أرباح محتجزة',         'type' => 'equity',    'sub_type' => 'retained',   'sort_order' => 61],

            // ══ الإيرادات (Revenue) ══
            ['code' => '4000', 'name' => 'Revenue',                 'name_ar' => 'الإيرادات',            'type' => 'revenue',   'sub_type' => 'header',     'sort_order' => 70],
            ['code' => '4100', 'name' => 'Service Revenue',         'name_ar' => 'إيرادات الخدمات',      'type' => 'revenue',   'sub_type' => 'service',    'sort_order' => 71],
            ['code' => '4200', 'name' => 'Product Sales Revenue',   'name_ar' => 'إيرادات مبيعات المنتجات', 'type' => 'revenue', 'sub_type' => 'product',  'sort_order' => 72],
            ['code' => '4300', 'name' => 'Subscription Revenue',    'name_ar' => 'إيرادات الاشتراكات',   'type' => 'revenue',   'sub_type' => 'subscription', 'sort_order' => 73],

            // ══ المصروفات (Expenses) ══
            ['code' => '5000', 'name' => 'Expenses',                'name_ar' => 'المصروفات',            'type' => 'expense',   'sub_type' => 'header',     'sort_order' => 80],
            ['code' => '5100', 'name' => 'Cost of Goods Sold',      'name_ar' => 'تكلفة البضاعة المباعة', 'type' => 'expense',  'sub_type' => 'cogs',       'sort_order' => 81],
            ['code' => '5200', 'name' => 'Sales Discounts',         'name_ar' => 'خصومات المبيعات',      'type' => 'expense',   'sub_type' => 'discount',   'sort_order' => 82],
            ['code' => '5300', 'name' => 'Operating Expenses',      'name_ar' => 'مصروفات تشغيلية',      'type' => 'expense',   'sub_type' => 'operating',  'sort_order' => 83],
            ['code' => '5400', 'name' => 'Payroll Expenses',        'name_ar' => 'مصروفات الرواتب',      'type' => 'expense',   'sub_type' => 'payroll',    'sort_order' => 84],
        ];
    }
}
