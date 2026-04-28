<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_tenant_nav_hides', function (Blueprint $table) {
            $table->id();
            $table->string('nav_key', 190);
            $table->string('scope', 24);
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['scope', 'company_id']);
            $table->index(['scope', 'user_id']);
            $table->index(['scope', 'customer_id']);
            $table->index('nav_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_tenant_nav_hides');
    }
};
