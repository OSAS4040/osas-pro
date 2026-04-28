<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfer_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_order_id')->constrained('payment_orders')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('transfer_date');
            $table->time('transfer_time')->nullable();
            $table->string('bank_name');
            $table->string('sender_name')->nullable();
            $table->string('sender_account_masked')->nullable();
            $table->string('bank_reference')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('status', 64);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('payment_order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_submissions');
    }
};
