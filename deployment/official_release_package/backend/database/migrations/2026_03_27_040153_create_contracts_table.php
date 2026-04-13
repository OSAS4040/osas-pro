<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('party_name');
            $table->string('party_type')->default('company'); // company | pos | service_center | individual
            $table->string('party_email')->nullable();
            $table->string('party_phone')->nullable();
            $table->string('party_cr')->nullable();
            $table->string('party_tax_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('value', 14, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->string('payment_policy')->default('monthly'); // monthly|quarterly|annually|one_time|custom
            $table->integer('payment_day')->nullable();
            $table->jsonb('payment_terms')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('alert_days_before')->default(30);
            $table->string('status')->default('draft'); // draft|pending_signature|active|expired|terminated
            $table->string('signed_at')->nullable();
            $table->string('document_url')->nullable();
            $table->string('signed_document_url')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->jsonb('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['end_date', 'status']);
        });

        Schema::create('contract_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // expiry_alert | signed | terminated | payment_due
            $table->string('channel'); // email | whatsapp | sms
            $table->string('recipient');
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_notifications');
        Schema::dropIfExists('contracts');
    }
};
