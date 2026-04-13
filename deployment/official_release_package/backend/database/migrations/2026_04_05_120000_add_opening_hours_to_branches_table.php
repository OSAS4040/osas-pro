<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly schedule per branch for booking validation (optional).
     * Shape: { "mon": [["08:00","18:00"]], "fri": [["14:00","22:00"]], ... }
     * Keys: mon,tue,wed,thu,fri,sat,sun — null or empty = no restriction (legacy behaviour).
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }
};
