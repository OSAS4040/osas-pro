<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_sequences', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->primary();
            $table->unsignedBigInteger('last_allocated');
            $table->timestamps();
        });

        if (! Schema::hasTable('work_orders')) {
            return;
        }

        $companyIds = DB::table('work_orders')->distinct()->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $maxSuffix = 0;
            $numbers = DB::table('work_orders')
                ->where('company_id', $companyId)
                ->pluck('order_number');

            foreach ($numbers as $orderNumber) {
                if (preg_match('/^WO-\d+-(\d{6})$/', (string) $orderNumber, $m)) {
                    $maxSuffix = max($maxSuffix, (int) $m[1]);
                }
            }

            if ($maxSuffix > 0) {
                $now = now();
                DB::table('work_order_sequences')->insert([
                    'company_id' => $companyId,
                    'last_allocated' => $maxSuffix,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_sequences');
    }
};
