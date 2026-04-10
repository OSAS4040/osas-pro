<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align stored tenant/demo branding with the official product name: أسس برو / Osas Pro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            DB::table('companies')->whereIn('name', ['OSAS Platform', 'Asas Platform'])->update([
                'name'    => 'Osas Pro',
                'name_ar' => 'أسس برو',
            ]);
            DB::table('companies')->where('name_ar', 'منصة أواس')->update([
                'name'    => 'Osas Pro',
                'name_ar' => 'أسس برو',
            ]);
            DB::table('companies')->where('email', 'hq@osas.sa')->update([
                'name'    => 'Osas Pro',
                'name_ar' => 'أسس برو',
            ]);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->where('name', 'OSAS Admin')->update(['name' => 'Osas Pro Admin']);
        }

        if (Schema::hasTable('plugins')) {
            DB::table('plugins')->where('author', 'OSAS Team')->update(['author' => 'Osas Pro Team']);
            DB::table('plugins')->where('author', 'OSAS')->update(['author' => 'Osas Pro']);
            DB::table('plugins')->where('author', 'OSAS AI')->update(['author' => 'Osas Pro AI']);
        }
    }

    public function down(): void
    {
        // Intentionally empty: reversing brand rename is ambiguous for production data.
    }
};
