<?php

use App\Services\SystemChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: inserts 1200 Accounts Receivable (and any other missing system COA rows) per company.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            SystemChartOfAccountsSeeder::ensureForCompany((int) $companyId);
        }
    }

    public function down(): void
    {
        // Intentionally no destructive down.
    }
};
