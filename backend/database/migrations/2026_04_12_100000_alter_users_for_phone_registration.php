<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_company_id_foreign');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_company_id_email_unique');
            DB::statement('ALTER TABLE users ALTER COLUMN company_id DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
            DB::statement('CREATE UNIQUE INDEX users_company_id_email_unique ON users (company_id, email)');
        } elseif ($driver === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'email']);
            });
            DB::statement('ALTER TABLE users MODIFY company_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->unique(['company_id', 'email']);
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'email']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->change();
                $table->string('email')->nullable()->change();
            });
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->unique(['company_id', 'email']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('account_type', 32)->nullable()->after('phone_verified_at');
            $table->string('registration_stage', 64)->default('phone_verified')->after('account_type');
            $table->timestamp('profile_completed_at')->nullable()->after('registration_stage');
            $table->timestamp('last_login_at')->nullable()->after('profile_completed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verified_at',
                'account_type',
                'registration_stage',
                'profile_completed_at',
                'last_login_at',
            ]);
        });
    }
};
