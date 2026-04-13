<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('status', 20)->default('pending'); // pending, completed, rewarded, expired
            $table->decimal('reward_amount', 10, 2)->default(0);
            $table->integer('reward_points')->default(0);
            $table->string('reward_type', 20)->default('wallet'); // wallet, points, discount
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('channel', 30)->nullable(); // whatsapp, email, sms, link
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'referrer_user_id']);
        });

        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('points')->default(0);
            $table->integer('points_used')->default(0);
            $table->decimal('points_to_sar_rate', 8, 4)->default(0.1);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'customer_id']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('type', 30); // earn, redeem, expire, referral_bonus
            $table->integer('points');
            $table->string('description', 255)->nullable();
            $table->morphs('source');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('referral_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('reward_type', 20)->default('wallet');
            $table->decimal('referrer_reward', 10, 2)->default(50.00);
            $table->decimal('referred_reward', 10, 2)->default(25.00);
            $table->integer('referrer_points')->default(0);
            $table->integer('referred_points')->default(0);
            $table->integer('points_per_sar')->default(1);
            $table->decimal('min_purchase_to_earn', 10, 2)->default(0);
            $table->integer('points_expiry_days')->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_policies');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_points');
        Schema::dropIfExists('referrals');
    }
};
