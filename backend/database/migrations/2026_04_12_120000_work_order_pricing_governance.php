<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->nullable()->after('branch_id')
                ->constrained('customer_groups')->nullOnDelete();
            $table->foreignId('pricing_contract_id')->nullable()->after('customer_group_id')
                ->constrained('contracts')->nullOnDelete();
            $table->string('customer_pricing_profile', 32)->default('standard')->after('type');
            $table->index(['company_id', 'customer_group_id']);
        });

        Schema::create('service_pricing_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('policy_type', 32);
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('status', 16)->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->integer('priority')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'service_id', 'status']);
            $table->index(['company_id', 'policy_type', 'status']);
        });

        Schema::table('work_order_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('product_id')
                ->constrained('services')->nullOnDelete();
            $table->string('pricing_source', 32)->nullable()->after('total');
            $table->foreignId('pricing_policy_id')->nullable()->after('pricing_source')
                ->constrained('service_pricing_policies')->nullOnDelete();
            $table->timestamp('pricing_resolved_at')->nullable();
            $table->boolean('pricing_resolved_by_system')->default(false);
            $table->text('pricing_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('pricing_policy_id');
            $table->dropColumn([
                'pricing_source',
                'pricing_resolved_at',
                'pricing_resolved_by_system',
                'pricing_notes',
            ]);
        });

        Schema::dropIfExists('service_pricing_policies');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_group_id');
            $table->dropConstrainedForeignId('pricing_contract_id');
            $table->dropColumn('customer_pricing_profile');
        });

        Schema::dropIfExists('customer_groups');
    }
};
