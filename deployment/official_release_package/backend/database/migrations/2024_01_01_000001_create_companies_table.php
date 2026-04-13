<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Facades\DB::raw('uuid_generate_v4()'));
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('tax_number')->nullable()->unique();
            $table->string('cr_number')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('SA');
            $table->string('currency')->default('SAR');
            $table->string('timezone')->default('Asia/Riyadh');
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->integer('version')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
