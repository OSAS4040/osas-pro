<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('event', 48);
            $table->string('auth_channel', 24)->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent_summary', 160)->nullable();
            $table->string('trace_id', 80)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_login_events');
    }
};
