<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Policy Rules ─────────────────────────────────────────────
        Schema::create('policy_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 80)->index();          // e.g. discount.max, credit.limit
            $table->string('entity_type', 60)->nullable(); // 'global' | 'branch' | 'role' | 'user'
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('operator', 20)->default('lte'); // lte | gte | eq | in | between
            $table->json('value');                         // threshold / allowed values
            $table->string('action', 40)->default('require_approval'); // require_approval | block | alert
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code', 'entity_type', 'entity_id'], 'policy_rules_unique');
        });

        // ── Approval Workflows ────────────────────────────────────────
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('subject_type', 80);            // App\Models\WorkOrder | Invoice | ...
            $table->unsignedBigInteger('subject_id');
            $table->string('policy_code', 80)->nullable(); // which rule triggered this
            $table->string('status', 30)->default('pending'); // pending | approved | rejected | cancelled
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('assigned_approver')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('requester_note')->nullable();
            $table->text('resolver_note')->nullable();
            $table->json('meta')->nullable();              // snapshot of values at request time
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['company_id', 'status']);
        });

        // ── Audit Logs ────────────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 80);                  // created | updated | deleted | approved | etc.
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        // ── Alert Rules ───────────────────────────────────────────────
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 80);                    // discount.unusual | balance.low | etc.
            $table->string('channel', 30)->default('in_app'); // in_app | email | webhook
            $table->json('condition');                     // threshold / filter
            $table->json('recipients')->nullable();        // user_ids or roles
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // ── Alert Notifications (fired instances) ─────────────────────
        Schema::create('alert_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('code', 80);
            $table->string('severity', 20)->default('info'); // info | warning | critical
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('message');
            $table->json('meta')->nullable();
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notifications');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('policy_rules');
    }
};
