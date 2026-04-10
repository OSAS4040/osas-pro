<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vertical_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('defaults')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('config_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20); // system|plan|vertical|company|branch
            $table->string('scope_key', 80);  // system|plan_slug|vertical_code|company_id|branch_id
            $table->string('config_key', 120);
            $table->json('config_value');
            $table->string('value_type', 20)->default('json'); // bool|int|float|string|json
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_key', 'config_key'], 'config_settings_scope_key_unique');
            $table->index(['config_key']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('vertical_profile_code', 50)->nullable()->after('timezone');
            $table->index(['vertical_profile_code']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('vertical_profile_code', 50)->nullable()->after('cross_branch_access');
            $table->index(['vertical_profile_code']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['vertical_profile_code']);
            $table->dropColumn(['vertical_profile_code']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['vertical_profile_code']);
            $table->dropColumn(['vertical_profile_code']);
        });

        Schema::dropIfExists('config_settings');
        Schema::dropIfExists('vertical_profiles');
    }
};
