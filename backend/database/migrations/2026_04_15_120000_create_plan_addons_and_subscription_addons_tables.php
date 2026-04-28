<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_addons', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            /** مفتاح التمكين في الواجهة/الخادم (قد يتجاوز مفاتيح الباقة الأساسية) */
            $table->string('feature_key', 80);
            $table->string('name')->nullable();
            $table->string('name_ar');
            $table->text('description_ar')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 8)->default('SAR');
            /** null أو [] = كل الباقات النشطة */
            $table->json('eligible_plan_slugs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('feature_key');
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('plan_addon_id')->constrained('plan_addons')->cascadeOnDelete();
            $table->timestamp('activated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['subscription_id', 'plan_addon_id']);
            $table->index('plan_addon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('plan_addons');
    }
};
