<?php

namespace App\Services\Config;

use App\Models\Branch;
use App\Models\Company;
use App\Models\VerticalProfile;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerticalProfileGovernanceService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function assignCompanyProfile(Company $company, ?string $verticalProfileCode, int $actorUserId, ?string $reason = null): Company
    {
        return DB::transaction(function () use ($company, $verticalProfileCode, $actorUserId, $reason) {
            $lockedCompany = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $normalized = $this->normalizeCode($verticalProfileCode);
            $this->validateProfileCode($normalized);
            $this->validateReasonPolicy((string) $lockedCompany->vertical_profile_code, $normalized, $reason);

            $before = ['vertical_profile_code' => $lockedCompany->vertical_profile_code];
            $action = $this->assignmentAction((string) $before['vertical_profile_code'], $normalized, 'company');

            if (($before['vertical_profile_code'] ?? null) !== $normalized) {
                $lockedCompany->forceFill(['vertical_profile_code' => $normalized]);
                $lockedCompany->save();
            }

            $this->auditLogger->log(
                action: $action,
                subjectType: Company::class,
                subjectId: (int) $lockedCompany->id,
                before: array_merge($before, ['reason' => null]),
                after: ['vertical_profile_code' => $normalized, 'reason' => $reason],
                companyId: (int) $lockedCompany->id,
                branchId: null,
                userId: $actorUserId
            );

            return $lockedCompany->fresh();
        });
    }

    public function assignBranchProfile(Branch $branch, ?string $verticalProfileCode, int $actorUserId, ?string $reason = null): Branch
    {
        return DB::transaction(function () use ($branch, $verticalProfileCode, $actorUserId, $reason) {
            $lockedBranch = Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $normalized = $this->normalizeCode($verticalProfileCode);
            $this->validateProfileCode($normalized);
            $this->validateReasonPolicy((string) $lockedBranch->vertical_profile_code, $normalized, $reason);

            $before = ['vertical_profile_code' => $lockedBranch->vertical_profile_code];
            $action = $this->assignmentAction((string) $before['vertical_profile_code'], $normalized, 'branch');

            if (($before['vertical_profile_code'] ?? null) !== $normalized) {
                $lockedBranch->forceFill(['vertical_profile_code' => $normalized]);
                $lockedBranch->save();
            }

            $this->auditLogger->log(
                action: $action,
                subjectType: Branch::class,
                subjectId: (int) $lockedBranch->id,
                before: array_merge($before, ['reason' => null]),
                after: ['vertical_profile_code' => $normalized, 'reason' => $reason],
                companyId: (int) $lockedBranch->company_id,
                branchId: (int) $lockedBranch->id,
                userId: $actorUserId
            );

            return $lockedBranch->fresh();
        });
    }

    private function normalizeCode(?string $value): ?string
    {
        $trimmed = $value !== null ? trim($value) : null;
        return $trimmed === '' ? null : $trimmed;
    }

    private function validateProfileCode(?string $code): void
    {
        if ($code === null) {
            return;
        }

        $exists = VerticalProfile::query()->where('code', $code)->where('is_active', true)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'vertical_profile_code' => 'Selected vertical profile is invalid or inactive.',
            ]);
        }
    }

    private function validateReasonPolicy(string $beforeCode, ?string $afterCode, ?string $reason): void
    {
        $before = trim($beforeCode) === '' ? null : $beforeCode;
        if ($before === $afterCode) {
            return;
        }

        $isReassignment = $before !== null && $afterCode !== null && $before !== $afterCode;
        $isUnassignment = $before !== null && $afterCode === null;
        if (($isReassignment || $isUnassignment) && (! is_string($reason) || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'reason' => 'Reason is required for reassignment or unassignment.',
            ]);
        }
    }

    private function assignmentAction(string $beforeCode, ?string $afterCode, string $target): string
    {
        $before = trim($beforeCode) === '' ? null : $beforeCode;
        if ($before === null && $afterCode !== null) {
            return "vertical_profile.assigned.{$target}";
        }
        if ($before !== null && $afterCode === null) {
            return "vertical_profile.unassigned.{$target}";
        }
        if ($before !== $afterCode) {
            return "vertical_profile.reassigned.{$target}";
        }

        return "vertical_profile.assignment.noop.{$target}";
    }
}

