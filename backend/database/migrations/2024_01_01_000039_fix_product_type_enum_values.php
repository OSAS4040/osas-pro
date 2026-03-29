<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix existing products with invalid product_type values
        DB::table('products')
            ->where('product_type', 'product')
            ->update(['product_type' => 'physical']);

        DB::table('products')
            ->whereNotIn('product_type', ['physical', 'service', 'consumable', 'labor'])
            ->update(['product_type' => 'physical']);
    }

    public function down(): void {}
};
