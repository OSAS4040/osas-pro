<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Official English trade name: Osas Pro (Arabic remains أسس برو).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            DB::table('companies')->where('name', 'Asas Pro')->update(['name' => 'Osas Pro']);
        }
        if (Schema::hasTable('users')) {
            DB::table('users')->where('name', 'Asas Pro Admin')->update(['name' => 'Osas Pro Admin']);
        }
        if (Schema::hasTable('plugins')) {
            DB::table('plugins')->where('author', 'Asas Pro Team')->update(['author' => 'Osas Pro Team']);
            DB::table('plugins')->where('author', 'Asas Pro')->update(['author' => 'Osas Pro']);
            DB::table('plugins')->where('author', 'Asas Pro AI')->update(['author' => 'Osas Pro AI']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies')) {
            DB::table('companies')->where('name', 'Osas Pro')->update(['name' => 'Asas Pro']);
        }
        if (Schema::hasTable('users')) {
            DB::table('users')->where('name', 'Osas Pro Admin')->update(['name' => 'Asas Pro Admin']);
        }
        if (Schema::hasTable('plugins')) {
            DB::table('plugins')->where('author', 'Osas Pro Team')->update(['author' => 'Asas Pro Team']);
            DB::table('plugins')->where('author', 'Osas Pro AI')->update(['author' => 'Asas Pro AI']);
            DB::table('plugins')->where('author', 'Osas Pro')->update(['author' => 'Asas Pro']);
        }
    }
};
