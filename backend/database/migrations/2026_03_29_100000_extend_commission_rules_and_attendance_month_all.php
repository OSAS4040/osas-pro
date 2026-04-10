<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commission_rules')) {
            return;
        }

        Schema::table('commission_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('commission_rules', 'name')) {
                $table->string('name', 160)->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('commission_rules', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('employee_id');
            }
            if (! Schema::hasColumn('commission_rules', 'priority')) {
                $table->unsignedSmallInteger('priority')->default(0)->after('is_active');
            }
            if (! Schema::hasColumn('commission_rules', 'max_commission_amount')) {
                $table->decimal('max_commission_amount', 12, 2)->nullable()->after('min_amount');
            }
            if (! Schema::hasColumn('commission_rules', 'attendance_multiplier')) {
                $table->decimal('attendance_multiplier', 4, 2)->default(1)->after('max_commission_amount');
            }
            if (! Schema::hasColumn('commission_rules', 'meta')) {
                $table->json('meta')->nullable()->after('attendance_multiplier');
            }
        });

        $indexColumns = ['company_id', 'applies_to', 'is_active'];
        $hasComposite = collect(Schema::getIndexes('commission_rules'))->contains(function (array $idx) use ($indexColumns) {
            $cols = $idx['columns'] ?? [];
            sort($cols);
            $want = $indexColumns;
            sort($want);

            return $cols === $want;
        });

        if (! $hasComposite) {
            Schema::table('commission_rules', function (Blueprint $table) use ($indexColumns) {
                $table->index($indexColumns);
            });
        }

        $hasCustomerFk = collect(Schema::getForeignKeys('commission_rules'))->contains(function (array $fk) {
            return in_array('customer_id', $fk['columns'] ?? [], true);
        });

        if (Schema::hasColumn('commission_rules', 'customer_id') && ! $hasCustomerFk && Schema::hasTable('customers')) {
            Schema::table('commission_rules', function (Blueprint $table) {
                $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('commission_rules')) {
            return;
        }

        $hasCustomerFk = collect(Schema::getForeignKeys('commission_rules'))->contains(function (array $fk) {
            return in_array('customer_id', $fk['columns'] ?? [], true);
        });

        if ($hasCustomerFk) {
            Schema::table('commission_rules', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
            });
        }

        $indexColumns = ['company_id', 'applies_to', 'is_active'];
        $indexName = collect(Schema::getIndexes('commission_rules'))->first(function (array $idx) use ($indexColumns) {
            $cols = $idx['columns'] ?? [];
            sort($cols);
            $want = $indexColumns;
            sort($want);

            return $cols === $want;
        });

        if ($indexName && isset($indexName['name'])) {
            Schema::table('commission_rules', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName['name']);
            });
        }

        Schema::table('commission_rules', function (Blueprint $table) {
            $drop = [];
            foreach (['meta', 'attendance_multiplier', 'max_commission_amount', 'priority', 'name', 'customer_id'] as $col) {
                if (Schema::hasColumn('commission_rules', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
