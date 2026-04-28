<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('journal_entries', 'posting_idempotency_key')) {
                $table->string('posting_idempotency_key', 191)->nullable()->after('trace_id');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS ux_journal_entries_company_posting_idem ON journal_entries (company_id, posting_idempotency_key) WHERE posting_idempotency_key IS NOT NULL'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS journal_entries_company_source_lookup_idx ON journal_entries (company_id, source_type, source_id)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS journal_entries_company_created_at_idx ON journal_entries (company_id, created_at)'
            );
            DB::statement(
                'CREATE INDEX IF NOT EXISTS journal_entry_lines_account_lookup_idx ON journal_entry_lines (account_id, journal_entry_id)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS ux_journal_entries_company_posting_idem');
            DB::statement('DROP INDEX IF EXISTS journal_entries_company_source_lookup_idx');
            DB::statement('DROP INDEX IF EXISTS journal_entries_company_created_at_idx');
            DB::statement('DROP INDEX IF EXISTS journal_entry_lines_account_lookup_idx');
        }

        Schema::table('journal_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('journal_entries', 'posting_idempotency_key')) {
                $table->dropColumn('posting_idempotency_key');
            }
        });
    }
};
