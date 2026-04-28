<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_controlled_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('action_id')->unique();
            $table->string('incident_key', 128)->index();
            $table->string('action_type', 64);
            $table->string('action_summary', 512);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->string('assigned_owner', 190)->nullable();
            $table->boolean('follow_up_required')->default(true);
            $table->timestampTz('scheduled_for')->nullable();
            $table->string('linked_decision_id', 128)->nullable();
            $table->text('linked_notes')->nullable();
            $table->string('external_reference', 190)->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->text('completion_reason')->nullable();
            $table->text('canceled_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['incident_key', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_controlled_actions');
    }
};
