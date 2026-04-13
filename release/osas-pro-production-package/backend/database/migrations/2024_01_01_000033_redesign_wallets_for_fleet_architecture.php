<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create customer_wallets table (replaces old wallets structure)
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('uuid_generate_v4()'));
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('wallet_type'); // customer_main | fleet_main | vehicle_wallet
            $table->string('status')->default('active');
            $table->decimal('balance', 14, 4)->default(0);
            $table->string('currency')->default('SAR');
            $table->integer('version')->default(0);
            $table->timestamps();

            // One customer_main per customer, one fleet_main per customer,
            // one vehicle_wallet per (customer, vehicle)
            $table->unique(['company_id', 'customer_id', 'vehicle_id', 'wallet_type'], 'ux_customer_wallets');
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'vehicle_id']);
        });

        // 2. Alter wallet_transactions to reference customer_wallets + add new columns
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Drop old customer_wallet_id added in phase 6 migration (if it exists)
            if (Schema::hasColumn('wallet_transactions', 'customer_wallet_id')) {
                $table->dropForeign(['customer_wallet_id']);
                $table->dropColumn('customer_wallet_id');
            }

            // Add new columns
            $table->foreignId('customer_wallet_id')->nullable()->after('wallet_id')
                ->constrained('customer_wallets')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->after('customer_wallet_id')
                ->constrained('vehicles')->nullOnDelete();
            $table->string('payment_mode')->nullable()->after('amount'); // direct | prepaid
            $table->string('idempotency_key')->nullable()->after('trace_id');

            // Unique idempotency per company
            $table->unique(['company_id', 'idempotency_key'], 'ux_wallet_txn_idempotency');
            $table->index(['company_id', 'customer_wallet_id']);
            $table->index(['company_id', 'vehicle_id']);
        });

        // 3. Add work order approval fields
        Schema::table('work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('work_orders', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('created_by_user_id')
                    ->constrained('users')->nullOnDelete();
                $table->string('approval_status')->default('not_required')->after('approved_by_user_id');
                $table->timestamp('approved_at')->nullable()->after('approval_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn(['approved_by_user_id', 'approval_status', 'approved_at']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('ux_wallet_txn_idempotency');
            $table->dropIndex(['company_id', 'customer_wallet_id']);
            $table->dropIndex(['company_id', 'vehicle_id']);
            $table->dropForeign(['customer_wallet_id']);
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn(['customer_wallet_id', 'vehicle_id', 'payment_mode', 'idempotency_key']);
        });

        Schema::dropIfExists('customer_wallets');
    }
};
