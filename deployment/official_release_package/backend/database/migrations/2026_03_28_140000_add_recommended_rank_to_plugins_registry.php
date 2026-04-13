<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plugins_registry')) {
            return;
        }

        Schema::table('plugins_registry', function (Blueprint $table) {
            if (! Schema::hasColumn('plugins_registry', 'recommended_rank')) {
                $table->unsignedSmallInteger('recommended_rank')->default(100)->after('rating');
            }
            if (! Schema::hasColumn('plugins_registry', 'tags')) {
                $table->json('tags')->nullable()->after('recommended_rank');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plugins_registry')) {
            return;
        }

        Schema::table('plugins_registry', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('plugins_registry', 'tags')) {
                $drop[] = 'tags';
            }
            if (Schema::hasColumn('plugins_registry', 'recommended_rank')) {
                $drop[] = 'recommended_rank';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
