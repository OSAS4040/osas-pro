<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accounts = [
            [
                'code'       => '2410',
                'name'       => 'Fleet Main Wallet Deposits',
                'name_ar'    => 'ودائع محفظة الأسطول الرئيسية',
                'type'       => 'liability',
                'sub_type'   => 'wallet',
                'sort_order' => 55,
                'is_system'  => true,
                'is_active'  => true,
            ],
            [
                'code'       => '2420',
                'name'       => 'Vehicle Wallet Deposits',
                'name_ar'    => 'ودائع محافظ المركبات',
                'type'       => 'liability',
                'sub_type'   => 'wallet',
                'sort_order' => 56,
                'is_system'  => true,
                'is_active'  => true,
            ],
        ];

        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            foreach ($accounts as $account) {
                $exists = DB::table('chart_of_accounts')
                    ->where('company_id', $companyId)
                    ->where('code', $account['code'])
                    ->exists();

                if (! $exists) {
                    DB::table('chart_of_accounts')->insert(array_merge($account, [
                        'company_id' => $companyId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')
            ->whereIn('code', ['2410', '2420'])
            ->where('is_system', true)
            ->delete();
    }
};
