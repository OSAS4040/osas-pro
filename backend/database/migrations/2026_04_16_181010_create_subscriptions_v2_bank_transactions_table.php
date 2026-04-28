<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_uuid')->nullable();
            $table->date('transaction_date');
            $table->time('transaction_time')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('SAR');
            $table->string('sender_name')->nullable();
            $table->string('bank_reference')->nullable();
            $table->text('description')->nullable();
            $table->string('reference_extracted')->nullable();
            $table->boolean('is_matched')->default(false);
            $table->timestamps();

            $table->index('transaction_date');
            $table->index('amount');
            $table->index('is_matched');
            $table->index('reference_extracted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
