<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // NPS ratings table
        Schema::create('nps_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->tinyInteger('score'); // 1-5
            $table->text('comment')->nullable();
            $table->string('channel', 20)->default('invoice'); // invoice|sms|email
            $table->boolean('alert_sent')->default(false);
            $table->boolean('resolved')->default(false);
            $table->timestamps();
            $table->index(['company_id', 'score']);
            $table->index(['invoice_id']);
        });

        // Warranty items
        Schema::create('warranty_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('part_name', 200);
            $table->string('part_number', 100)->nullable();
            $table->integer('warranty_days')->default(90);
            $table->date('warranty_start');
            $table->date('warranty_end');
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();
            $table->index(['company_id', 'warranty_end']);
        });

        // Next service reminders
        Schema::create('service_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->date('next_service_date');
            $table->string('discount_code', 30)->nullable();
            $table->decimal('discount_value', 8, 2)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->boolean('notified')->default(false);
            $table->timestamps();
            $table->index(['company_id', 'next_service_date', 'notified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_reminders');
        Schema::dropIfExists('warranty_items');
        Schema::dropIfExists('nps_ratings');
    }
};
