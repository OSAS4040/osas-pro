<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_announcement_banners', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('title', 200)->nullable();
            $table->text('message')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->string('link_text', 120)->nullable();
            $table->string('variant', 20)->default('promo');
            $table->boolean('dismissible')->default(true);
            $table->string('dismiss_token', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_announcement_banners');
    }
};
