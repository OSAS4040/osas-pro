<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('signature_url')->nullable()->after('logo_url');
            $table->string('stamp_url')->nullable()->after('signature_url');
            $table->string('website')->nullable()->after('stamp_url');
            $table->string('iban')->nullable()->after('website');
            $table->string('bank_name')->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['signature_url', 'stamp_url', 'website', 'iban', 'bank_name']);
        });
    }
};
