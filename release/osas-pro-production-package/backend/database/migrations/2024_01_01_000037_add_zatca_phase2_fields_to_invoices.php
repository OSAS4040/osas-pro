<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('zatca_uuid', 36)->nullable()->after('uuid');
            $table->string('zatca_clearance_status', 30)->nullable()->after('zatca_uuid');
            $table->text('zatca_qr_code')->nullable()->after('zatca_clearance_status');
            $table->longText('zatca_signed_xml')->nullable()->after('zatca_qr_code');
            $table->string('zatca_submission_id')->nullable()->after('zatca_signed_xml');
            $table->timestamp('zatca_submitted_at')->nullable()->after('zatca_submission_id');
            $table->string('vat_type', 20)->nullable()->default('standard')->after('tax_amount'); // standard, zero_rated, exempt
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'zatca_uuid',
                'zatca_clearance_status',
                'zatca_qr_code',
                'zatca_signed_xml',
                'zatca_submission_id',
                'zatca_submitted_at',
                'vat_type',
            ]);
        });
    }
};
