<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('work_order_id')->constrained('work_orders');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->text('reason');
            $table->string('status', 24)->default('pending'); // pending|approved|rejected
            $table->string('restoration_work_order_status', 48);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['work_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_cancellation_requests');
    }
};
