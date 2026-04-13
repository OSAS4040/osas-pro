<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('fcm_token', 512)->unique();
            $table->string('device_name', 120)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->timestamp('last_registered_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_push_devices');
    }
};
