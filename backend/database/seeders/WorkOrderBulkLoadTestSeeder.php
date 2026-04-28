<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates up to 200 vehicles for k6 OSAS bulk tests (single tenant).
 * Run: LOAD_TEST_EMAIL=simulation.owner@demo.local php artisan db:seed --class=WorkOrderBulkLoadTestSeeder
 *
 * Load-test closure doc (duplicate criterion per batch): load-testing/k6/HOT_TENANT_BULK_WORK_ORDERS.md
 */
final class WorkOrderBulkLoadTestSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) (getenv('LOAD_TEST_EMAIL') ?: 'simulation.owner@demo.local');
        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->company_id || ! $user->branch_id) {
            $this->command?->warn("No tenant user found for email {$email}; skip WorkOrderBulkLoadTestSeeder.");

            return;
        }

        $companyId = (int) $user->company_id;
        $branchId = (int) $user->branch_id;

        $customer = Customer::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => 'OSAS Bulk Load Customer',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'type' => 'individual',
                'is_active' => true,
            ],
        );

        $ids = [];
        for ($i = 1; $i <= 200; $i++) {
            $plate = sprintf('OSAS-BULK-%03d', $i);
            $v = Vehicle::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'plate_number' => $plate,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'customer_id' => $customer->id,
                    'created_by_user_id' => $user->id,
                    'make' => 'Test',
                    'model' => 'Bulk',
                    'year' => 2024,
                ],
            );
            $ids[] = $v->id;
        }

        $this->command?->info('K6_BULK_VEHICLE_IDS='.implode(',', $ids));
    }
}
