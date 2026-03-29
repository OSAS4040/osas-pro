<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing columns to plans if needed
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'billing_cycle')) {
                $table->string('billing_cycle', 20)->default('monthly')->after('name');
            }
            if (!Schema::hasColumn('plans', 'limits')) {
                $table->json('limits')->nullable()->after('features');
            }
            if (!Schema::hasColumn('plans', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });

        // Add missing columns to subscriptions if needed
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'overrides')) {
                $table->json('overrides')->nullable()->after('external_ref');
            }
        });

        // Create subscription_invoices
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
    }
};
