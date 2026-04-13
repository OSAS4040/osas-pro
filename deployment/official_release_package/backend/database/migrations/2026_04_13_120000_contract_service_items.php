<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('discount_amount', 14, 4)->default(0);
            $table->boolean('applies_to_all_vehicles')->default(true);
            $table->jsonb('vehicle_ids')->nullable();
            $table->decimal('max_total_quantity', 14, 4)->nullable();
            $table->boolean('requires_internal_approval')->default(false);
            $table->string('status', 16)->default('active');
            $table->integer('priority')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'contract_id', 'status']);
            $table->index(['company_id', 'service_id', 'status']);
        });

        Schema::table('work_order_items', function (Blueprint $table) {
            $table->foreignId('pricing_contract_service_item_id')->nullable()->after('pricing_policy_id')
                ->constrained('contract_service_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_contract_service_item_id');
        });

        Schema::dropIfExists('contract_service_items');
    }
};
