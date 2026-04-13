<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Facades\DB::raw('uuid_generate_v4()'));
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('invoice_number');
            $table->string('type')->default('sale');
            $table->string('status')->default('pending');
            $table->string('customer_type')->default('b2c');
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('discount_amount', 14, 4)->default(0);
            $table->decimal('tax_amount', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->decimal('paid_amount', 14, 4)->default(0);
            $table->decimal('due_amount', 14, 4)->default(0);
            $table->string('currency')->default('SAR');
            $table->string('idempotency_key')->nullable();
            $table->string('invoice_hash')->nullable();
            $table->string('previous_invoice_hash')->nullable();
            $table->unsignedBigInteger('invoice_counter')->nullable();
            $table->string('zatca_status')->nullable();
            $table->string('trace_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->integer('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'invoice_number']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('discount_amount', 14, 4)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(15);
            $table->decimal('tax_amount', 14, 4)->default(0);
            $table->decimal('subtotal', 14, 4);
            $table->decimal('total', 14, 4);
            $table->timestamps();

            $table->index(['company_id', 'invoice_id']);
            $table->index(['company_id', 'product_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Facades\DB::raw('uuid_generate_v4()'));
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('method');
            $table->decimal('amount', 14, 4);
            $table->string('currency')->default('SAR');
            $table->string('reference')->nullable();
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('original_payment_id')->nullable();
            $table->unsignedBigInteger('reversal_payment_id')->nullable();
            $table->string('trace_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'invoice_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
