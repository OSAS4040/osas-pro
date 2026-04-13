<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_reconciliation_findings', function (Blueprint $table) {
            $table->string('status', 24)->default('open')->after('finding_type');
            $table->timestamp('status_updated_at')->nullable()->after('status');
            $table->unsignedBigInteger('status_updated_by_user_id')->nullable()->after('status_updated_at');
            $table->string('status_update_note', 255)->nullable()->after('status_updated_by_user_id');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_reconciliation_findings', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_updated_at', 'status_updated_by_user_id', 'status_update_note']);
        });
    }
};
