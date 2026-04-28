<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per vehicle per batch. Same vehicle may appear again in another batch
 * (e.g. another bulk_service_code on work_order_batches).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_batch_items', function (Blueprint $table) {
            $table->unique(
                ['work_order_batch_id', 'vehicle_id'],
                'work_order_batch_items_batch_vehicle_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('work_order_batch_items', function (Blueprint $table) {
            $table->dropUnique('work_order_batch_items_batch_vehicle_unique');
        });
    }
};
