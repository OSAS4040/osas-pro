<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! $this->hasIndex('invoices', 'idx_invoices_company_counter_desc')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->index(['company_id', 'invoice_counter'], 'idx_invoices_company_counter_desc');
            });
        }

        if (Schema::hasTable('inventory_reservations') && ! $this->hasIndex('inventory_reservations', 'idx_inv_res_status_expires')) {
            Schema::table('inventory_reservations', function (Blueprint $table): void {
                $table->index(['status', 'expires_at', 'id'], 'idx_inv_res_status_expires');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                try {
                    $table->dropIndex('idx_invoices_company_counter_desc');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('inventory_reservations')) {
            Schema::table('inventory_reservations', function (Blueprint $table): void {
                try {
                    $table->dropIndex('idx_inv_res_status_expires');
                } catch (\Throwable) {
                }
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $existing = DB::select(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $indexName]
        );

        return ! empty($existing);
    }
};

