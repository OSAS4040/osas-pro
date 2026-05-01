<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'billing_flow_type')) {
                    $table->string('billing_flow_type', 64)->nullable()->after('source_id');
                }
                if (! Schema::hasColumn('invoices', 'customer_visible')) {
                    $table->boolean('customer_visible')->default(true)->after('billing_flow_type');
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (! Schema::hasColumn('purchases', 'billing_flow_type')) {
                    $table->string('billing_flow_type', 64)->nullable()->after('trace_id');
                }
                if (! Schema::hasColumn('purchases', 'source_type')) {
                    $table->string('source_type', 100)->nullable()->after('billing_flow_type');
                }
                if (! Schema::hasColumn('purchases', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (Schema::hasColumn('purchases', 'source_id')) {
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('purchases', 'source_type')) {
                    $table->dropColumn('source_type');
                }
                if (Schema::hasColumn('purchases', 'billing_flow_type')) {
                    $table->dropColumn('billing_flow_type');
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'customer_visible')) {
                    $table->dropColumn('customer_visible');
                }
                if (Schema::hasColumn('invoices', 'billing_flow_type')) {
                    $table->dropColumn('billing_flow_type');
                }
            });
        }
    }
};
