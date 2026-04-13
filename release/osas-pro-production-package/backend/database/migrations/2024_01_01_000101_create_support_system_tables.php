<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // SLA Policies
        if (!Schema::hasTable('sla_policies')) {
            Schema::create('sla_policies', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->unsignedBigInteger('company_id');
                $table->string('name');
                $table->string('priority')->default('medium'); // critical, high, medium, low
                $table->unsignedSmallInteger('first_response_hours')->default(4);
                $table->unsignedSmallInteger('resolution_hours')->default(24);
                $table->unsignedSmallInteger('escalation_after_hours')->default(8);
                $table->json('escalate_to_roles')->nullable(); // ['owner','manager']
                $table->boolean('notify_customer_on_breach')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('company_id');
            });
        }

        // Support Tickets
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->string('ticket_number', 20)->unique();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('fleet_account_id')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable(); // user_id
                $table->unsignedBigInteger('created_by');             // user_id
                $table->unsignedBigInteger('sla_policy_id')->nullable();

                $table->string('subject');
                $table->text('description');
                $table->string('category')->default('general');
                // categories: technical, financial, operational, general, billing, vehicle, complaint
                $table->string('priority')->default('medium');
                // critical, high, medium, low
                $table->string('status')->default('open');
                // open, in_progress, pending_customer, resolved, closed, escalated
                $table->string('channel')->default('portal');
                // portal, email, whatsapp, phone, walk_in
                $table->string('source_module')->nullable(); // work_order, invoice, booking, fleet
                $table->unsignedBigInteger('source_id')->nullable();

                // SLA tracking
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('sla_due_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->boolean('sla_breached')->default(false);
                $table->boolean('first_response_breached')->default(false);

                // Satisfaction
                $table->unsignedTinyInteger('satisfaction_score')->nullable(); // 1-5
                $table->text('satisfaction_comment')->nullable();
                $table->timestamp('satisfaction_rated_at')->nullable();

                // AI
                $table->json('suggested_kb_articles')->nullable();
                $table->float('ai_sentiment_score')->nullable(); // -1 to 1
                $table->string('ai_category_suggestion')->nullable();
                $table->string('ai_priority_suggestion')->nullable();

                $table->json('tags')->nullable();
                $table->json('attachments')->nullable();
                $table->text('internal_notes')->nullable();
                $table->boolean('is_private')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'priority']);
                $table->index(['assigned_to', 'status']);
                $table->index('sla_due_at');
            });
        }

        // Ticket Replies / Timeline
        if (!Schema::hasTable('support_ticket_replies')) {
            Schema::create('support_ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('author_type')->default('staff'); // staff, customer, system, ai_bot
                $table->string('author_name')->nullable();
                $table->text('body');
                $table->boolean('is_internal')->default(false); // internal note vs customer reply
                $table->string('channel')->default('portal');
                $table->json('attachments')->nullable();
                $table->string('event_type')->nullable();
                // reply, status_change, assignment, escalation, sla_breach, satisfaction
                $table->json('event_meta')->nullable();
                $table->timestamps();
                $table->index('ticket_id');
            });
        }

        // Knowledge Base Categories
        if (!Schema::hasTable('kb_categories')) {
            Schema::create('kb_categories', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->unsignedBigInteger('company_id');
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->string('icon')->nullable();
                $table->string('color')->default('#3B82F6');
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->boolean('is_public')->default(true); // visible to customers?
                $table->timestamps();
                $table->index('company_id');
            });
        }

        // Knowledge Base Articles
        if (!Schema::hasTable('knowledge_base')) {
            Schema::create('knowledge_base', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('kb_category_id')->nullable();
                $table->unsignedBigInteger('author_id');
                $table->string('title');
                $table->string('title_ar')->nullable();
                $table->longText('content');         // HTML/Markdown
                $table->longText('content_ar')->nullable();
                $table->text('summary')->nullable();
                $table->json('tags')->nullable();
                $table->json('related_categories')->nullable(); // ticket categories this applies to
                $table->string('status')->default('draft'); // draft, published, archived
                $table->unsignedInteger('views')->default(0);
                $table->unsignedInteger('helpful_yes')->default(0);
                $table->unsignedInteger('helpful_no')->default(0);
                $table->boolean('is_public')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'status']);
            });
        }

        // Escalation Rules
        if (!Schema::hasTable('escalation_rules')) {
            Schema::create('escalation_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name');
                $table->json('conditions');
                // {"priority": "critical", "hours_open": 2, "status": "open"}
                $table->json('actions');
                // {"assign_to_role": "manager", "notify_emails": [], "change_priority": "critical"}
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('company_id');
            });
        }

        // Ticket Watchers
        if (!Schema::hasTable('ticket_watchers')) {
            Schema::create('ticket_watchers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['ticket_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_watchers');
        Schema::dropIfExists('escalation_rules');
        Schema::dropIfExists('knowledge_base');
        Schema::dropIfExists('kb_categories');
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('sla_policies');
    }
};
