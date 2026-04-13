<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Bays / Lifts ───────────────────────────────────────────────
        Schema::create('bays', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id');
            $table->string('code', 20);                      // B01, L03
            $table->string('name', 80);
            $table->string('type', 30)->default('lift');     // lift | bay | wash | alignment
            $table->string('status', 30)->default('available'); // available|reserved|in_use|maintenance|out_of_service
            $table->integer('capacity')->default(1);         // vehicles at once
            $table->unsignedBigInteger('current_work_order_id')->nullable();
            $table->json('capabilities')->nullable();        // ['oil_change','alignment']
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            $table->index(['company_id', 'status']);
        });

        // ── Bookings ──────────────────────────────────────────────────
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('bay_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->integer('duration_minutes')->default(60);
            $table->string('status', 30)->default('pending'); // pending|confirmed|in_progress|completed|cancelled|no_show
            $table->string('service_type', 80)->nullable();
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->string('source', 30)->default('manual'); // manual|fleet_portal|online
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['bay_id', 'starts_at', 'ends_at']);
            $table->index(['company_id', 'status', 'starts_at']);
            $table->index(['customer_id', 'starts_at']);
        });

        // ── Bay Maintenance Logs ───────────────────────────────────────
        Schema::create('bay_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bay_id');
            $table->string('type', 40)->default('scheduled'); // scheduled|unscheduled|failure
            $table->text('description');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bay_maintenance_logs');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('bays');
    }
};
