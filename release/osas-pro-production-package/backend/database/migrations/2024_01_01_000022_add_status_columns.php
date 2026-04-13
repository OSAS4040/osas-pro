<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
            $table->index('status');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
            $table->index(['company_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
