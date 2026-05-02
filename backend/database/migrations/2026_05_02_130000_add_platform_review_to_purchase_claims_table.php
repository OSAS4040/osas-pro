<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_claims', function (Blueprint $table) {
            $table->string('platform_review_status', 32)->nullable()->after('reviewed_at');
            $table->text('platform_review_notes')->nullable()->after('platform_review_status');
            $table->foreignId('platform_reviewed_by_user_id')->nullable()->after('platform_review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('platform_reviewed_at')->nullable()->after('platform_reviewed_by_user_id');
            $table->index(['platform_review_status', 'status']);
        });

        // مطالبات كانت معتمدة من المستأجر قبل إدخال اعتماد المنصة — تُعتبر معتمدة نهائياً من المنصة.
        DB::table('purchase_claims')->where('status', 'approved')->update(['platform_review_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('purchase_claims', function (Blueprint $table) {
            $table->dropIndex(['platform_review_status', 'status']);
        });
        Schema::table('purchase_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_reviewed_by_user_id');
        });
        Schema::table('purchase_claims', function (Blueprint $table) {
            $table->dropColumn(['platform_review_status', 'platform_review_notes', 'platform_reviewed_at']);
        });
    }
};
