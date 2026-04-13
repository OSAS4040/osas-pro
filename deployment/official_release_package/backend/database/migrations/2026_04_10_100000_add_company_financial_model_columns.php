<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('financial_model', 32)->nullable()->after('status')
                ->comment('prepaid|credit — set by platform only');
            $table->string('financial_model_status', 48)->default('pending_platform_review')->after('financial_model');
            $table->decimal('credit_limit', 18, 4)->nullable()->after('financial_model_status');
            $table->timestamp('platform_financial_reviewed_at')->nullable()->after('credit_limit');
            $table->unsignedBigInteger('platform_financial_reviewed_by')->nullable()->after('platform_financial_reviewed_at');
        });

        // Existing tenants: preserve behaviour (prepaid approved) until platform re-classifies.
        DB::table('companies')->update([
            'financial_model' => 'prepaid',
            'financial_model_status' => 'approved_prepaid',
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->index(['financial_model_status']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['financial_model_status']);
            $table->dropColumn([
                'financial_model',
                'financial_model_status',
                'credit_limit',
                'platform_financial_reviewed_at',
                'platform_financial_reviewed_by',
            ]);
        });
    }
};
