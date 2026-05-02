<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_claim_purchase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_claim_id')->constrained('purchase_claims')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['purchase_claim_id', 'purchase_id']);
            $table->index(['purchase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_claim_purchase');
    }
};
