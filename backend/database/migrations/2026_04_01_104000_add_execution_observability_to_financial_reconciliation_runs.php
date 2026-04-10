<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_reconciliation_runs', function (Blueprint $table) {
            $table->string('execution_status', 16)->default('succeeded')->after('run_date');
            $table->timestamp('started_at')->nullable()->after('execution_status');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->unsignedBigInteger('duration_ms')->nullable()->after('completed_at');
            $table->string('failure_message', 255)->nullable()->after('duration_ms');
            $table->string('failure_class', 160)->nullable()->after('failure_message');
            $table->index(['execution_status']);
            $table->index(['completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_reconciliation_runs', function (Blueprint $table) {
            $table->dropIndex(['execution_status']);
            $table->dropIndex(['completed_at']);
            $table->dropColumn([
                'execution_status',
                'started_at',
                'completed_at',
                'duration_ms',
                'failure_message',
                'failure_class',
            ]);
        });
    }
};
