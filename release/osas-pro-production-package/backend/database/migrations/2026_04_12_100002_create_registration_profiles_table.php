<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('account_type', 32)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('contact_name', 255)->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('company_activation_status', 48)->default('not_applicable');
            $table->unsignedTinyInteger('profile_completion_percent')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'company_activation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_profiles');
    }
};
