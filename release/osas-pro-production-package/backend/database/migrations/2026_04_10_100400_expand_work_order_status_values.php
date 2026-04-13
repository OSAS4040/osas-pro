<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map legacy operational "pending" to governance queue name.
        DB::table('work_orders')->where('status', 'pending')->update(['status' => 'pending_manager_approval']);
    }

    public function down(): void
    {
        DB::table('work_orders')->where('status', 'pending_manager_approval')->update(['status' => 'pending']);
    }
};
