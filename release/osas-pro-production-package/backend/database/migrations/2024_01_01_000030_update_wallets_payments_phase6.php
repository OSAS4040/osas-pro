<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('customer_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('status')->default('active')->after('currency');
            $table->decimal('credit_limit', 14, 4)->default(0)->after('balance');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('method');
            $table->string('external_sync_status')->nullable()->after('status');
            $table->string('external_reference')->nullable()->after('reference');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')
                ->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_wallet_id')->nullable()->after('wallet_id')
                ->constrained('wallets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['customer_wallet_id']);
            $table->dropColumn(['branch_id', 'customer_wallet_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'external_sync_status', 'external_reference']);
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'status', 'credit_limit', 'notes']);
        });
    }
};
