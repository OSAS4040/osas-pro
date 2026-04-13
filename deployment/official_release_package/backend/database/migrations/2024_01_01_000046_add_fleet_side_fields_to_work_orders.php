<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يميّز بين طلبات خدمة أُنشئت من طرف الجهة العميلة (fleet)
 * وتلك التي أنشأها موظف الورشة (workshop).
 * fleet_approved_by_user_id: المعتمد من جانب الجهة العميلة
 * fleet_approved_at: وقت الاعتماد من جهة العميل
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('created_by_side', 20)
                ->default('workshop')
                ->after('created_by_user_id')
                ->comment('fleet | workshop');

            $table->unsignedBigInteger('fleet_approved_by_user_id')
                ->nullable()
                ->after('approved_by_user_id');

            $table->timestamp('fleet_approved_at')
                ->nullable()
                ->after('fleet_approved_by_user_id');

            $table->foreign('fleet_approved_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['fleet_approved_by_user_id']);
            $table->dropColumn(['created_by_side', 'fleet_approved_by_user_id', 'fleet_approved_at']);
        });
    }
};
