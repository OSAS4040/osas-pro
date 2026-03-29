<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plugins_registry', function (Blueprint $table) {
            $table->id();
            $table->string('plugin_key')->unique();
            $table->string('name');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('author')->default('OSAS Team');
            $table->string('category')->default('ai'); // ai, integration, ui, analytics
            $table->string('icon')->nullable();
            $table->json('module_scope')->nullable(); // ['vehicles', 'work_orders']
            $table->json('config_schema')->nullable();
            $table->json('supported_plans')->default('["professional","enterprise"]');
            $table->json('hooks')->nullable(); // ['onVehicleView', 'onDashboardLoad']
            $table->boolean('is_active')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->integer('install_count')->default(0);
            $table->decimal('rating', 3, 2)->default(5.0);
            $table->timestamps();
        });

        Schema::create('tenant_plugins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('plugin_key');
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'plugin_key']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::create('plugin_logs', function (Blueprint $table) {
            $table->id();
            $table->string('plugin_key');
            $table->unsignedBigInteger('company_id');
            $table->string('event_type'); // activated, deactivated, executed, error
            $table->json('payload')->nullable();
            $table->string('status')->default('success'); // success, error, warning
            $table->integer('execution_ms')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'plugin_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_logs');
        Schema::dropIfExists('tenant_plugins');
        Schema::dropIfExists('plugins_registry');
    }
};
