<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()
                ->after('company_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('unit_id')->nullable()
                ->after('category_id')
                ->constrained('units')->nullOnDelete();

            $table->foreignId('purchase_unit_id')->nullable()
                ->after('unit_id')
                ->constrained('units')->nullOnDelete();

            $table->string('product_type')->default('physical')
                ->after('name_ar'); // physical | service | consumable | labor

            $table->renameColumn('price', 'sale_price');
            $table->renameColumn('cost', 'cost_price');

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('sale_price', 'price');
            $table->renameColumn('cost_price', 'cost');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('purchase_unit_id');
            $table->dropColumn('product_type');
        });
    }
};
