<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Facades\DB::raw('uuid_generate_v4()'));
            $table->foreignId('company_id')->constrained('companies');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->string('action');
            $table->string('status');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('zatca_uuid')->nullable();
            $table->string('zatca_status')->nullable();
            $table->text('error_message')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'reference_type', 'reference_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('api_key_id')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['api_key_id', 'created_at']);
            $table->index(['created_at']);
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('signing_secret')->nullable()->after('secret_hash');
            $table->unsignedInteger('total_deliveries')->default(0)->after('is_active');
            $table->unsignedInteger('failed_deliveries')->default(0)->after('total_deliveries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
        Schema::dropIfExists('zatca_logs');
    }
};
