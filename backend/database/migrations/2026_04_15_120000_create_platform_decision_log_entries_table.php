<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_decision_log_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('decision_id')->unique();
            $table->string('incident_key', 128)->index();
            $table->string('decision_type', 32);
            $table->text('decision_summary');
            $table->text('rationale');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->json('linked_signals');
            $table->json('linked_notes');
            $table->text('expected_outcome')->default('');
            $table->json('evidence_refs');
            $table->boolean('follow_up_required')->default(false);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['incident_key', 'created_at', 'decision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_decision_log_entries');
    }
};
