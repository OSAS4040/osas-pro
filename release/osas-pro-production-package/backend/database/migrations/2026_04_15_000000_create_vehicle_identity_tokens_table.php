<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_identity_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            /** رمز غير قابل للتخمين — يُخزَّن كما هو للمطابقة السريعة (مستوى طول يعادل 256 بت سداسي عشري) */
            $table->string('token', 64)->unique();
            /** كود قصير للعرض البشري (مثل VH-XXXX-XXXX) — ليس سراً */
            $table->string('public_code', 24)->unique();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('vehicle_identity_tokens')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_identity_tokens');
    }
};
