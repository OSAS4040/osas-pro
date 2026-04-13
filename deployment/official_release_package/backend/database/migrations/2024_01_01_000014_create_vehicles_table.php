<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Facades\DB::raw('uuid_generate_v4()'));
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->string('plate_number');
            $table->string('vin')->nullable();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->string('engine_type')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('transmission')->nullable();
            $table->unsignedInteger('mileage_in')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'plate_number']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
