<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PolicyRule;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight setup checklist signals for the staff dashboard (tenant-scoped models apply).
 */
class OnboardingController extends Controller
{
    public function setupStatus(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        if ($companyId < 1) {
            return response()->json([
                'data' => $this->emptyPayload(),
                'trace_id' => app('trace_id'),
            ]);
        }

        $company = Company::query()->find($companyId);

        $companyProfileOk = $this->isCompanyProfileComplete($company);

        $branchesCount = Branch::query()->where('company_id', $companyId)->count();
        $usersCount = User::query()->where('company_id', $companyId)->count();
        $policiesCount = PolicyRule::query()->where('company_id', $companyId)->count();
        $productsCount = Product::query()->where('company_id', $companyId)->count();

        $hasPricedCatalog = Service::query()
            ->where('company_id', $companyId)
            ->where('base_price', '>', 0)
            ->exists()
            || Product::query()
                ->where('company_id', $companyId)
                ->where('sale_price', '>', 0)
                ->exists();

        return response()->json([
            'data' => [
                'company_profile_ok' => $companyProfileOk,
                'branches_count' => $branchesCount,
                'users_count' => $usersCount,
                'policies_count' => $policiesCount,
                'products_count' => $productsCount,
                'has_priced_catalog' => $hasPricedCatalog,
                'team_ok' => $usersCount >= 2,
                'permissions_ok' => $policiesCount >= 1,
                'branch_ok' => $branchesCount >= 1,
                'product_ok' => $productsCount >= 1,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function emptyPayload(): array
    {
        return [
            'company_profile_ok' => false,
            'branches_count' => 0,
            'users_count' => 0,
            'policies_count' => 0,
            'products_count' => 0,
            'has_priced_catalog' => false,
            'team_ok' => false,
            'permissions_ok' => false,
            'branch_ok' => false,
            'product_ok' => false,
        ];
    }

    private function isCompanyProfileComplete(?Company $company): bool
    {
        if ($company === null) {
            return false;
        }
        $name = trim((string) $company->name);
        if ($name === '') {
            return false;
        }
        $contact = trim((string) $company->phone) !== '' || trim((string) $company->email) !== '';
        if (! $contact) {
            return false;
        }
        $idOk = trim((string) $company->tax_number) !== '' || trim((string) $company->cr_number) !== '';

        return $idOk;
    }
}
