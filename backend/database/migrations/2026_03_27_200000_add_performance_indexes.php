<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Performance indexes for OSAS hot query paths.
 * Based on profiling: slowest queries use these columns without indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── invoices ────────────────────────────────────────────────────────
        $this->addIndexIfMissing('invoices', 'idx_invoices_company_status_date', function(Blueprint $t) {
            $t->index(['company_id', 'status', 'created_at'], 'idx_invoices_company_status_date');
        });
        $this->addIndexIfMissing('invoices', 'idx_invoices_customer_id', function(Blueprint $t) {
            $t->index('customer_id', 'idx_invoices_customer_id');
        });
        $this->addIndexIfMissing('invoices', 'idx_invoices_uuid', function(Blueprint $t) {
            $t->index('uuid', 'idx_invoices_uuid');
        });

        // ── work_orders ──────────────────────────────────────────────────────
        $this->addIndexIfMissing('work_orders', 'idx_wo_company_status_date', function(Blueprint $t) {
            $t->index(['company_id', 'status', 'created_at'], 'idx_wo_company_status_date');
        });
        $this->addIndexIfMissing('work_orders', 'idx_wo_vehicle_id', function(Blueprint $t) {
            $t->index('vehicle_id', 'idx_wo_vehicle_id');
        });
        $this->addIndexIfMissing('work_orders', 'idx_wo_customer_id', function(Blueprint $t) {
            $t->index('customer_id', 'idx_wo_customer_id');
        });
        $this->addIndexIfMissing('work_orders', 'idx_wo_technician_id', function(Blueprint $t) {
            $t->index('assigned_technician_id', 'idx_wo_technician_id');
        });

        // ── customers ───────────────────────────────────────────────────────
        $this->addIndexIfMissing('customers', 'idx_customers_company_phone', function(Blueprint $t) {
            $t->index(['company_id', 'phone'], 'idx_customers_company_phone');
        });
        $this->addIndexIfMissing('customers', 'idx_customers_company_name', function(Blueprint $t) {
            $t->index(['company_id', 'name'], 'idx_customers_company_name');
        });

        // ── vehicles ────────────────────────────────────────────────────────
        $this->addIndexIfMissing('vehicles', 'idx_vehicles_plate_company', function(Blueprint $t) {
            $t->index(['plate_number', 'company_id'], 'idx_vehicles_plate_company');
        });
        $this->addIndexIfMissing('vehicles', 'idx_vehicles_customer_id', function(Blueprint $t) {
            $t->index('customer_id', 'idx_vehicles_customer_id');
        });

        // ── wallets ─────────────────────────────────────────────────────────
        $this->addIndexIfMissing('wallets', 'idx_wallets_company_status', function(Blueprint $t) {
            $t->index(['company_id', 'status'], 'idx_wallets_company_status');
        });

        // ── ledger / journal entries ─────────────────────────────────────────
        foreach (['journal_entries', 'ledger_entries'] as $table) {
            if (!Schema::hasTable($table)) continue;
            $this->addIndexIfMissing($table, "idx_{$table}_company_date", function(Blueprint $t) use ($table) {
                $t->index(['company_id', 'created_at'], "idx_{$table}_company_date");
            });
            if (Schema::hasColumn($table, 'reference_type')) {
                $this->addIndexIfMissing($table, "idx_{$table}_ref", function(Blueprint $t) use ($table) {
                    $t->index(['reference_type', 'reference_id'], "idx_{$table}_ref");
                });
            }
        }

        // ── bookings ────────────────────────────────────────────────────────
        if (Schema::hasTable('bookings')) {
            $this->addIndexIfMissing('bookings', 'idx_bookings_company_date', function(Blueprint $t) {
                $t->index(['company_id', 'starts_at', 'status'], 'idx_bookings_company_date');
            });
            $this->addIndexIfMissing('bookings', 'idx_bookings_bay_id', function(Blueprint $t) {
                $t->index('bay_id', 'idx_bookings_bay_id');
            });
        }

        // ── users ────────────────────────────────────────────────────────────
        $this->addIndexIfMissing('users', 'idx_users_company_role', function(Blueprint $t) {
            $t->index(['company_id', 'role'], 'idx_users_company_role');
        });
        if (Schema::hasColumn('users', 'remember_token')) {
            // already indexed by default in most setups — skip
        }

        // ── products ────────────────────────────────────────────────────────
        $this->addIndexIfMissing('products', 'idx_products_company_sku', function(Blueprint $t) {
            $t->index(['company_id', 'sku'], 'idx_products_company_sku');
        });
        $this->addIndexIfMissing('products', 'idx_products_company_type', function(Blueprint $t) {
            $t->index(['company_id', 'product_type'], 'idx_products_company_type');
        });

        // ── ANALYZE tables so query planner uses new indexes immediately ───
        DB::statement("ANALYZE invoices, work_orders, customers, vehicles, users, products");

        // ── PostgreSQL: update table stats for planner ─────────────────────
        DB::statement("SELECT pg_stat_reset_single_table_counters('pg_catalog.pg_class'::regclass)") ;
    }

    public function down(): void
    {
        $drops = [
            'invoices'       => ['idx_invoices_company_status_date','idx_invoices_customer_id','idx_invoices_uuid'],
            'work_orders'    => ['idx_wo_company_status_date','idx_wo_vehicle_id','idx_wo_customer_id','idx_wo_technician_id'],
            'customers'      => ['idx_customers_company_phone','idx_customers_company_name'],
            'vehicles'       => ['idx_vehicles_plate_company','idx_vehicles_customer_id'],
            'wallets'        => ['idx_wallets_company_status'],
            'users'          => ['idx_users_company_role'],
            'products'       => ['idx_products_company_sku','idx_products_company_type'],
        ];
        foreach ($drops as $table => $indexes) {
            Schema::table($table, function(Blueprint $t) use ($indexes) {
                foreach ($indexes as $idx) {
                    try { $t->dropIndex($idx); } catch (\Throwable) {}
                }
            });
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, callable $callback): void
    {
        if (!Schema::hasTable($table)) return;
        $exists = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $indexName]
        );
        if (!empty($exists)) return;
        Schema::table($table, $callback);
    }
};
