<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchases')) {
            return;
        }

        if (Schema::hasColumn('purchases', 'document_attachments')) {
            return;
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->json('document_attachments')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchases')) {
            return;
        }

        if (! Schema::hasColumn('purchases', 'document_attachments')) {
            return;
        }

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('document_attachments');
        });
    }
};
