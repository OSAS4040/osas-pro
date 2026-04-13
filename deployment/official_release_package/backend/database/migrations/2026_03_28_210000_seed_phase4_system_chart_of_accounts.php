<?php

use App\Services\SystemChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Idempotent system COA rows per company (1010, 1020, 1200, 2300, 2410, 2420, 4100, 5200).
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
        // Intentionally no destructive down: removing system accounts can break historical journals.
    }
};
