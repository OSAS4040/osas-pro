<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_batches', function (Blueprint $table): void {
            $table->string('idempotency_key', 191)->nullable()->after('notes');
            $table->string('bulk_service_code', 64)->nullable()->after('idempotency_key');
            $table->string('source', 32)->nullable()->after('bulk_service_code');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS work_order_batches_company_idempotency_unique ON work_order_batches (company_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS work_order_batches_company_idempotency_unique');
        }

        Schema::table('work_order_batches', function (Blueprint $table): void {
            $table->dropColumn(['idempotency_key', 'bulk_service_code', 'source']);
        });
    }
};
