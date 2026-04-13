<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // invoices — most queried table
        Schema::table('invoices', function (Blueprint $table) {
            if (!$this->hasIndex('invoices', 'invoices_company_status_created_idx'))
                $table->index(['company_id', 'status', 'created_at'], 'invoices_company_status_created_idx');
            if (!$this->hasIndex('invoices', 'invoices_company_created_idx'))
                $table->index(['company_id', 'created_at'], 'invoices_company_created_idx');
        });

        // work_orders
        Schema::table('work_orders', function (Blueprint $table) {
            if (!$this->hasIndex('work_orders', 'wo_company_status_idx'))
                $table->index(['company_id', 'status', 'created_at'], 'wo_company_status_idx');
        });

        // vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            if (!$this->hasIndex('vehicles', 'vehicles_company_plate_idx'))
                $table->index(['company_id', 'plate_number'], 'vehicles_company_plate_idx');
        });

        // journal_entries (ledger)
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (!$this->hasIndex('journal_entries', 'je_company_date_idx'))
                    $table->index(['company_id', 'entry_date'], 'je_company_date_idx');
            });
        }

        // products
        Schema::table('products', function (Blueprint $table) {
            if (!$this->hasIndex('products', 'products_company_active_idx'))
                $table->index(['company_id', 'is_active'], 'products_company_active_idx');
        });

        // customers
        Schema::table('customers', function (Blueprint $table) {
            if (!$this->hasIndex('customers', 'customers_company_name_idx'))
                $table->index(['company_id', 'name'], 'customers_company_name_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'invoices'       => ['invoices_company_status_created_idx', 'invoices_company_created_idx'],
            'work_orders'    => ['wo_company_status_idx'],
            'vehicles'       => ['vehicles_company_plate_idx'],
            'products'       => ['products_company_active_idx'],
            'customers'      => ['customers_company_name_idx'],
        ] as $table => $indexes) {
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $idx) {
                    try { $t->dropIndex($idx); } catch (\Exception $e) {}
                }
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]);
        return count($indexes) > 0;
    }
};
