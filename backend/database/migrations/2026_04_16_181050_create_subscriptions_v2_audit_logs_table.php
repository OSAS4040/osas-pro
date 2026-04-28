<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الاسم الفعلي: subscriptions_v2_audit_logs
 * (جدول audit_logs العام مُعرَّف مسبقاً في governance — لا يُعاد إنشاؤه).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions_v2_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 128);
            $table->string('entity_type', 128);
            $table->unsignedBigInteger('entity_id');
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->json('context_json')->nullable();
            $table->timestamps();

            $table->index('actor_id');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions_v2_audit_logs');
    }
};
