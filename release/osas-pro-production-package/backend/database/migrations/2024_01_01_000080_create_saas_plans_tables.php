<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 80);
                $table->string('billing_cycle', 20)->default('monthly');
                $table->decimal('price', 12, 2)->default(0);
                $table->json('features');
                $table->json('limits');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('company_id')->unique();
                $table->unsignedBigInteger('plan_id');
                $table->string('status', 20)->default('active');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_start');
                $table->timestamp('current_period_end');
                $table->timestamp('cancelled_at')->nullable();
                $table->string('payment_method', 40)->nullable();
                $table->string('external_ref', 120)->nullable();
                $table->json('overrides')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'status']);
            });
        }

        if (!Schema::hasTable('subscription_invoices')) {
            Schema::create('subscription_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('subscription_id');
                $table->unsignedBigInteger('plan_id');
                $table->decimal('amount', 12, 2);
                $table->string('status', 20)->default('unpaid');
                $table->timestamp('due_at');
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_ref', 120)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
