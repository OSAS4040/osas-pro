<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS payment_orders_status_wave2_idx ON payment_orders (status)');
        DB::statement('CREATE INDEX IF NOT EXISTS bank_transactions_is_matched_wave2_idx ON bank_transactions (is_matched)');
        DB::statement('CREATE INDEX IF NOT EXISTS subscriptions_company_id_wave2_idx ON subscriptions (company_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS wallet_transactions_company_id_wave2_idx ON wallet_transactions (company_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payment_orders_status_wave2_idx');
        DB::statement('DROP INDEX IF EXISTS bank_transactions_is_matched_wave2_idx');
        DB::statement('DROP INDEX IF EXISTS subscriptions_company_id_wave2_idx');
        DB::statement('DROP INDEX IF EXISTS wallet_transactions_company_id_wave2_idx');
    }
};

