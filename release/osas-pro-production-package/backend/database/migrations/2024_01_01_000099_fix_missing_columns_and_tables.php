<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Fix services table - missing deleted_at
        if (!Schema::hasColumn('services', 'deleted_at')) {
            Schema::table('services', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
        // Fix bundles table - missing deleted_at
        if (!Schema::hasColumn('bundles', 'deleted_at')) {
            Schema::table('bundles', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
        // Add leaves table if not exists
        if (!Schema::hasTable('leaves')) {
            Schema::create('leaves', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['annual','sick','emergency','unpaid','other'])->default('annual');
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('days')->default(1);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending','approved','rejected'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['company_id','employee_id','status']);
            });
        }
        // Add salaries table if not exists
        if (!Schema::hasTable('salaries')) {
            Schema::create('salaries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->string('month'); // e.g. "2026-03"
                $table->decimal('base_salary', 12, 2)->default(0);
                $table->decimal('allowances', 12, 2)->default(0);
                $table->decimal('deductions', 12, 2)->default(0);
                $table->decimal('commissions', 12, 2)->default(0);
                $table->decimal('net_salary', 12, 2)->storedAs('base_salary + allowances + commissions - deductions');
                $table->enum('status', ['draft','approved','paid'])->default('draft');
                $table->date('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['company_id','employee_id','month']);
                $table->index(['company_id','status','month']);
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('leaves');
    }
};
