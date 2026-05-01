<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_scope_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('org_unit_id')->constrained('org_units')->cascadeOnDelete();
            $table->unsignedBigInteger('last_allocated')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'customer_id', 'org_unit_id'], 'wo_scope_seq_unique');
            $table->index(['company_id', 'org_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_scope_sequences');
    }
};
