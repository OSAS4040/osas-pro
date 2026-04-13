<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يربط مستخدمي الأسطول (fleet_contact / fleet_manager) بسجل العميل (Customer).
 * يتيح تحديد المركبات والمحافظ المسموح لهم بإدارتها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')
                ->nullable()
                ->after('branch_id')
                ->comment('للمستخدمين من طرف العميل (fleet_contact/fleet_manager)');

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
