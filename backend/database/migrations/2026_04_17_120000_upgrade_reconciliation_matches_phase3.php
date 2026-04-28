<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->after('match_type');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX reconciliation_matches_bank_transaction_id_active ON reconciliation_matches (bank_transaction_id) WHERE status IN ('pending', 'confirmed')");
        } else {
            Schema::table('reconciliation_matches', function (Blueprint $table) {
                $table->unique('bank_transaction_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS reconciliation_matches_bank_transaction_id_active');
        } else {
            Schema::table('reconciliation_matches', function (Blueprint $table) {
                $table->dropUnique(['bank_transaction_id']);
            });
        }

        Schema::table('reconciliation_matches', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
