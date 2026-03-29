<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Companies", description="Company management")
 */
class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Company::class);
        $companies = Company::paginate(25);
        return response()->json(['data' => $companies, 'trace_id' => app('trace_id')]);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create(array_merge(
            $request->validated(),
            ['uuid' => Str::uuid()]
        ));
        return response()->json(['data' => $company, 'trace_id' => app('trace_id')], 201);
    }

    public function show(int $id): JsonResponse
    {
        $company = Company::with(['branches', 'activeSubscription'])->findOrFail($id);
        $this->authorize('view', $company);
        return response()->json(['data' => $company, 'trace_id' => app('trace_id')]);
    }

    public function update(UpdateCompanyRequest $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);
        $company->update($request->validated());
        return response()->json(['data' => $company, 'trace_id' => app('trace_id')]);
    }

    public function destroy(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('delete', $company);
        $company->delete();
        return response()->json(['message' => 'Company deleted.', 'trace_id' => app('trace_id')]);
    }

    // ─── Logo ────────────────────────────────────────────────────────────
    public function uploadLogo(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $request->validate([
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp,svg'],
        ]);

        if ($company->logo_url) {
            $this->deleteOldFile($company->logo_url);
        }

        $path = $request->file('logo')->store("logos/company_{$id}", 'public');
        $url  = Storage::disk('public')->url($path);
        $company->update(['logo_url' => $url]);

        return response()->json(['data' => ['logo_url' => $url], 'trace_id' => app('trace_id')]);
    }

    // ─── Signature ───────────────────────────────────────────────────────
    public function uploadSignature(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $request->validate([
            'signature' => ['required', 'image', 'max:1024', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($company->signature_url) {
            $this->deleteOldFile($company->signature_url);
        }

        $path = $request->file('signature')->store("signatures/company_{$id}", 'public');
        $url  = Storage::disk('public')->url($path);
        $company->update(['signature_url' => $url]);

        return response()->json(['data' => ['signature_url' => $url], 'trace_id' => app('trace_id')]);
    }

    public function deleteSignature(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        if ($company->signature_url) {
            $this->deleteOldFile($company->signature_url);
        }
        $company->update(['signature_url' => null]);

        return response()->json(['message' => 'Signature deleted.', 'trace_id' => app('trace_id')]);
    }

    // ─── Stamp ───────────────────────────────────────────────────────────
    public function uploadStamp(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $request->validate([
            'stamp' => ['required', 'image', 'max:1024', 'mimes:jpg,jpeg,png,webp'],
        ]);

        if ($company->stamp_url) {
            $this->deleteOldFile($company->stamp_url);
        }

        $path = $request->file('stamp')->store("stamps/company_{$id}", 'public');
        $url  = Storage::disk('public')->url($path);
        $company->update(['stamp_url' => $url]);

        return response()->json(['data' => ['stamp_url' => $url], 'trace_id' => app('trace_id')]);
    }

    public function deleteStamp(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        if ($company->stamp_url) {
            $this->deleteOldFile($company->stamp_url);
        }
        $company->update(['stamp_url' => null]);

        return response()->json(['message' => 'Stamp deleted.', 'trace_id' => app('trace_id')]);
    }

    // ─── Settings (WhatsApp / Email / Tracking) ─────────────────────────
    public function getSettings(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('view', $company);

        return response()->json(['data' => $company->settings ?? [], 'trace_id' => app('trace_id')]);
    }

    public function updateSettings(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $request->validate([
            'whatsapp'        => ['sometimes', 'array'],
            'email'           => ['sometimes', 'array'],
            'tracking'        => ['sometimes', 'array'],
            'loyalty'         => ['sometimes', 'array'],
            'cameras'         => ['sometimes', 'array'],
            'booking_portal'  => ['sometimes', 'array'],
            'invoice_options' => ['sometimes', 'array'],
        ]);

        $current  = $company->settings ?? [];
        $merged   = array_merge($current, $request->only([
            'whatsapp', 'email', 'tracking', 'loyalty', 'cameras', 'booking_portal', 'invoice_options',
        ]));
        $company->update(['settings' => $merged]);

        return response()->json(['data' => $merged, 'trace_id' => app('trace_id')]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────
    private function deleteOldFile(string $url): void
    {
        $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
