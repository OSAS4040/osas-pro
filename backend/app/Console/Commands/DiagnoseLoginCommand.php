<?php

namespace App\Console\Commands;

use App\Enums\CompanyStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Support\SubscriptionAccessEvaluator;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Safe, read-only login path inspection (no tokens issued, no secrets printed).
 * Mirrors AuthController::completeLogin resolution order for the matched user.
 */
class DiagnoseLoginCommand extends Command
{
    protected $signature = 'auth:diagnose
        {email : Email address as sent to POST /api/v1/auth/login}
        {--verify : Prompt for password (hidden) and report whether login would succeed; does not create a token}
        {--force : Allow in production (shows account/tenant topology; use only by operators)}';

    protected $description = 'Diagnose API login for an email: candidates, account/company/branch/subscription gates (no password or hash output).';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force (exposes whether accounts exist and tenant linkage).');

            return self::FAILURE;
        }

        if (app()->environment('production') && $this->option('force')) {
            if (! $this->confirm('Running in production may reveal account topology to anyone with server access. Continue?', false)) {
                return self::FAILURE;
            }
        }

        $emailNorm = $this->normalizeEmail((string) $this->argument('email'));
        if ($emailNorm === '' || ! filter_var($emailNorm, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email after normalization (same rules as LoginRequest: trim, lower, strip zero-width).');

            return self::FAILURE;
        }

        $table = (new User)->getTable();
        $candidates = User::withoutGlobalScope('tenant')
            ->whereRaw('LOWER(TRIM('.$table.'.email)) = ?', [$emailNorm])
            ->orderBy($table.'.id')
            ->get();

        if ($candidates->isEmpty()) {
            $this->warn("No user rows for normalized email [{$emailNorm}]. API would return 401.");

            return self::SUCCESS;
        }

        $this->info('Candidates (same query as login; order by id — first password match wins):');
        $rows = [];
        foreach ($candidates as $u) {
            $hash = $u->getRawOriginal('password');
            $rows[] = [
                'id'         => $u->id,
                'company_id' => $u->company_id,
                'branch_id'  => $u->branch_id ?? '—',
                'role'       => (string) $u->getRawOriginal('role'),
                'is_active'  => $u->is_active ? 'yes' : 'no',
                'status'     => $this->enumish($u->status),
                'can_login'  => $u->canLogin() ? 'yes' : 'no',
                'password'   => $this->passwordStorageSummary($hash),
            ];
        }
        $this->table(
            ['id', 'company_id', 'branch_id', 'role', 'is_active', 'status', 'can_login', 'password_field'],
            $rows
        );

        $companyIds = $candidates->pluck('company_id')->unique()->values();
        $this->newLine();
        $this->info('Company / subscription (by company_id):');
        foreach ($companyIds as $cid) {
            $this->line($this->formatCompanySubscriptionBlock((int) $cid));
        }

        $tokensReady = Schema::hasTable('personal_access_tokens');
        $this->newLine();
        $this->line('personal_access_tokens table: '.($tokensReady ? 'present' : 'MISSING (token step would fail — run migrations)'));

        if (! $this->option('verify')) {
            $this->newLine();
            $this->comment('Tip: add --verify to test password matching and post-password gates (password is prompted hidden; never printed).');

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('--verify is not allowed in production without --force.');

            return self::FAILURE;
        }

        $plain = $this->secret('Password (hidden; not logged by this command)');
        if (! is_string($plain) || $plain === '') {
            $this->warn('Empty password — skipping verification.');

            return self::SUCCESS;
        }

        $plain = $this->normalizePasswordScalar($plain);

        $matched = null;
        foreach ($candidates as $candidate) {
            $hash = $candidate->getRawOriginal('password');
            if (is_string($hash) && $hash !== '' && Hash::check($plain, $hash)) {
                $matched = $candidate;
                break;
            }
        }

        if (! $matched) {
            $this->warn('No candidate row accepts this password. API would return 401.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Password matches user id {$matched->id} (company_id {$matched->company_id}) — post-password checks:");

        if (! $matched->canLogin()) {
            $this->error('BLOCK: Account is disabled (is_active / status). API → 403.');

            return self::SUCCESS;
        }

        $company = Company::withTrashed()->find($matched->company_id);
        if (! $company) {
            $this->error('BLOCK: company_id not found. API → 403.');

            return self::SUCCESS;
        }
        if ($company->trashed()) {
            $this->error('BLOCK: company soft-deleted. API → 403.');

            return self::SUCCESS;
        }
        if ($company->status === CompanyStatus::Suspended) {
            $this->error('BLOCK: company suspended. API → 403.');

            return self::SUCCESS;
        }

        if ($matched->branch_id !== null) {
            $branch = Branch::withTrashed()
                ->withoutGlobalScope('tenant')
                ->where('id', $matched->branch_id)
                ->where('company_id', $matched->company_id)
                ->first();
            if (! $branch || $branch->trashed()) {
                $this->error('BLOCK: invalid or missing branch for this user. API → 403.');

                return self::SUCCESS;
            }
        }

        $request = Request::create('/api/v1/auth/login', 'POST', ['email' => $emailNorm, 'password' => $plain]);
        $subBlock = SubscriptionAccessEvaluator::evaluate((int) $matched->company_id, $request, true);
        if ($subBlock !== null) {
            $this->error("BLOCK: subscription — HTTP {$subBlock['code']}: {$subBlock['message']}");

            return self::SUCCESS;
        }

        if (! $tokensReady) {
            $this->error('BLOCK: cannot issue Sanctum token (personal_access_tokens missing). API → 503.');

            return self::SUCCESS;
        }

        $this->info('All gates pass — login would return 200 with a token (this command does not issue one).');

        return self::SUCCESS;
    }

    private function normalizeEmail(string $email): string
    {
        $raw = trim($email);
        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw);

        return Str::lower($raw);
    }

    private function normalizePasswordScalar(string $p): string
    {
        return preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $p) ?? $p;
    }

    private function enumish(mixed $v): string
    {
        if ($v instanceof \BackedEnum) {
            return (string) $v->value;
        }
        if (is_object($v) && method_exists($v, 'value')) {
            return (string) $v->value;
        }

        return (string) $v;
    }

    /**
     * Classify stored password without revealing the hash.
     */
    private function passwordStorageSummary(mixed $hash): string
    {
        if (! is_string($hash) || $hash === '') {
            return 'missing';
        }

        $info = @password_get_info($hash);
        if (is_array($info) && ($info['algoName'] ?? '') === 'bcrypt') {
            return 'bcrypt';
        }

        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            return 'bcrypt';
        }

        return 'other';
    }

    private function formatCompanySubscriptionBlock(int $companyId): string
    {
        $company = Company::withTrashed()->find($companyId);
        if (! $company) {
            return "company_id {$companyId}: MISSING";
        }

        $parts = [
            "company_id {$companyId}",
            'name='.Str::limit((string) $company->name, 40),
            'status='.$this->enumish($company->status),
            'trashed='.($company->trashed() ? 'yes' : 'no'),
        ];

        $row = DB::table('subscriptions')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        if (! $row) {
            $parts[] = 'subscription=NONE (login would likely 402)';

            return implode(' | ', $parts);
        }

        $parts[] = 'sub_status='.(string) ($row->status ?? '');
        $parts[] = 'ends_at='.(string) ($row->ends_at ?? 'null');

        return implode(' | ', $parts);
    }
}
