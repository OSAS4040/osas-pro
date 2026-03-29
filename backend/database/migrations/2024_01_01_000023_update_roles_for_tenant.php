<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')
                ->constrained('companies')->cascadeOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->boolean('is_system')->default(false)->after('guard_name');

            $table->dropUnique(['name', 'guard_name']);
            $table->index(['company_id', 'name']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('group')->nullable()->after('name');
            $table->text('description')->nullable()->after('group');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['group', 'description']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'name']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['description', 'is_system']);
            $table->unique(['name', 'guard_name']);
        });
    }
};
