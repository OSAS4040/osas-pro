<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Employees ─────────────────────────────────────────────────
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->unique(); // linked login account
            $table->string('employee_number', 40)->nullable();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('national_id', 30)->nullable();
            $table->string('position', 80)->nullable();       // Technician / Cashier / Supervisor
            $table->string('department', 80)->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->string('status', 20)->default('active'); // active | inactive | suspended
            $table->json('skills')->nullable();               // ['oil_change','alignment',...]
            $table->string('device_id', 120)->nullable();     // for attendance binding
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        // ── Attendance ─────────────────────────────────────────────────
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->string('type', 20);                      // check_in | check_out
            $table->timestamp('logged_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('device_id', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_valid')->default(true);
            $table->string('invalidation_reason', 200)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'logged_at']);
            $table->index(['company_id', 'logged_at']);
        });

        // ── Tasks ──────────────────────────────────────────────────────
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 40)->default('service');  // service | inspection | admin
            $table->string('status', 30)->default('pending'); // pending|assigned|in_progress|completed|cancelled
            $table->string('priority', 20)->default('normal'); // low|normal|high|urgent
            $table->unsignedBigInteger('assigned_to')->nullable();     // employee_id
            $table->unsignedBigInteger('assigned_by')->nullable();     // user_id
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->integer('actual_minutes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // ── Commissions ────────────────────────────────────────────────
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id');
            $table->string('source_type', 80);               // App\Models\Invoice | WorkOrder
            $table->unsignedBigInteger('source_id');
            $table->decimal('base_amount', 12, 2);           // invoice/WO total
            $table->decimal('rate', 5, 2);                   // %
            $table->decimal('amount', 12, 2);                // calculated commission
            $table->string('status', 20)->default('pending'); // pending|paid|cancelled
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['company_id', 'status']);
        });

        // ── Commission Rules ───────────────────────────────────────────
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id')->nullable(); // null = applies to all
            $table->string('applies_to', 40)->default('invoice'); // invoice | work_order | service
            $table->decimal('rate', 5, 2);                        // %
            $table->decimal('min_amount', 12, 2)->default(0);     // minimum base amount
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('employees');
    }
};
