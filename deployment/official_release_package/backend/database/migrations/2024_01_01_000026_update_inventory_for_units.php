<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()
                ->after('product_id')
                ->constrained('units')->nullOnDelete();

            $table->decimal('unit_cost', 14, 4)->nullable()
                ->after('quantity');
        });

        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()
                ->after('company_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('work_order_id')->nullable()
                ->after('reference_id')
                ->constrained('work_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropConstrainedForeignId('created_by_user_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
