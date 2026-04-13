<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS invoice_counter_global_seq');
        DB::statement(
            "SELECT setval(
                'invoice_counter_global_seq',
                GREATEST((SELECT COALESCE(MAX(invoice_counter), 0) FROM invoices), 1),
                true
            )"
        );
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS invoice_counter_global_seq');
    }
};

