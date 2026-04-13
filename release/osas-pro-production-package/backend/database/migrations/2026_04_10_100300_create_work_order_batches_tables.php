<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('status', 24)->default('processing'); // processing|completed|failed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('work_order_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_batch_id')->constrained('work_order_batches')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('status', 24)->default('pending'); // pending|succeeded|failed|skipped
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['work_order_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_batch_items');
        Schema::dropIfExists('work_order_batches');
    }
};
