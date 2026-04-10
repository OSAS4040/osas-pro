<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reconciliation_finding_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('finding_id');
            $table->string('old_status', 24);
            $table->string('new_status', 24);
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->timestamp('changed_at');
            $table->string('trace_id', 64)->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamps();

            $table->foreign('finding_id')->references('id')->on('financial_reconciliation_findings')->onDelete('cascade');
            $table->index(['finding_id', 'changed_at']);
            $table->index(['new_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliation_finding_histories');
    }
};
