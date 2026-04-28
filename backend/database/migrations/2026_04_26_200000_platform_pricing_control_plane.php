<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_service_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->json('regions')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_service_provider_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_service_provider_id')
                ->constrained('platform_service_providers')
                ->cascadeOnDelete();
            $table->string('service_code', 64);
            $table->decimal('cost_amount', 14, 4);
            $table->string('currency', 8)->default('SAR');
            $table->date('effective_from')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['platform_service_provider_id', 'service_code'], 'psp_costs_provider_service_unique');
        });

        Schema::create('platform_pricing_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('status', 48)->index();
            $table->string('title')->nullable();
            $table->json('vehicle_types')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('review_completed_at')->nullable();
            $table->json('review_payload')->nullable();
            $table->foreignId('escalated_by_user_id')->nullable()->constrained('users');
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('root_pricing_request_id')->nullable()->references('id')->on('platform_pricing_requests');
            $table->unsignedInteger('version_no')->default(1);
            $table->timestamps();
        });

        Schema::create('platform_pricing_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_pricing_request_id')
                ->constrained('platform_pricing_requests')
                ->cascadeOnDelete();
            $table->string('service_code', 64);
            $table->unsignedBigInteger('tenant_service_id')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_pricing_provider_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_pricing_request_id')
                ->constrained('platform_pricing_requests')
                ->cascadeOnDelete();
            $table->foreignId('platform_service_provider_id')
                ->constrained('platform_service_providers')
                ->cascadeOnDelete();
            $table->decimal('total_provider_cost', 14, 4)->default(0);
            $table->decimal('sell_price_suggested', 14, 4)->nullable();
            $table->decimal('margin_suggested_pct', 8, 3)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('platform_pricing_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_pricing_request_id')
                ->constrained('platform_pricing_requests')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action', 64);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('platform_customer_price_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('root_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('platform_pricing_request_id')->nullable()->constrained('platform_pricing_requests')->nullOnDelete();
            $table->unsignedInteger('version_no')->default(1);
            $table->boolean('is_reference')->default(false)->index();
            $table->json('sell_snapshot');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'customer_id', 'contract_id', 'version_no'], 'pcpv_company_customer_contract_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_customer_price_versions');
        Schema::dropIfExists('platform_pricing_audit_logs');
        Schema::dropIfExists('platform_pricing_provider_quotes');
        Schema::dropIfExists('platform_pricing_request_lines');
        Schema::dropIfExists('platform_pricing_requests');
        Schema::dropIfExists('platform_service_provider_costs');
        Schema::dropIfExists('platform_service_providers');
    }
};
