<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_identity_scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_identity_token_id')->nullable()->constrained('vehicle_identity_tokens')->nullOnDelete();
            $table->string('token_prefix', 12)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_identity_scan_events');
    }
};
