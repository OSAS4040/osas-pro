<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\LoginBootstrapService;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates or updates a standalone platform operator (no tenant): company_id null, owner role.
 * Platform API access still requires SAAS_PLATFORM_ADMIN_EMAILS and/or SAAS_PLATFORM_ADMIN_PHONES on the server.
 */
final class ProvisionPlatformAdminCommand extends Command
{
    protected $signature = 'platform-admin:provision
        {--phone= : Mobile number (e.g. 05xxxxxxxx or 9665xxxxxxxx)}
        {--email= : Optional login email; if omitted a unique internal address is generated}
        {--name=مدير المنصة : Display name}
        {--role=super_admin : IAM platform_role (super_admin, platform_admin, support_agent, finance_admin, operations_admin, auditor)}';

    protected $description = 'Provision or refresh a platform admin user (hashed password, no company linkage).';

    public function handle(LoginBootstrapService $loginBootstrap): int
    {
        $phoneRaw = trim((string) $this->option('phone'));
        if ($phoneRaw === '') {
            $this->error('Missing --phone=…');

            return self::FAILURE;
        }

        $variants = PhoneNormalizer::comparisonVariants($phoneRaw);
        if ($variants === []) {
            $this->error('Invalid phone number after normalization.');

            return self::FAILURE;
        }

        $candidates = $loginBootstrap->usersMatchingPhoneVariants($variants);
        if ($candidates->count() > 1) {
            $this->error('Multiple users share this phone; resolve duplicates before provisioning.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Initial password (hidden)');
        $confirm = (string) $this->secret('Confirm password (hidden)');
        if ($password === '' || $password !== $confirm) {
            $this->error('Passwords are empty or do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $msg) {
                $this->error($msg);
            }

            return self::FAILURE;
        }

        $phoneStored = PhoneNormalizer::normalizeForStorage($phoneRaw);
        $name = trim((string) $this->option('name')) ?: 'مدير المنصة';

        $emailOpt = trim((string) $this->option('email'));
        $email = $emailOpt !== '' ? Str::lower($emailOpt) : $this->allocateInternalEmail($phoneStored);

        if ($emailOpt !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid --email value.');

            return self::FAILURE;
        }

        if ($this->emailTakenByAnotherNullTenant($email, $candidates->first()?->id)) {
            $this->error('That email is already used by another user without a company.');

            return self::FAILURE;
        }

        $user = $candidates->first();
        if ($user === null) {
            $user = new User;
            $user->uuid = (string) Str::uuid();
        }

        $platformRole = strtolower(trim((string) $this->option('role')));
        $allowedRoles = array_keys((array) config('platform_roles.roles', []));
        if ($platformRole === '' || ! in_array($platformRole, $allowedRoles, true)) {
            $this->error('Invalid --role. Allowed: '.implode(', ', $allowedRoles));

            return self::FAILURE;
        }

        $user->forceFill([
            'company_id'         => null,
            'branch_id'          => null,
            'org_unit_id'        => null,
            'customer_id'        => null,
            'name'               => $name,
            'email'              => $email,
            'phone'              => $phoneStored,
            'phone_verified_at'  => now(),
            'password'           => $password,
            'role'               => UserRole::Owner,
            'status'             => UserStatus::Active,
            'is_active'          => true,
            'is_platform_user'   => true,
            'platform_role'      => $platformRole,
            'account_type'       => null,
            'registration_stage' => 'phone_verified',
        ]);
        $user->save();

        $this->info('Platform admin user saved (id='.$user->id.'). IAM role: '.$platformRole);
        $this->line('Either keep SAAS_PLATFORM_ADMIN_* in sync with this account, or rely on is_platform_user (already set).');
        $this->line('Optional env allowlist for defense in depth:');
        $this->line('  - SAAS_PLATFORM_ADMIN_PHONES should include this mobile (comma-separated), and/or');
        $this->line('  - SAAS_PLATFORM_ADMIN_EMAILS should include: '.$email);
        $this->line('Login: POST /api/v1/auth/login with identifier = phone or email; expect account_context.principal_kind = platform_employee and home_route_hint = /admin.');
        $this->warn('Change the password after first login; do not reuse the bootstrap password.');

        return self::SUCCESS;
    }

    private function allocateInternalEmail(string $phoneDigits): string
    {
        $slug = preg_replace('/[^\d]/', '', $phoneDigits) ?: (string) random_int(100000, 999999);

        return 'platform+'.$slug.'@internal.platform.sa';
    }

    private function emailTakenByAnotherNullTenant(string $emailNorm, ?int $exceptUserId): bool
    {
        $q = User::withoutGlobalScope('tenant')
            ->whereNull('company_id')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm]);

        if ($exceptUserId !== null) {
            $q->where('id', '!=', $exceptUserId);
        }

        return $q->exists();
    }
}
