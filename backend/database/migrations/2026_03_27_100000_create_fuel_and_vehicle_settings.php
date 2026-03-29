<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('log_date');
            $table->decimal('liters', 10, 3);
            $table->decimal('price_per_liter', 10, 3);
            $table->decimal('total_cost', 10, 2)->storedAs('liters * price_per_liter');
            $table->decimal('odometer_before', 12, 2)->nullable();
            $table->decimal('odometer_after', 12, 2)->nullable();
            $table->decimal('fuel_efficiency', 8, 3)->nullable()->comment('km per liter');
            $table->string('fuel_type', 30)->default('91'); // 91, 95, 98, diesel
            $table->string('station_name', 120)->nullable();
            $table->string('payment_method', 30)->default('cash');
            $table->text('notes')->nullable();
            $table->string('receipt_number', 80)->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['company_id', 'vehicle_id', 'log_date']);
        });

        Schema::create('vehicle_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained('vehicles')->cascadeOnDelete();
            $table->string('oil_type', 80)->nullable();
            $table->string('oil_capacity_liters', 20)->nullable();
            $table->integer('oil_change_interval_km')->nullable()->default(5000);
            $table->decimal('last_oil_change_km', 12, 2)->nullable();
            $table->date('last_oil_change_date')->nullable();
            $table->string('tire_size', 40)->nullable();
            $table->string('tire_brand', 80)->nullable();
            $table->date('tire_change_date')->nullable();
            $table->string('battery_brand', 80)->nullable();
            $table->string('battery_capacity_ah', 20)->nullable();
            $table->date('battery_change_date')->nullable();
            $table->string('ac_gas_type', 40)->nullable();
            $table->date('last_ac_service_date')->nullable();
            $table->integer('inspection_interval_months')->nullable()->default(12);
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('registration_expiry')->nullable();
            $table->jsonb('custom_settings')->nullable()->default('{}');
            $table->timestamps();
        });

        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 60); // insurance, registration, technical, other
            $table->string('title', 160);
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->bigInteger('file_size')->default(0);
            $table->date('expiry_date')->nullable();
            $table->integer('alert_days_before')->default(30);
            $table->boolean('alert_sent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('vehicle_settings');
        Schema::dropIfExists('fuel_logs');
    }
};
