<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — domain event store + diagnostic failures (additive, non-financial).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aggregate_type', 120);
            $table->string('aggregate_id', 64);
            $table->string('event_name', 160);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->jsonb('payload_json')->default('{}');
            $table->jsonb('metadata_json')->default('{}');
            $table->string('trace_id', 80)->nullable()->index();
            $table->string('correlation_id', 80)->nullable()->index();
            $table->foreignId('caused_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('caused_by_type', 64)->nullable();
            $table->string('source_context', 255)->nullable();
            $table->string('processing_status', 32)->default('recorded')->index();
            $table->timestampTz('occurred_at')->index();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'occurred_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index('event_name');
        });

        Schema::create('event_record_failures', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 160)->nullable()->index();
            $table->string('aggregate_type', 120)->nullable();
            $table->string('aggregate_id', 64)->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trace_id', 80)->nullable()->index();
            $table->text('error_message');
            $table->jsonb('payload_json')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_record_failures');
        Schema::dropIfExists('domain_events');
    }
};
