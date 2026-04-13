<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Facades\DB::raw('uuid_generate_v4()'));
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('order_number');
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('customer_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('technician_notes')->nullable();
            $table->unsignedInteger('mileage_in')->nullable();
            $table->unsignedInteger('mileage_out')->nullable();
            $table->decimal('estimated_total', 14, 4)->default(0);
            $table->decimal('actual_total', 14, 4)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('trace_id')->nullable();
            $table->integer('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'order_number']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['company_id', 'vehicle_id']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'assigned_technician_id']);
        });

        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_type')->default('part');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('discount_amount', 14, 4)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(15);
            $table->decimal('tax_amount', 14, 4)->default(0);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'work_order_id']);
            $table->index(['company_id', 'product_id']);
        });

        Schema::create('work_order_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('role')->default('technician');
            $table->decimal('labor_hours', 6, 2)->nullable();
            $table->decimal('labor_cost', 14, 4)->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->index(['company_id', 'work_order_id']);
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_technicians');
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
    }
};
