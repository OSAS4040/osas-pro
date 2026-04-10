<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_top_up_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            /** individual = customer_main wallet, fleet = fleet_main wallet */
            $table->string('target', 16);
            $table->decimal('amount', 14, 4);
            $table->string('currency', 8)->default('SAR');
            $table->string('payment_method', 32);
            $table->string('reference_number', 120)->nullable();
            /** Relative path on tenant upload disk (not public URL) */
            $table->string('receipt_path', 512)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('notes_from_customer')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_wallet_transaction_id')
                ->nullable()
                ->constrained('wallet_transactions')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_top_up_requests');
    }
};
