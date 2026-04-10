<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\CompanyStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProductType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Automated simulation: 1 company, 20 customers, 100 paid invoices, 100 payments.
 * Idempotent: clears prior simulation operational rows for the same company email, then re-seeds inside a transaction.
 */
class DemoOperationsSeeder extends Seeder
{
    public const COMPANY_EMAIL = 'simulation.operations@demo.local';

    public const OWNER_EMAIL = 'simulation.owner@demo.local';

    public const OWNER_PASSWORD = 'SimulationDemo123!';

    public function run(): void
    {
        DB::transaction(function () {
            $company = Company::firstOrCreate(
                ['email' => self::COMPANY_EMAIL],
                [
                    'uuid'      => (string) Str::uuid(),
                    'name'      => 'Simulation Operations Demo',
                    'name_ar'   => 'محاكاة العمليات',
                    'phone'     => '+966500000001',
                    'city'      => 'Riyadh',
                    'country'   => 'SAU',
                    'currency'  => 'SAR',
                    'timezone'  => 'Asia/Riyadh',
                    'status'    => CompanyStatus::Active,
                    'is_active' => true,
                ]
            );

            $branch = Branch::firstOrCreate(
                ['company_id' => $company->id, 'is_main' => true],
                [
                    'uuid'      => (string) Str::uuid(),
                    'name'      => 'Simulation Main',
                    'name_ar'   => 'الفرع الرئيسي للمحاكاة',
                    'code'      => 'SIM-MAIN',
                    'status'    => BranchStatus::Active,
                    'is_active' => true,
                ]
            );

            Subscription::firstOrCreate(
                ['company_id' => $company->id],
                [
                    'uuid'           => (string) Str::uuid(),
                    'plan'           => 'professional',
                    'status'         => SubscriptionStatus::Active,
                    'starts_at'      => now()->subMonth(),
                    'ends_at'        => now()->addYear(),
                    'amount'         => 0,
                    'currency'       => 'SAR',
                    'max_branches'   => 10,
                    'max_users'      => 50,
                ]
            );

            $owner = User::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email'      => self::OWNER_EMAIL,
                ],
                [
                    'branch_id' => $branch->id,
                    'name'      => 'Simulation Owner',
                    'password'  => self::OWNER_PASSWORD,
                    'role'      => UserRole::Owner,
                    'status'    => UserStatus::Active,
                    'is_active' => true,
                ]
            );

            $this->clearOperationalData($company->id);

            $customers = [];
            for ($i = 0; $i < 20; $i++) {
                $customers[] = Customer::create([
                    'uuid'       => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'branch_id'  => $branch->id,
                    'type'       => 'b2c',
                    'name'       => fake()->name(),
                    'name_ar'    => 'عميل '.($i + 1),
                    'email'      => 'sim-cust-'.$i.'-'.Str::lower(Str::random(6)).'@demo.local',
                    'phone'      => '+9665'.sprintf('%08d', 10000000 + $i),
                    'is_active'  => true,
                ]);
            }

            $year = (int) now()->format('Y');
            $prevHash = hash('sha256', 'simulation-genesis-'.$company->id);
            $taxRate  = 0.15;

            $demoProducts = [];
            for ($p = 1; $p <= 8; $p++) {
                $demoProducts[] = Product::create([
                    'uuid'               => (string) Str::uuid(),
                    'company_id'         => $company->id,
                    'created_by_user_id' => $owner->id,
                    'name'               => 'Simulation Product '.$p,
                    'name_ar'            => 'منتج محاكاة '.$p,
                    'sku'                => 'SIM-DEMO-'.sprintf('%02d', $p),
                    'sale_price'         => 100,
                    'cost_price'         => 50,
                    'tax_rate'           => 15,
                    'product_type'       => ProductType::Physical,
                    'is_active'          => true,
                    'track_inventory'    => false,
                ]);
            }

            for ($n = 1; $n <= 100; $n++) {
                $customer = $customers[array_rand($customers)];
                $total    = round(random_int(5_000, 250_000) / 100, 2);
                $subtotal = round($total / (1 + $taxRate), 4);
                $tax      = round($total - $subtotal, 4);
                $issued   = now()->subDays(random_int(0, 90))->subMinutes(random_int(0, 59 * 24));

                $invNo = sprintf('SIM-%d-%05d', $year, $n);
                $hash  = hash('sha256', $invNo.$issued->timestamp.$total);

                $invoice = Invoice::create([
                    'uuid'                 => (string) Str::uuid(),
                    'company_id'           => $company->id,
                    'branch_id'            => $branch->id,
                    'customer_id'          => $customer->id,
                    'created_by_user_id'   => $owner->id,
                    'invoice_number'       => $invNo,
                    'invoice_counter'      => $n,
                    'type'                 => 'sale',
                    'status'               => InvoiceStatus::Paid,
                    'customer_type'        => 'b2c',
                    'subtotal'             => $subtotal,
                    'discount_amount'      => 0,
                    'tax_amount'           => $tax,
                    'total'                => $total,
                    'paid_amount'          => $total,
                    'due_amount'           => 0,
                    'currency'             => 'SAR',
                    'invoice_hash'         => $hash,
                    'previous_invoice_hash'=> $prevHash,
                    'issued_at'            => $issued,
                    'due_at'               => $issued->copy()->addDays(random_int(7, 45)),
                ]);
                $prevHash = $hash;

                $prod = $demoProducts[array_rand($demoProducts)];
                InvoiceItem::create([
                    'company_id'       => $company->id,
                    'invoice_id'       => $invoice->id,
                    'product_id'       => $prod->id,
                    'name'             => $prod->name,
                    'sku'              => $prod->sku,
                    'quantity'         => 1,
                    'unit_price'       => $subtotal,
                    'discount_amount'  => 0,
                    'tax_rate'         => 15,
                    'tax_amount'       => $tax,
                    'subtotal'         => $subtotal,
                    'total'            => $total,
                ]);

                $methods = ['cash', 'card', 'bank_transfer'];
                $method  = $methods[array_rand($methods)];

                Payment::create([
                    'uuid'               => (string) Str::uuid(),
                    'company_id'         => $company->id,
                    'branch_id'          => $branch->id,
                    'invoice_id'         => $invoice->id,
                    'created_by_user_id' => $owner->id,
                    'method'             => $method,
                    'payment_method'     => $method,
                    'amount'             => $total,
                    'currency'           => 'SAR',
                    'reference'          => 'SIM-PAY-'.Str::upper(Str::random(10)),
                    'status'             => 'completed',
                    'meta'               => ['simulation' => true],
                    'created_at'         => $issued->copy()->addMinutes(random_int(0, 120)),
                ]);
            }

            $this->command?->info(sprintf(
                'DemoOperationsSeeder: company_id=%d customers=%d invoices=%d payments=%d',
                $company->id,
                Customer::withoutGlobalScope('tenant')->where('company_id', $company->id)->count(),
                Invoice::withoutGlobalScope('tenant')->where('company_id', $company->id)->count(),
                Payment::withoutGlobalScope('tenant')->where('company_id', $company->id)->count(),
            ));
            $this->command?->info(
                'Simulation staff login (بوابة فريق العمل): '.self::OWNER_EMAIL.' / '.self::OWNER_PASSWORD
            );
        });
    }

    private function clearOperationalData(int $companyId): void
    {
        Payment::withoutGlobalScope('tenant')->where('company_id', $companyId)->delete();
        Invoice::withoutGlobalScope('tenant')->where('company_id', $companyId)->forceDelete();
        Product::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->where('sku', 'like', 'SIM-DEMO-%')
            ->forceDelete();
        Customer::withoutGlobalScope('tenant')->where('company_id', $companyId)->forceDelete();
    }
}
