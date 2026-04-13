<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_step')->default(1)->after('status');
            $table->unsignedSmallInteger('total_steps')->default(1)->after('current_step');
            $table->timestamp('acted_at')->nullable()->after('resolved_at');
            $table->string('trace_id', 80)->nullable()->after('resolver_note');
            $table->index(['company_id', 'subject_type', 'subject_id'], 'approval_workflows_subject_lookup_idx');
        });

        Schema::create('approval_workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->string('old_status', 30);
            $table->string('new_status', 30);
            $table->unsignedSmallInteger('approval_step')->default(1);
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamp('acted_at');
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('approval_workflows')->onDelete('cascade');
            $table->index(['workflow_id', 'approval_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_actions');

        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->dropIndex('approval_workflows_subject_lookup_idx');
            $table->dropColumn(['current_step', 'total_steps', 'acted_at', 'trace_id']);
        });
    }
};
