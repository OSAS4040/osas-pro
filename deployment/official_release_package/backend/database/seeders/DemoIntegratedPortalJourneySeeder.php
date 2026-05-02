<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\WalletType;
use App\Enums\WorkOrderItemType;
use App\Enums\WorkOrderStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * يفترض وجود شركة «Demo Auto Center» وحسابات workshop:seed-demo.
 * يكمّل الرحلة: كتالوج + عمليات تشغيلية + ربط عميل البوابة + محافظ مسبقة الدفع + أوامر عمل ببنود.
 * آمن للإعادة: firstOrCreate وأوزان محافظ تُفحص قبل الشحن.
 */
final class DemoIntegratedPortalJourneySeeder extends Seeder
{
    private const COMPANY_EMAIL = 'demo@autocenter.sa';

    public function run(): void
    {
        $company = Company::withoutGlobalScope('tenant')->where('email', self::COMPANY_EMAIL)->first();
        if ($company === null) {
            $this->command?->warn('لم يُعثر على شركة الديمو. نفّذ أولاً: php artisan workshop:seed-demo');

            return;
        }

        app()->instance('tenant_company_id', $company->id);

        $mainBranch = Branch::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('is_main', true)
            ->first();

        if ($mainBranch !== null) {
            app()->instance('tenant_branch_id', $mainBranch->id);
        }

        $owner = User::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('email', 'owner@demo.sa')
            ->first();
        $tech = User::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('email', 'tech@demo.sa')
            ->first();

        if ($owner === null) {
            $this->command?->warn('مستخدم owner@demo.sa غير موجود.');

            return;
        }

        $this->call(DemoDataSeeder::class);

        $extraBranch = $this->ensureSecondaryBranch($company);
        $this->ensureExtendedCatalog($company, $owner);

        $portalCustomer = $this->linkPortalCustomerAndWallet(
            $company,
            $mainBranch,
            $owner,
        );

        if ($portalCustomer !== null) {
            $this->seedFleetManagerLinkage($company, $mainBranch, $portalCustomer);
        }

        $svcProduct = Product::query()
            ->where('company_id', $company->id)
            ->where('sku', 'SVC-001')
            ->first();

        if ($svcProduct !== null && $portalCustomer !== null) {
            $this->seedPortalCustomerWorkOrders(
                $company,
                $mainBranch,
                $extraBranch,
                $owner,
                $tech,
                $portalCustomer,
                $svcProduct,
            );
        }

        $this->command?->info('تم تشغيل الرحلة المتكاملة للبوابات (موظف، عميل، أسطول، محفظة، أوامر عمل).');
    }

    private function ensureSecondaryBranch(Company $company): Branch
    {
        return Branch::withoutGlobalScope('tenant')->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-WEST'],
            [
                'uuid'      => (string) Str::uuid(),
                'name'      => 'فرع غرب الرياض — تجريبي',
                'name_ar'   => 'فرع غرب الرياض — تجريبي',
                'status'    => 'active',
                'is_active' => true,
            ]
        );
    }

    private function ensureExtendedCatalog(Company $company, User $owner): void
    {
        $rows = [
            ['sku' => 'PJ-SVC-BRAKE', 'name' => 'صيانة فرامل — رحلة تجريبية', 'price' => 420, 'type' => ProductType::Service],
            ['sku' => 'PJ-PRD-FILTER', 'name' => 'فلتر هواء — تجريبي', 'price' => 95, 'type' => ProductType::Physical],
        ];

        foreach ($rows as $row) {
            Product::query()->firstOrCreate(
                ['company_id' => $company->id, 'sku' => $row['sku']],
                [
                    'uuid'               => (string) Str::uuid(),
                    'company_id'         => $company->id,
                    'created_by_user_id' => $owner->id,
                    'name'               => $row['name'],
                    'name_ar'            => $row['name'],
                    'sale_price'         => $row['price'],
                    'cost_price'         => round($row['price'] * 0.55, 2),
                    'product_type'       => $row['type'],
                    'tax_rate'           => 15,
                    'is_active'          => true,
                    'track_inventory'    => false,
                ]
            );
        }
    }

    private function linkPortalCustomerAndWallet(
        Company $company,
        ?Branch $mainBranch,
        User $owner,
    ): ?Customer {
        $portalUser = User::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('email', 'customer@demo.sa')
            ->first();

        if ($portalUser === null) {
            return null;
        }

        $customer = Customer::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'email'      => 'portal.journey@demo.local',
            ],
            [
                'uuid'       => (string) Str::uuid(),
                'branch_id'  => $mainBranch?->id,
                'name'       => 'عميل بوابة — رحلة متكاملة',
                'name_ar'    => 'عميل بوابة — رحلة متكاملة',
                'phone'      => '+966509998877',
                'type'       => 'b2c',
                'is_active'  => true,
            ]
        );

        User::withoutGlobalScope('tenant')->whereKey($portalUser->id)->update([
            'customer_id' => $customer->id,
        ]);

        Vehicle::query()->firstOrCreate(
            [
                'company_id'   => $company->id,
                'plate_number' => 'ر ح ي 2030',
            ],
            [
                'uuid'               => (string) Str::uuid(),
                'branch_id'          => $mainBranch?->id,
                'customer_id'        => $customer->id,
                'created_by_user_id' => $owner->id,
                'make'               => 'Toyota',
                'model'              => 'Camry',
                'year'               => 2024,
                'color'              => 'أبيض لؤلؤي',
                'is_active'          => true,
            ]
        );

        $walletService = app(WalletService::class);
        $branchId = $mainBranch?->id;
        $trace = 'portal-journey-seed';

        $fleetBal = (float) (CustomerWallet::query()
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('wallet_type', WalletType::FleetMain)
            ->value('balance') ?? 0);

        if ($fleetBal < 1000) {
            try {
                $walletService->topUpFleet(
                    companyId: $company->id,
                    customerId: $customer->id,
                    vehicleId: null,
                    amount: 25000,
                    invoiceId: null,
                    paymentId: null,
                    userId: $owner->id,
                    traceId: $trace,
                    idempotencyKey: 'portal-journey-fleet-topup-'.$company->id,
                    branchId: $branchId,
                    notes: 'بذور رحلة الديمو — محفظة أسطول',
                );
            } catch (\Throwable $e) {
                $this->command?->warn('تخطي شحن أسطول (ربما مُنفَّذ سابقاً): '.$e->getMessage());
            }
        }

        $vehicle = Vehicle::query()
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('plate_number', 'ر ح ي 2030')
            ->first();

        if ($vehicle !== null) {
            $vwBal = (float) (CustomerWallet::query()
                ->where('company_id', $company->id)
                ->where('customer_id', $customer->id)
                ->where('wallet_type', WalletType::VehicleWallet)
                ->where('vehicle_id', $vehicle->id)
                ->value('balance') ?? 0);

            if ($vwBal < 500) {
                try {
                    $walletService->transferToVehicle(
                        companyId: $company->id,
                        customerId: $customer->id,
                        vehicleId: $vehicle->id,
                        amount: 8000,
                        invoiceId: null,
                        paymentId: null,
                        userId: $owner->id,
                        traceId: $trace.'-xfer',
                        idempotencyKey: 'portal-journey-vehicle-xfer-'.$vehicle->id,
                        branchId: $branchId,
                        notes: 'تحويل إلى محفظة مركبة — رحلة الديمو',
                    );
                } catch (\Throwable $e) {
                    $this->command?->warn('تخطي تحويل للمركبة: '.$e->getMessage());
                }
            }
        }

        return $customer;
    }

    private function seedFleetManagerLinkage(
        Company $company,
        ?Branch $mainBranch,
        Customer $portalFleetCustomer,
    ): void {
        $fleetMgr = User::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('email', 'fleet.manager@demo.sa')
            ->first();

        if ($fleetMgr !== null && $fleetMgr->customer_id === null) {
            User::withoutGlobalScope('tenant')->whereKey($fleetMgr->id)->update([
                'customer_id' => $portalFleetCustomer->id,
            ]);
        }

        $fleetContact = User::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('email', 'fleet.contact@demo.sa')
            ->first();

        if ($fleetContact !== null && $fleetContact->customer_id === null) {
            User::withoutGlobalScope('tenant')->whereKey($fleetContact->id)->update([
                'customer_id' => $portalFleetCustomer->id,
            ]);
        }
    }

    private function seedPortalCustomerWorkOrders(
        Company $company,
        ?Branch $mainBranch,
        Branch $secondaryBranch,
        User $owner,
        ?User $tech,
        Customer $customer,
        Product $lineProduct,
    ): void {
        $vehicle = Vehicle::query()
            ->where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('plate_number', 'ر ح ي 2030')
            ->first();

        if ($vehicle === null || $mainBranch === null) {
            return;
        }

        $scenarios = [
            ['suffix' => 'PJ-DRAFT', 'status' => WorkOrderStatus::Draft, 'branch' => $mainBranch, 'days' => 0],
            ['suffix' => 'PJ-PROG', 'status' => WorkOrderStatus::InProgress, 'branch' => $mainBranch, 'days' => 1],
            ['suffix' => 'PJ-DONE', 'status' => WorkOrderStatus::Completed, 'branch' => $secondaryBranch, 'days' => 5],
            ['suffix' => 'PJ-DLVR', 'status' => WorkOrderStatus::Delivered, 'branch' => $secondaryBranch, 'days' => 8],
        ];

        foreach ($scenarios as $sc) {
            $orderNumber = 'WO-'.$sc['suffix'].'-'.$company->id;
            $wo = WorkOrder::query()->firstOrCreate(
                ['company_id' => $company->id, 'order_number' => $orderNumber],
                [
                    'uuid'                   => (string) Str::uuid(),
                    'branch_id'              => $sc['branch']->id,
                    'customer_id'            => $customer->id,
                    'vehicle_id'             => $vehicle->id,
                    'created_by_user_id'     => $owner->id,
                    'assigned_technician_id' => $tech?->id,
                    'order_number'           => $orderNumber,
                    'status'                 => $sc['status'],
                    'priority'               => 'normal',
                    'customer_complaint'       => 'رحلة تجريبية متكاملة — '.$lineProduct->name,
                    'estimated_total'        => (float) $lineProduct->sale_price,
                    'actual_total'           => in_array($sc['status'], [WorkOrderStatus::Completed, WorkOrderStatus::Delivered], true)
                        ? (float) $lineProduct->sale_price
                        : 0,
                    'started_at'             => $sc['status'] === WorkOrderStatus::Draft ? null : now()->subDays($sc['days']),
                    'completed_at'           => in_array($sc['status'], [WorkOrderStatus::Completed, WorkOrderStatus::Delivered], true)
                        ? now()->subDays(max(0, $sc['days'] - 1))
                        : null,
                    'delivered_at'           => $sc['status'] === WorkOrderStatus::Delivered
                        ? now()->subDays(max(0, $sc['days'] - 2))
                        : null,
                ]
            );

            $hasItem = WorkOrderItem::query()->where('work_order_id', $wo->id)->exists();
            if (! $hasItem) {
                $qty = 1;
                $unit = (float) $lineProduct->sale_price;
                $taxRate = (float) ($lineProduct->tax_rate ?? 15);
                $subtotal = round($unit * $qty, 2);
                $taxAmt = round($subtotal * ($taxRate / 100), 2);

                WorkOrderItem::query()->create([
                    'company_id'    => $company->id,
                    'work_order_id' => $wo->id,
                    'product_id'    => $lineProduct->id,
                    'item_type'     => WorkOrderItemType::Service,
                    'name'          => $lineProduct->name,
                    'sku'           => $lineProduct->sku,
                    'quantity'      => $qty,
                    'unit_price'    => $unit,
                    'discount_amount' => 0,
                    'tax_rate'      => $taxRate,
                    'tax_amount'    => $taxAmt,
                    'subtotal'      => $subtotal,
                    'total'         => round($subtotal + $taxAmt, 2),
                ]);
            }
        }
    }
}
