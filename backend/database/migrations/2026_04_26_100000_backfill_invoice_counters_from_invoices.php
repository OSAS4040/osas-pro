<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            INSERT INTO invoice_counters (company_id, last_value, created_at, updated_at)
            SELECT company_id, COALESCE(MAX(invoice_counter), 0), NOW(), NOW()
            FROM invoices
            GROUP BY company_id
            ON CONFLICT (company_id) DO UPDATE SET
                last_value = GREATEST(invoice_counters.last_value, EXCLUDED.last_value),
                updated_at = NOW()
            SQL);
    }

    public function down(): void
    {
        // Intentionally empty: do not delete seeded counter rows.
    }
};
