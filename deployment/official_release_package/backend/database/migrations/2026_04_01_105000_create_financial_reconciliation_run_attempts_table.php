<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reconciliation_run_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('run_type', 32)->default('daily');
            $table->date('run_date')->nullable();
            $table->string('attempt_status', 16); // started|blocked|succeeded|failed
            $table->unsignedBigInteger('run_id')->nullable();
            $table->unsignedBigInteger('blocked_by_run_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('trace_id', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['attempt_status', 'created_at']);
            $table->index(['run_type', 'run_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliation_run_attempts');
    }
};
