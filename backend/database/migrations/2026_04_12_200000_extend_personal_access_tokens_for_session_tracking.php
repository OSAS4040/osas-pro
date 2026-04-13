<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'auth_channel')) {
                $table->string('auth_channel', 24)->nullable()->after('name');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('auth_channel');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'user_agent_summary')) {
                $table->string('user_agent_summary', 160)->nullable()->after('user_agent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            foreach (['user_agent_summary', 'user_agent', 'ip_address', 'auth_channel'] as $col) {
                if (Schema::hasColumn('personal_access_tokens', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
