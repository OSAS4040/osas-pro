<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7A — append-only human review audit for Smart Command Center (no updates/deletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_command_center_governance_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('governance_ref');
            $table->string('item_source', 32);
            $table->string('item_id', 128);
            $table->string('item_title_snapshot', 512);
            $table->string('severity_snapshot', 32);
            $table->timestampTz('window_from');
            $table->timestampTz('window_to');
            $table->timestampTz('snapshot_generated_at');
            $table->string('action', 48);
            $table->text('note')->nullable();
            $table->jsonb('client_context')->nullable();
            $table->string('trace_id', 80)->nullable()->index();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'governance_ref', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_command_center_governance_audits');
    }
};
