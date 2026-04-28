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

        DB::unprepared(<<<'SQL'
        DO $$
        DECLARE r record;
        DECLARE seq_name text;
        BEGIN
            FOR r IN
                SELECT company_id, COALESCE(last_value, 0) AS last_value
                FROM invoice_counters
            LOOP
                seq_name := format('invoice_counter_company_%s_seq', r.company_id);
                EXECUTE format('CREATE SEQUENCE IF NOT EXISTS %I', seq_name);
                EXECUTE format('SELECT setval(%L::regclass, GREATEST(%s, 1), true)', seq_name, r.last_value);
            END LOOP;
        END $$;
        SQL);
    }

    public function down(): void
    {
        // Keep per-company sequences to avoid accidental invoice counter regression.
    }
};

