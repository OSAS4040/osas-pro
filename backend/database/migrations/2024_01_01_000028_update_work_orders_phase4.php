<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('work_order_number')->nullable()->after('order_number');
            $table->integer('odometer_reading')->nullable()->after('mileage_in');
            $table->string('driver_name')->nullable()->after('odometer_reading');
            $table->string('driver_phone')->nullable()->after('driver_name');
            $table->string('work_order_sync_status')->nullable()->after('trace_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['work_order_number', 'odometer_reading', 'driver_name', 'driver_phone', 'work_order_sync_status']);
        });
    }
};
