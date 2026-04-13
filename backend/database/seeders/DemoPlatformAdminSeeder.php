<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * مشغّل منصة للتطوير/التجريب — مُربَط بشركة «أسس برو» (بعد {@see DefaultAdminSeeder}) حتى تعمل واجهة الفريق
 * وواجهات الـ API التي تتطلب سياق مستأجر، مع الإبقاء على صلاحيات لوحة المنصة.
 * الدخول من /platform/login — يختلف عن {@see DefaultAdminSeeder} (مالك المستأجر الافتراضي).
 */
final class DemoPlatformAdminSeeder extends Seeder
{
    public const DEMO_EMAIL = 'platform-demo@osas.sa';

    public const DEMO_PASSWORD = '12345678';

    /** رقم غير مستخدم في باقي الـ seeders لتفادي تعارض unique(phone). */
    private const DEMO_PHONE_RAW = '966599999991';

    public function run(): void
    {
        $allowInProd = filter_var((string) env('APP_DEMO_PLATFORM_ADMIN', ''), FILTER_VALIDATE_BOOLEAN);
        if (app()->environment('production') && ! $allowInProd) {
            $this->command?->warn(
                'DemoPlatformAdminSeeder skipped in production. Set APP_DEMO_PLATFORM_ADMIN=true to allow, or use platform-admin:provision.',
            );

            return;
        }

        $phoneStored = PhoneNormalizer::normalizeForStorage(self::DEMO_PHONE_RAW);

        $anchorCompany = Company::withoutGlobalScope('tenant')
            ->where('email', 'hq@osas.sa')
            ->orderBy('id')
            ->first();

        $anchorBranch = null;
        if ($anchorCompany !== null) {
            $anchorBranch = Branch::withoutGlobalScope('tenant')
                ->where('company_id', $anchorCompany->id)
                ->where('is_main', true)
                ->orderBy('id')
                ->first()
                ?? Branch::withoutGlobalScope('tenant')
                    ->where('company_id', $anchorCompany->id)
                    ->orderBy('id')
                    ->first();
        }

        if ($anchorCompany === null) {
            $this->command?->warn(
                'DemoPlatformAdminSeeder: company hq@osas.sa not found (run DefaultAdminSeeder first). Platform demo user will have no tenant context.',
            );
        }

        $user = User::withoutGlobalScope('tenant')
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(self::DEMO_EMAIL)])
            ->first();

        if ($user === null) {
            $user = new User;
            $user->uuid = (string) Str::uuid();
        }

        $user->forceFill([
            'company_id'         => $anchorCompany?->id,
            'branch_id'          => $anchorBranch?->id,
            'org_unit_id'        => null,
            'customer_id'        => null,
            'name'               => 'مدير المنصة (تجريبي)',
            'email'              => self::DEMO_EMAIL,
            'phone'              => $phoneStored,
            'phone_verified_at'  => now(),
            'password'           => self::DEMO_PASSWORD,
            'role'               => UserRole::Owner,
            'status'             => UserStatus::Active,
            'is_active'          => true,
            'is_platform_user'   => true,
            'platform_role'      => 'super_admin',
            'account_type'       => null,
            'registration_stage' => 'phone_verified',
        ]);
        $user->save();

        $this->command?->info('Platform demo: '.self::DEMO_EMAIL.' / '.self::DEMO_PASSWORD.' — /platform/login');
    }
}
