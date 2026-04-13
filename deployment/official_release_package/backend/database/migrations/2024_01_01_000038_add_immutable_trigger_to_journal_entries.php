<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Prevent UPDATE on journal_entries (append-only ledger)
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_journal_update()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'Journal entries are immutable. Use reversal entries to correct errors.';
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_prevent_journal_update
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_journal_update();
        ");

        // Prevent DELETE on journal_entries
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_journal_delete()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'Journal entries cannot be deleted. They are permanent financial records.';
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_prevent_journal_delete
            BEFORE DELETE ON journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_journal_delete();
        ");

        // Prevent UPDATE on journal_entry_lines
        DB::unprepared("
            CREATE TRIGGER trg_prevent_journal_lines_update
            BEFORE UPDATE ON journal_entry_lines
            FOR EACH ROW
            EXECUTE FUNCTION prevent_journal_update();
        ");

        // Prevent DELETE on journal_entry_lines
        DB::unprepared("
            CREATE TRIGGER trg_prevent_journal_lines_delete
            BEFORE DELETE ON journal_entry_lines
            FOR EACH ROW
            EXECUTE FUNCTION prevent_journal_delete();
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_journal_lines_delete ON journal_entry_lines;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_journal_lines_update ON journal_entry_lines;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_journal_delete ON journal_entries;');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_prevent_journal_update ON journal_entries;');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_journal_delete();');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_journal_update();');
    }
};
