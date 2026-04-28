<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('failed_jobs_archive')) {
            return;
        }

        Schema::create('failed_jobs_archive', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->index();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
            $table->timestamp('archived_at')->useCurrent();
            $table->string('archive_reason', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs_archive');
    }
};
