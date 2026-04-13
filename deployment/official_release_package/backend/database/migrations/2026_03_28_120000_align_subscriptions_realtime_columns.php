<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps subscriptions compatible with real-time middleware (grace_ends_at, ends_at vs SaaS aliases).
 * Non-destructive: additive / backfill only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'grace_ends_at')) {
                $table->timestamp('grace_ends_at')->nullable();
            }
        });

        if (Schema::hasColumn('subscriptions', 'ends_at')
            && Schema::hasColumn('subscriptions', 'current_period_end')) {
            DB::table('subscriptions')
                ->whereNull('ends_at')
                ->update(['ends_at' => DB::raw('current_period_end')]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive — down() does not drop columns that may hold production data.
    }
};
