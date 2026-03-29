<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Financial engine: explicit invoice + payment linkage on the wallet ledger
 * for ZATCA reporting, reconciliation, and audit trails.
 *
 * Balance remains derived from customer_wallets; wallet_transactions stay append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_transactions', 'invoice_id')) {
                $table->foreignId('invoice_id')
                    ->nullable()
                    ->after('reference_id')
                    ->constrained('invoices')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('wallet_transactions', 'payment_id')) {
                $table->foreignId('payment_id')
                    ->nullable()
                    ->after('invoice_id')
                    ->constrained('payments')
                    ->nullOnDelete();
            }
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_transactions', 'invoice_id')) {
                $table->index(['company_id', 'invoice_id'], 'idx_wallet_txn_company_invoice');
            }
            if (Schema::hasColumn('wallet_transactions', 'payment_id')) {
                $table->index(['company_id', 'payment_id'], 'idx_wallet_txn_company_payment');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_transactions', 'payment_id')) {
                $table->dropIndex('idx_wallet_txn_company_payment');
                $table->dropForeign(['payment_id']);
                $table->dropColumn('payment_id');
            }
            if (Schema::hasColumn('wallet_transactions', 'invoice_id')) {
                $table->dropIndex('idx_wallet_txn_company_invoice');
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
        });
    }
};
