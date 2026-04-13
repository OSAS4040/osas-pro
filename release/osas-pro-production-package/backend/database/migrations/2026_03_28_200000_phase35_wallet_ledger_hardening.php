<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.5 — Ledger hardening: reversal uniqueness, self-FK, append-only triggers (PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS ux_wallet_txn_reversal_original ON wallet_transactions (original_transaction_id) WHERE type = \'REVERSAL\' AND original_transaction_id IS NOT NULL'
        );

        $fkExists = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ?',
            ['wallet_transactions_original_transaction_id_foreign']
        );
        if ($fkExists === null) {
            DB::statement(
                'ALTER TABLE wallet_transactions ADD CONSTRAINT wallet_transactions_original_transaction_id_foreign FOREIGN KEY (original_transaction_id) REFERENCES wallet_transactions(id) ON DELETE RESTRICT'
            );
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION wallet_transactions_forbid_mutation()
RETURNS TRIGGER AS $$
BEGIN
  RAISE EXCEPTION 'wallet_transactions is append-only: updates and deletes are not allowed';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS wallet_transactions_append_only_upd ON wallet_transactions;
CREATE TRIGGER wallet_transactions_append_only_upd
  BEFORE UPDATE ON wallet_transactions
  FOR EACH ROW EXECUTE PROCEDURE wallet_transactions_forbid_mutation();

DROP TRIGGER IF EXISTS wallet_transactions_append_only_del ON wallet_transactions;
CREATE TRIGGER wallet_transactions_append_only_del
  BEFORE DELETE ON wallet_transactions
  FOR EACH ROW EXECUTE PROCEDURE wallet_transactions_forbid_mutation();
SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS wallet_transactions_append_only_upd ON wallet_transactions;
DROP TRIGGER IF EXISTS wallet_transactions_append_only_del ON wallet_transactions;
DROP FUNCTION IF EXISTS wallet_transactions_forbid_mutation();
SQL);

        DB::statement('ALTER TABLE wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_original_transaction_id_foreign');

        DB::statement('DROP INDEX IF EXISTS ux_wallet_txn_reversal_original');
    }
};
