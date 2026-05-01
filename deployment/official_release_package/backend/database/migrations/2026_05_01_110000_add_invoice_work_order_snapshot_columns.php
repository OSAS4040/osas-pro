<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'work_order_number_snapshot')) {
                $table->string('work_order_number_snapshot', 64)->nullable()->after('customer_visible');
            }
            if (! Schema::hasColumn('invoices', 'vehicle_plate_snapshot')) {
                $table->string('vehicle_plate_snapshot', 32)->nullable()->after('work_order_number_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'vehicle_plate_snapshot')) {
                $table->dropColumn('vehicle_plate_snapshot');
            }
            if (Schema::hasColumn('invoices', 'work_order_number_snapshot')) {
                $table->dropColumn('work_order_number_snapshot');
            }
        });
    }
};
