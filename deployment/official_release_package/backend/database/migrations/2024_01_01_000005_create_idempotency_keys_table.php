<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('key', 255);
            $table->string('endpoint');
            $table->string('trace_id')->nullable();
            $table->string('request_hash', 64);
            $table->longText('response_snapshot')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
