<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_suspicious_login_signals', function (Blueprint $table) {
            $table->id();
            $table->string('signal_type', 64);
            $table->string('channel', 32)->nullable();
            $table->string('subject_fingerprint', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->uuid('trace_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_suspicious_login_signals');
    }
};
