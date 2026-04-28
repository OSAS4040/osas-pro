<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_key', 128)->unique();
            $table->string('incident_type', 96);
            $table->string('title', 512);
            $table->text('summary');
            $table->text('why_summary');
            $table->string('severity', 24);
            $table->double('confidence');
            $table->string('status', 32);
            $table->string('owner', 190)->nullable();
            $table->string('ownership_state', 32);
            $table->string('escalation_state', 32);
            $table->string('affected_scope', 190);
            $table->json('affected_entities');
            $table->json('affected_companies');
            $table->json('source_signals');
            $table->json('recommended_actions');
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('last_status_change_at')->nullable();
            $table->text('resolve_reason')->nullable();
            $table->text('close_reason')->nullable();
            $table->json('operator_notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('platform_incident_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->string('incident_key', 128)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 96);
            $table->string('prior_status', 32)->nullable();
            $table->string('next_status', 32)->nullable();
            $table->string('prior_escalation_state', 32)->nullable();
            $table->string('next_escalation_state', 32)->nullable();
            $table->string('prior_owner', 190)->nullable();
            $table->string('next_owner', 190)->nullable();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_incident_lifecycle_events');
        Schema::dropIfExists('platform_incidents');
    }
};
