<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يسمح بمسار V2: إنشاء Payment ثم ربطه بـ Invoice (invoice_id يُملأ بعد إنشاء الفاتورة).
 * dependency تقني — لا يغيّر سلوك السجلات القائمة (invoice_id يبقى NOT NULL للصفوف الحالية).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_order_id')->nullable()->after('invoice_id')
                ->constrained('payment_orders')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments ALTER COLUMN invoice_id DROP NOT NULL');
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['payment_order_id']);
            $table->dropColumn('payment_order_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments ALTER COLUMN invoice_id SET NOT NULL');
        }
    }
};
