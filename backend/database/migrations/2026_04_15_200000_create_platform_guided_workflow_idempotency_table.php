<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_guided_workflow_idempotency', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('incident_key', 128)->index();
            $table->string('workflow_key', 64);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedSmallInteger('http_status')->default(200);
            $table->json('response_json');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_guided_workflow_idempotency');
    }
};
