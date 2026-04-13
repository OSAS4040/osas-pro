<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_decisions', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('decision_text');
            $table->unsignedBigInteger('approval_workflow_id')->nullable()->after('requires_approval');
            $table->string('approval_status', 20)->nullable()->after('approval_workflow_id'); // pending|approved|rejected|cancelled
            $table->index(['requires_approval', 'approval_status']);
        });

        Schema::table('meeting_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('decision_id')->nullable()->after('meeting_id');
            $table->unsignedBigInteger('owner_employee_id')->nullable()->after('owner_user_id');
            $table->string('status', 24)->default('open')->after('owner_employee_id'); // open|in_progress|done|cancelled
            $table->timestamp('closed_at')->nullable()->after('due_date');
            $table->index(['decision_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('meeting_actions', function (Blueprint $table) {
            $table->dropIndex(['decision_id']);
            $table->dropIndex(['status']);
            $table->dropColumn(['decision_id', 'owner_employee_id', 'status', 'closed_at']);
        });

        Schema::table('meeting_decisions', function (Blueprint $table) {
            $table->dropIndex(['requires_approval', 'approval_status']);
            $table->dropColumn(['requires_approval', 'approval_workflow_id', 'approval_status']);
        });
    }
};
