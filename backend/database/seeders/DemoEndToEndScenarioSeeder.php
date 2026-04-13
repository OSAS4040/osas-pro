<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryType;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * سيناريو تجريبي متكامل: شجرة حسابات، موظفون (HR)، حضور، قيود يومية، فريق تشغيل منصة،
 * وبيانات تشغيلية خفيفة لشركة «أسس برو» حتى تظهر أرقام في التقارير وذكاء التشغيل.
 * Idempotent حيث أمكن (firstOrCreate / تحقق من وجود السجلات).
 */
final class DemoEndToEndScenarioSeeder extends Seeder
{
    private const SEED_TAG = 'e2e_demo_scenario';

    public function run(): void
    {
        $coa = new ChartOfAccountSeeder;

        foreach ($this->targetCompanies() as $company) {
            $coa->seedForCompany($company);
            $this->seedEmployeesForCompany($company);
            $this->seedAttendanceForCompany($company);
            $this->seedJournalAdjustments($company);
        }

        $this->seedOsasProOperationalSurface();
        $this->seedPlatformOperationsTeam();
    }

    /**
     * @return list<Company>
     */
    private function targetCompanies(): array
    {
        $out = [];
        foreach (['demo@autocenter.sa', 'hq@osas.sa'] as $email) {
            $c = Company::withoutGlobalScope('tenant')->where('email', $email)->first();
            if ($c !== null) {
                $out[] = $c;
            }
        }

        return $out;
    }

    private function seedEmployeesForCompany(Company $company): void
    {
        $branch = Branch::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('is_main', true)
            ->first()
            ?? Branch::withoutGlobalScope('tenant')->where('company_id', $company->id)->orderBy('id')->first();

        if ($branch === null) {
            return;
        }

        $standalone = [
            [
                'number' => 'E2E-HR-'.$company->id,
                'name' => 'موظف موارد بشرية — تجريبي',
                'position' => 'HR Specialist',
                'department' => 'الموارد البشرية',
                'salary' => 9500.00,
            ],
            [
                'number' => 'E2E-ACC-'.$company->id,
                'name' => 'محاسب تشغيل — تجريبي',
                'position' => 'Accountant',
                'department' => 'المالية',
                'salary' => 11000.00,
            ],
            [
                'number' => 'E2E-OPS-'.$company->id,
                'name' => 'مشرف صيانة — تجريبي',
                'position' => 'Supervisor',
                'department' => 'العمليات',
                'salary' => 8800.00,
            ],
        ];

        foreach ($standalone as $row) {
            Employee::withoutGlobalScope('tenant')->firstOrCreate(
                ['company_id' => $company->id, 'employee_number' => $row['number']],
                [
                    'uuid'        => (string) Str::uuid(),
                    'branch_id'   => $branch->id,
                    'user_id'     => null,
                    'name'        => $row['name'],
                    'phone'       => null,
                    'email'       => null,
                    'position'    => $row['position'],
                    'department'  => $row['department'],
                    'hire_date'   => now()->subMonths(6)->toDateString(),
                    'base_salary' => $row['salary'],
                    'status'      => 'active',
                ]
            );
        }

        foreach ($this->workshopUserEmailsForCompany($company) as $email) {
            $user = User::withoutGlobalScope('tenant')
                ->where('company_id', $company->id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower($email)])
                ->first();
            if ($user === null || Employee::withoutGlobalScope('tenant')->where('user_id', $user->id)->exists()) {
                continue;
            }

            Employee::withoutGlobalScope('tenant')->create([
                'uuid'              => (string) Str::uuid(),
                'company_id'        => $company->id,
                'branch_id'         => $branch->id,
                'user_id'           => $user->id,
                'employee_number'   => 'E2E-U-'.$user->id,
                'name'              => $user->name,
                'phone'             => $user->phone,
                'email'             => $user->email,
                'position'          => 'Team Member',
                'department'        => 'الإدارة',
                'hire_date'         => now()->subMonths(3)->toDateString(),
                'base_salary'       => 7000,
                'status'            => 'active',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function workshopUserEmailsForCompany(Company $company): array
    {
        if ($company->email === 'hq@osas.sa') {
            return ['admin@osas.sa'];
        }

        return ['owner@demo.sa', 'manager@demo.sa', 'staff@demo.sa', 'tech@demo.sa'];
    }

    private function seedAttendanceForCompany(Company $company): void
    {
        $employees = Employee::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->take(6)
            ->get();

        if ($employees->isEmpty()) {
            return;
        }

        $branch = $employees->first()->branch_id;

        foreach ($employees as $emp) {
            for ($d = 1; $d <= 10; $d++) {
                $day = now()->subDays($d)->setTime(8, 15, 0);
                $this->firstOrCreateAttendance($company->id, $branch, $emp->id, 'check_in', $day);
                $this->firstOrCreateAttendance($company->id, $branch, $emp->id, 'check_out', (clone $day)->setTime(16, 45, 0));
            }
        }
    }

    private function firstOrCreateAttendance(int $companyId, ?int $branchId, int $employeeId, string $type, Carbon $at): void
    {
        $exists = AttendanceLog::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where('type', $type)
            ->whereDate('logged_at', $at->toDateString())
            ->exists();
        if ($exists) {
            return;
        }

        AttendanceLog::create([
            'company_id'  => $companyId,
            'branch_id'   => $branchId,
            'employee_id' => $employeeId,
            'type'        => $type,
            'logged_at'   => $at,
            'device_id'   => self::SEED_TAG,
            'is_valid'    => true,
        ]);
    }

    private function seedJournalAdjustments(Company $company): void
    {
        $owner = User::withoutGlobalScope('tenant')->where('company_id', $company->id)->orderBy('id')->first();
        $uid = $owner?->id;

        $cash = ChartOfAccount::where('company_id', $company->id)->where('code', '1010')->value('id');
        $rev = ChartOfAccount::where('company_id', $company->id)->where('code', '4100')->value('id');
        if (! $cash || ! $rev) {
            return;
        }

        $amount = $company->email === 'hq@osas.sa' ? 25000.0 : 15000.0;
        $suffix = 'E2E-JE-'.$company->id;

        if (JournalEntry::where('company_id', $company->id)->where('description', 'like', '%'.$suffix.'%')->exists()) {
            return;
        }

        DB::transaction(function () use ($company, $uid, $cash, $rev, $amount, $suffix): void {
            $branchId = Branch::withoutGlobalScope('tenant')->where('company_id', $company->id)->value('id');
            $entry = JournalEntry::create([
                'uuid'               => (string) Str::uuid(),
                'company_id'         => $company->id,
                'branch_id'          => $branchId,
                'entry_number'       => 'JE'.$company->id.'-'.substr(str_replace('-', '', (string) Str::uuid()), 0, 20),
                'type'               => JournalEntryType::Adjustment,
                'source_type'        => null,
                'source_id'          => null,
                'entry_date'         => now()->subDays(2)->toDateString(),
                'description'        => 'قيد افتتاحي للمحاكاة — '.$suffix,
                'total_debit'        => $amount,
                'total_credit'       => $amount,
                'currency'           => 'SAR',
                'created_by_user_id' => $uid,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => (int) $cash,
                'type'             => 'debit',
                'amount'           => $amount,
                'description'      => 'حركة مدينة — نقدية',
            ]);
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => (int) $rev,
                'type'             => 'credit',
                'amount'           => $amount,
                'description'      => 'إيراد خدمات — ترحيل تجريبي',
            ]);
        });
    }

    private function seedOsasProOperationalSurface(): void
    {
        $company = Company::withoutGlobalScope('tenant')->where('email', 'hq@osas.sa')->first();
        if ($company === null) {
            return;
        }

        if (Customer::withoutGlobalScope('tenant')->where('company_id', $company->id)->count() >= 2) {
            return;
        }

        $branch = Branch::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('is_main', true)
            ->first();
        $owner = User::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', ['admin@osas.sa'])
            ->first();
        if ($branch === null || $owner === null) {
            return;
        }

        $customers = [];
        foreach (
            [
                ['name' => 'عميل أسس — تجاري', 'phone' => '+966501000501'],
                ['name' => 'عميل أسس — فردي', 'phone' => '+966501000502'],
            ] as $row
        ) {
            $customers[] = Customer::withoutGlobalScope('tenant')->firstOrCreate(
                ['company_id' => $company->id, 'phone' => $row['phone']],
                [
                    'uuid'       => (string) Str::uuid(),
                    'branch_id'  => $branch->id,
                    'name'       => $row['name'],
                    'email'      => null,
                    'type'       => 'b2c',
                    'is_active'  => true,
                ]
            );
        }

        $product = Product::withoutGlobalScope('tenant')->firstOrCreate(
            ['company_id' => $company->id, 'sku' => 'OSAS-SEED-SVC-01'],
            [
                'uuid'               => (string) Str::uuid(),
                'created_by_user_id' => $owner->id,
                'name'               => 'خدمة صيانة — بذرة أسس',
                'name_ar'            => 'خدمة صيانة — بذرة أسس',
                'sale_price'         => 400,
                'cost_price'         => 200,
                'tax_rate'           => 15,
                'product_type'       => ProductType::Service,
                'is_active'          => true,
                'track_inventory'    => false,
            ]
        );

        $year = (int) now()->format('Y');
        foreach ([1 => InvoiceStatus::Paid, 2 => InvoiceStatus::Paid, 3 => InvoiceStatus::Pending] as $n => $status) {
            $total = match ($n) {
                1 => 1150.0,
                2 => 2300.0,
                default => 575.0,
            };
            $subtotal = round($total / 1.15, 2);
            $tax = round($total - $subtotal, 2);
            $cust = $customers[($n - 1) % 2];
            $invNo = sprintf('OSAS-SEED-%d-%02d', $year, $n);

            Invoice::withoutGlobalScope('tenant')->firstOrCreate(
                ['company_id' => $company->id, 'invoice_number' => $invNo],
                [
                    'uuid'               => (string) Str::uuid(),
                    'branch_id'          => $branch->id,
                    'customer_id'        => $cust->id,
                    'created_by_user_id' => $owner->id,
                    'invoice_counter'    => 0,
                    'type'               => 'sale',
                    'status'             => $status,
                    'customer_type'      => 'b2c',
                    'subtotal'           => $subtotal,
                    'tax_amount'         => $tax,
                    'total'              => $total,
                    'paid_amount'        => $status === InvoiceStatus::Paid ? $total : 0,
                    'due_amount'         => $status === InvoiceStatus::Paid ? 0 : $total,
                    'currency'           => 'SAR',
                    'issued_at'          => now()->subDays(5 + $n),
                    'due_at'             => now()->addDays(20),
                    'invoice_hash'       => hash('sha256', $invNo.$company->id),
                    'previous_invoice_hash' => hash('sha256', 'osas-seed-genesis'),
                ]
            );
        }
    }

    private function seedPlatformOperationsTeam(): void
    {
        $company = Company::withoutGlobalScope('tenant')->where('email', 'hq@osas.sa')->first();
        if ($company === null) {
            return;
        }

        $branch = Branch::withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->where('is_main', true)
            ->first();
        if ($branch === null) {
            return;
        }

        $team = [
            [
                'email' => 'platform-ops-hr@osas.sa',
                'name'  => 'منسق سياسات المنصة (HR)',
                'phone' => '966599999881',
                'role'  => UserRole::Manager,
            ],
            [
                'email' => 'platform-ops-finance@osas.sa',
                'name'  => 'مراقب مالي للمنصة',
                'phone' => '966599999882',
                'role'  => UserRole::Accountant,
            ],
        ];

        foreach ($team as $i => $row) {
            $attrs = [
                'company_id'         => $company->id,
                'branch_id'          => $branch->id,
                'name'               => $row['name'],
                'password'           => '12345678',
                'phone'              => PhoneNormalizer::normalizeForStorage($row['phone']),
                'phone_verified_at'  => now(),
                'registration_stage' => 'phone_verified',
                'role'               => $row['role'],
                'status'             => UserStatus::Active,
                'is_active'          => true,
                'is_platform_user'   => true,
                'platform_role'      => $i === 0 ? 'super_admin' : 'platform_admin',
            ];

            $existing = User::withoutGlobalScope('tenant')->whereRaw('LOWER(TRIM(email)) = ?', [strtolower($row['email'])])->first();
            if ($existing === null) {
                User::withoutGlobalScope('tenant')->create(array_merge($attrs, [
                    'uuid'  => (string) Str::uuid(),
                    'email' => $row['email'],
                ]));
            } else {
                $existing->forceFill(array_merge($attrs, ['email' => $row['email']]))->save();
            }
        }
    }
}
