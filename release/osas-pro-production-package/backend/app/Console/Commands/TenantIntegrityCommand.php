<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Detects and optionally repairs broken user ↔ company ↔ branch links (tenant integrity).
 */
class TenantIntegrityCommand extends Command
{
    protected $signature = 'tenant:integrity {--fix-branches : Set branch_id to company main branch when branch is missing or mismatched}';

    protected $description = 'Verify tenant links on users (company + branch); optional safe repair of branch_id only';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix-branches');

        $badCompany = 0;
        $badBranch  = 0;
        $repaired   = 0;

        User::query()->withoutGlobalScope('tenant')->orderBy('id')->chunkById(200, function ($users) use (&$badCompany, &$badBranch, &$repaired, $fix): void {
            foreach ($users as $user) {
                $company = Company::withTrashed()->find($user->company_id);
                if (! $company || $company->trashed()) {
                    $badCompany++;
                    $this->line(" user_id={$user->id} email={$user->email} problem=missing_or_trashed_company company_id={$user->company_id}");

                    continue;
                }

                if ($user->branch_id === null) {
                    continue;
                }

                $branchOk = Branch::query()
                    ->withoutGlobalScope('tenant')
                    ->where('id', $user->branch_id)
                    ->where('company_id', $user->company_id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($branchOk) {
                    continue;
                }

                $badBranch++;
                $this->line(" user_id={$user->id} email={$user->email} problem=invalid_branch branch_id={$user->branch_id} company_id={$user->company_id}");

                if (! $fix) {
                    continue;
                }

                $main = Branch::query()
                    ->withoutGlobalScope('tenant')
                    ->where('company_id', $user->company_id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('is_main')
                    ->orderBy('id')
                    ->first();

                if ($main) {
                    $user->withoutGlobalScopes()->update(['branch_id' => $main->id]);
                    $repaired++;
                    $this->info("  → repaired branch_id={$main->id} (main/first branch)");
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Summary: users_with_bad_company=%d users_with_bad_branch=%d branches_repaired=%d',
            $badCompany,
            $badBranch,
            $repaired
        ));

        if ($badCompany > 0) {
            $this->warn('Users with missing/trashed companies need manual cleanup or full re-seed (DB FK should normally prevent new orphans).');

            return self::FAILURE;
        }

        if ($badBranch > 0 && ! $fix) {
            $this->warn('Re-run with --fix-branches to assign a valid main branch where possible.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
