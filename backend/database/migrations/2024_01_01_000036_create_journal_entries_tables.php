<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('entry_number', 30)->unique();
            $table->string('type', 30); // sale, purchase, payment, adjustment, reversal
            $table->string('source_type', 100)->nullable(); // App\Models\Invoice, etc.
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reversed_by_entry_id')->nullable(); // points to reversal entry
            $table->unsignedBigInteger('reversed_entry_id')->nullable(); // original entry reversed
            $table->date('entry_date');
            $table->text('description')->nullable();
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->string('trace_id', 36)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'type']);
            $table->index(['source_type', 'source_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->string('type', 10); // debit / credit
            $table->decimal('amount', 18, 4);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['journal_entry_id']);
            $table->index(['account_id']);
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
