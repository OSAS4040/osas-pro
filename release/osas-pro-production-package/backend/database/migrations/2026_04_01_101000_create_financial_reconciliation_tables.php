<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('run_type', 32)->default('daily');
            $table->date('run_date');
            $table->timestamp('executed_at');
            $table->string('artifact_path');
            $table->unsignedInteger('detected_cases')->default(0);
            $table->unsignedInteger('healthy_cases')->default(0);
            $table->unsignedInteger('invoice_without_ledger_count')->default(0);
            $table->unsignedInteger('unbalanced_journal_entry_count')->default(0);
            $table->unsignedInteger('anomalous_reversal_settlement_count')->default(0);
            $table->string('trace_id', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['run_type', 'run_date']);
            $table->index(['run_date']);
        });

        Schema::create('financial_reconciliation_findings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('finding_type', 64);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('trace_reference', 64)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('financial_reconciliation_runs')->onDelete('cascade');
            $table->index(['run_id', 'finding_type']);
            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliation_findings');
        Schema::dropIfExists('financial_reconciliation_runs');
    }
};
