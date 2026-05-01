<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('work_orders', 'before_service_images')) {
                $table->json('before_service_images')->nullable()->after('technician_notes');
            }
            if (! Schema::hasColumn('work_orders', 'after_service_images')) {
                $table->json('after_service_images')->nullable()->after('before_service_images');
            }
            if (! Schema::hasColumn('work_orders', 'internal_service_images')) {
                $table->json('internal_service_images')->nullable()->after('after_service_images');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_orders')) {
            return;
        }

        Schema::table('work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('work_orders', 'internal_service_images')) {
                $table->dropColumn('internal_service_images');
            }
            if (Schema::hasColumn('work_orders', 'after_service_images')) {
                $table->dropColumn('after_service_images');
            }
            if (Schema::hasColumn('work_orders', 'before_service_images')) {
                $table->dropColumn('before_service_images');
            }
        });
    }
};
