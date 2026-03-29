<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('email', 'demo@autocenter.sa')->first();
        if (!$company) {
            $this->command->error('Demo company not found. Run DemoCompanySeeder first.');
            return;
        }

        $branch = Branch::where('company_id', $company->id)->where('is_main', true)->first();
        $owner  = User::where('email', 'owner@demo.sa')->first();
        $tech   = User::where('email', 'tech@demo.sa')->first();

        // ── Customers ─────────────────────────────────────────────────
        $customersData = [
            ['name' => 'أحمد العتيبي',     'phone' => '+966501111111', 'email' => 'ahmed@example.com'],
            ['name' => 'محمد الحربي',      'phone' => '+966502222222', 'email' => 'mohammed@example.com'],
            ['name' => 'فهد القحطاني',    'phone' => '+966503333333', 'email' => 'fahad@example.com'],
            ['name' => 'عبدالله الغامدي', 'phone' => '+966504444444', 'email' => 'abdulla@example.com'],
            ['name' => 'سلطان الدوسري',   'phone' => '+966505555555', 'email' => 'sultan@example.com'],
        ];

        $customers = [];
        foreach ($customersData as $data) {
            $customers[] = Customer::firstOrCreate(
                ['company_id' => $company->id, 'phone' => $data['phone']],
                [
                    'uuid'      => Str::uuid(),
                    'company_id'=> $company->id,
                    'branch_id' => $branch->id,
                    'name'      => $data['name'],
                    'email'     => $data['email'],
                    'phone'     => $data['phone'],
                    'type'      => 'b2c',
                    'is_active' => true,
                ]
            );
        }

        // ── Products/Services ──────────────────────────────────────────
        $productsData = [
            ['name' => 'تغيير زيت المحرك',    'sku' => 'SVC-001', 'price' => 150,  'type' => 'service'],
            ['name' => 'فحص شامل للمركبة',    'sku' => 'SVC-002', 'price' => 300,  'type' => 'service'],
            ['name' => 'تغيير فلتر الهواء',  'sku' => 'SVC-003', 'price' => 80,   'type' => 'service'],
            ['name' => 'تبديل البطارية',      'sku' => 'SVC-004', 'price' => 450,  'type' => 'service'],
            ['name' => 'صيانة نظام الفرامل', 'sku' => 'SVC-005', 'price' => 600,  'type' => 'service'],
            ['name' => 'تعبئة فريون تكييف',   'sku' => 'SVC-006', 'price' => 200,  'type' => 'service'],
            ['name' => 'زيت موتول 5W40',      'sku' => 'PRD-001', 'price' => 120,  'type' => 'physical'],
            ['name' => 'فلتر زيت',            'sku' => 'PRD-002', 'price' => 35,   'type' => 'physical'],
        ];

        $products = [];
        foreach ($productsData as $data) {
            $products[] = Product::firstOrCreate(
                ['company_id' => $company->id, 'sku' => $data['sku']],
                [
                    'uuid'             => Str::uuid(),
                    'company_id'       => $company->id,
                    'name'             => $data['name'],
                    'name_ar'          => $data['name'],
                    'sku'              => $data['sku'],
                    'sale_price'       => $data['price'],
                    'cost_price'       => round($data['price'] * 0.6, 2),
                    'product_type'     => $data['type'],
                    'is_active'        => true,
                    'track_inventory'  => false,
                    'tax_rate'         => 15,
                ]
            );
        }

        // ── Vehicles ───────────────────────────────────────────────────
        $vehiclesData = [
            ['plate' => 'ا ب ج 1234', 'make' => 'Toyota',  'model' => 'Camry',        'year' => 2022, 'color' => 'أبيض', 'customer_idx' => 0],
            ['plate' => 'د ه و 5678', 'make' => 'Nissan',  'model' => 'Altima',       'year' => 2021, 'color' => 'فضي',  'customer_idx' => 1],
            ['plate' => 'ز ح ط 9012', 'make' => 'Hyundai', 'model' => 'Sonata',       'year' => 2023, 'color' => 'أسود', 'customer_idx' => 2],
            ['plate' => 'ي ك ل 3456', 'make' => 'Toyota',  'model' => 'Land Cruiser', 'year' => 2020, 'color' => 'أبيض', 'customer_idx' => 3],
            ['plate' => 'م ن س 7890', 'make' => 'Ford',    'model' => 'F-150',        'year' => 2022, 'color' => 'أحمر', 'customer_idx' => 4],
        ];

        $vehicles = [];
        foreach ($vehiclesData as $data) {
            $customer = $customers[$data['customer_idx']];
            $vehicles[] = Vehicle::firstOrCreate(
                ['company_id' => $company->id, 'plate_number' => $data['plate']],
                [
                    'uuid'              => Str::uuid(),
                    'company_id'        => $company->id,
                    'branch_id'         => $branch->id,
                    'customer_id'       => $customer->id,
                    'created_by_user_id'=> $owner ? $owner->id : 1,
                    'plate_number'      => $data['plate'],
                    'make'              => $data['make'],
                    'model'             => $data['model'],
                    'year'              => $data['year'],
                    'color'             => $data['color'],
                    'vin'               => strtoupper(Str::random(17)),
                    'is_active'         => true,
                ]
            );
        }

        // ── Work Orders ────────────────────────────────────────────────
        $workOrdersData = [
            ['vehicle_idx' => 0, 'status' => 'completed',   'product_idx' => 0, 'days_ago' => 30],
            ['vehicle_idx' => 1, 'status' => 'completed',   'product_idx' => 1, 'days_ago' => 20],
            ['vehicle_idx' => 2, 'status' => 'in_progress', 'product_idx' => 4, 'days_ago' => 2],
            ['vehicle_idx' => 3, 'status' => 'draft',       'product_idx' => 3, 'days_ago' => 0],
            ['vehicle_idx' => 4, 'status' => 'completed',   'product_idx' => 5, 'days_ago' => 10],
        ];

        $workOrders = [];
        $counter = 1001;
        foreach ($workOrdersData as $data) {
            $vehicle  = $vehicles[$data['vehicle_idx']];
            $product  = $products[$data['product_idx']];
            $customer = $customers[$data['vehicle_idx']];

            $wo = WorkOrder::firstOrCreate(
                ['company_id' => $company->id, 'order_number' => 'WO-' . $counter],
                [
                    'uuid'                  => Str::uuid(),
                    'company_id'            => $company->id,
                    'branch_id'             => $branch->id,
                    'customer_id'           => $customer->id,
                    'vehicle_id'            => $vehicle->id,
                    'created_by_user_id'    => $owner ? $owner->id : 1,
                    'assigned_technician_id'=> $tech ? $tech->id : null,
                    'order_number'          => 'WO-' . $counter,
                    'status'                => $data['status'],
                    'priority'              => 'normal',
                    'customer_complaint'    => 'طلب صيانة: ' . $product->name,
                    'estimated_total'       => $product->sale_price,
                    'actual_total'          => $data['status'] === 'completed' ? $product->sale_price : 0,
                    'started_at'            => $data['status'] !== 'draft' ? now()->subDays($data['days_ago']) : null,
                    'completed_at'          => $data['status'] === 'completed' ? now()->subDays(max(0, $data['days_ago'] - 1)) : null,
                ]
            );
            $workOrders[] = $wo;
            $counter++;
        }

        // ── Invoices ───────────────────────────────────────────────────
        $invoicesData = [
            ['customer_idx' => 0, 'wo_idx' => 0, 'total' => 172.5,  'status' => 'paid',    'days_ago' => 29],
            ['customer_idx' => 1, 'wo_idx' => 1, 'total' => 345.0,  'status' => 'paid',    'days_ago' => 19],
            ['customer_idx' => 4, 'wo_idx' => 4, 'total' => 230.0,  'status' => 'paid',    'days_ago' => 9],
            ['customer_idx' => 2, 'wo_idx' => 2, 'total' => 690.0,  'status' => 'draft',   'days_ago' => 0],
            ['customer_idx' => 3, 'wo_idx' => 3, 'total' => 517.5,  'status' => 'pending', 'days_ago' => 0],
        ];

        foreach ($invoicesData as $idx => $data) {
            $customer  = $customers[$data['customer_idx']];
            $workOrder = $workOrders[$data['wo_idx']];
            $subtotal  = round($data['total'] / 1.15, 2);
            $tax       = round($data['total'] - $subtotal, 2);
            $counter   = $idx + 1;

            Invoice::firstOrCreate(
                ['company_id' => $company->id, 'invoice_number' => 'SEED-' . str_pad($counter, 4, '0', STR_PAD_LEFT)],
                [
                    'uuid'              => Str::uuid(),
                    'company_id'        => $company->id,
                    'branch_id'         => $branch->id,
                    'customer_id'       => $customer->id,
                    'created_by_user_id'=> $owner ? $owner->id : 1,
                    'invoice_number'    => 'SEED-' . str_pad($counter, 4, '0', STR_PAD_LEFT),
                    'invoice_counter'   => 0,
                    'type'              => 'sale',
                    'status'            => $data['status'],
                    'customer_type'     => 'b2c',
                    'subtotal'          => $subtotal,
                    'tax_amount'        => $tax,
                    'total'             => $data['total'],
                    'paid_amount'       => $data['status'] === 'paid' ? $data['total'] : 0,
                    'due_amount'        => $data['status'] === 'paid' ? 0 : $data['total'],
                    'currency'          => 'SAR',
                    'issued_at'         => now()->subDays($data['days_ago']),
                    'due_at'            => now()->subDays($data['days_ago'])->addDays(30),
                    'invoice_hash'      => hash('sha256', 'SEED-' . $counter . now()->timestamp),
                    'previous_invoice_hash' => hash('sha256', 'genesis'),
                ]
            );
        }

        $this->command->info('✅ Demo data seeded!');
        $this->command->info('   Customers:   ' . count($customers));
        $this->command->info('   Products:    ' . count($products));
        $this->command->info('   Vehicles:    ' . count($vehicles));
        $this->command->info('   Work Orders: ' . count($workOrders));
        $this->command->info('   Invoices:    ' . count($invoicesData));
    }
}
