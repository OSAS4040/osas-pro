<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('title', 180);
            $table->text('agenda')->nullable();
            $table->string('status', 20)->default('draft'); // draft|scheduled|in_progress|closed|cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->string('linked_entity_type', 32)->nullable(); // governance_item|work_order|support_ticket|employee
            $table->unsignedBigInteger('linked_entity_id')->nullable();
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['linked_entity_type', 'linked_entity_id']);
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 120)->nullable();
            $table->string('role', 80)->nullable();
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->text('content');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamp('recorded_at')->nullable();
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
        });

        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->text('decision_text');
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamp('decided_at')->nullable();
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
        });

        Schema::create('meeting_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->text('action_text');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('follow_up_status', 24)->default('open'); // open|in_progress|done|blocked
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->index(['meeting_id', 'follow_up_status']);
        });

        Schema::create('meeting_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->string('file_name', 190);
            $table->string('file_path', 255);
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attachments');
        Schema::dropIfExists('meeting_actions');
        Schema::dropIfExists('meeting_decisions');
        Schema::dropIfExists('meeting_minutes');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
