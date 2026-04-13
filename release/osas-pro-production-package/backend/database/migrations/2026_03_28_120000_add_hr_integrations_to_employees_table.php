<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'hr_integrations')) {
                $table->json('hr_integrations')->nullable()->after('skills');
            }
            if (! Schema::hasColumn('employees', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('hr_integrations');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('employees', 'internal_notes')) {
                $drop[] = 'internal_notes';
            }
            if (Schema::hasColumn('employees', 'hr_integrations')) {
                $drop[] = 'hr_integrations';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
