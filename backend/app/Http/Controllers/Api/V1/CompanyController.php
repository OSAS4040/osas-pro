<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Config\AssignVerticalProfileRequest;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\VerticalProfile;
use App\Services\Config\ResolvedConfigVisibilityService;
use App\Services\Config\VerticalProfileGovernanceService;
use App\Support\Media\TenantUploadDisk;
use App\Support\TenantBusinessFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    public function assignVerticalProfile(
        AssignVerticalProfileRequest $request,
        int $id,
        VerticalProfileGovernanceService $governance
    ): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $company = $governance->assignCompanyProfile(
            company: $company,
            verticalProfileCode: $request->input('vertical_profile_code'),
            actorUserId: (int) $request->user()->id,
            reason: $request->input('reason')
        );

        return response()->json(['data' => $company, 'trace_id' => app('trace_id')]);
    }

    public function effectiveConfig(
        int $id,
        ResolvedConfigVisibilityService $visibility
    ): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('view', $company);

        $plan = Subscription::query()->where('company_id', $company->id)->latest('id')->value('plan');
        $verticalCode = $company->vertical_profile_code ?: null;
        if ($verticalCode === null) {
            $verticalCode = VerticalProfile::query()->where('is_active', true)->orderBy('id')->value('code');
        }

        $resolved = $visibility->resolveForCompany((int) $company->id, $plan, $verticalCode, (int) $this->resolveActorId());

        return response()->json([
            'data' => [
                'context' => [
                    'company_id' => $company->id,
                    'plan' => $plan,
                    'vertical' => $verticalCode,
                    'branch_id' => null,
                ],
                'config' => $resolved,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function resolveActorId(): int
    {
        return (int) optional(request()->user())->id;
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
            TenantUploadDisk::deleteIfExists($company->logo_url);
        }

        $disk = TenantUploadDisk::name();
        $path = $request->file('logo')->store("logos/company_{$id}", $disk);
        $url  = Storage::disk($disk)->url($path);
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
            TenantUploadDisk::deleteIfExists($company->signature_url);
        }

        $disk = TenantUploadDisk::name();
        $path = $request->file('signature')->store("signatures/company_{$id}", $disk);
        $url  = Storage::disk($disk)->url($path);
        $company->update(['signature_url' => $url]);

        return response()->json(['data' => ['signature_url' => $url], 'trace_id' => app('trace_id')]);
    }

    public function deleteSignature(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        if ($company->signature_url) {
            TenantUploadDisk::deleteIfExists($company->signature_url);
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
            TenantUploadDisk::deleteIfExists($company->stamp_url);
        }

        $disk = TenantUploadDisk::name();
        $path = $request->file('stamp')->store("stamps/company_{$id}", $disk);
        $url  = Storage::disk($disk)->url($path);
        $company->update(['stamp_url' => $url]);

        return response()->json(['data' => ['stamp_url' => $url], 'trace_id' => app('trace_id')]);
    }

    public function deleteStamp(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        if ($company->stamp_url) {
            TenantUploadDisk::deleteIfExists($company->stamp_url);
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
            'dashcam'         => ['sometimes', 'array'],
            'loyalty'         => ['sometimes', 'array'],
            'cameras'         => ['sometimes', 'array'],
            'booking_portal'  => ['sometimes', 'array'],
            'invoice_options' => ['sometimes', 'array'],
            'smart_user_guide' => ['sometimes', 'array'],
            'ui'              => ['sometimes', 'array'],
            'referrals'       => ['sometimes', 'array'],
            'invoice_footer_note' => ['sometimes', 'string'],
            'default_vat_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'accepted_payment_methods' => ['sometimes', 'array'],
            'pos'             => ['sometimes', 'array'],
            'documents_notifications' => ['sometimes', 'array'],
            'documents_registry' => ['sometimes', 'array'],
            'supplier_contract_notifications' => ['sometimes', 'array'],
        ]);

        $current  = $company->settings ?? [];
        $merged   = array_merge($current, $request->only([
            'whatsapp', 'email', 'tracking', 'dashcam', 'loyalty', 'cameras', 'booking_portal', 'invoice_options',
            'smart_user_guide', 'ui', 'referrals', 'invoice_footer_note', 'default_vat_rate', 'accepted_payment_methods',
            'pos', 'documents_notifications', 'documents_registry', 'supplier_contract_notifications',
        ]));
        $company->update(['settings' => $merged]);

        return response()->json(['data' => $merged, 'trace_id' => app('trace_id')]);
    }

    public function featureProfile(int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('view', $company);

        $settings = is_array($company->settings) ? $company->settings : [];
        $profile = $settings['business_profile'] ?? [];
        $businessType = (string) ($profile['business_type'] ?? 'service_center');
        $featureMatrix = is_array($profile['feature_matrix'] ?? null)
            ? $profile['feature_matrix']
            : [];

        $effectiveMatrix = TenantBusinessFeatures::effectiveMatrix($company);

        return response()->json([
            'data' => [
                'business_type' => $businessType,
                'feature_matrix' => $featureMatrix,
                'effective_feature_matrix' => $effectiveMatrix,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function updateFeatureProfile(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $payload = $request->validate([
            'business_type' => ['required', 'string', 'in:service_center,retail,fleet_operator'],
            'feature_matrix' => ['nullable', 'array'],
        ]);

        $currentSettings = is_array($company->settings) ? $company->settings : [];
        $profile = $currentSettings['business_profile'] ?? [];
        $profile['business_type'] = $payload['business_type'];
        if (array_key_exists('feature_matrix', $payload)) {
            $profile['feature_matrix'] = $payload['feature_matrix'] ?? [];
        }

        $mergedSettings = array_merge($currentSettings, ['business_profile' => $profile]);
        $company->update(['settings' => $mergedSettings]);
        $company->refresh();

        $customMatrix = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];

        return response()->json([
            'data' => [
                'business_type' => $profile['business_type'],
                'feature_matrix' => $customMatrix,
                'effective_feature_matrix' => TenantBusinessFeatures::effectiveMatrix($company),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * نقطة رسمية لأزرار «إرسال تجريبي» في واجهة التكاملات — لا 404؛ الإرسال الفعلي يُفعّل لاحقاً حسب المزود.
     */
    public function testIntegrationChannel(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('update', $company);

        $request->validate([
            'channel' => ['required', 'string', 'in:whatsapp,email'],
        ]);

        $channel = $request->string('channel')->toString();

        return response()->json([
            'message' => $channel === 'whatsapp'
                ? 'تم استلام طلب اختبار واتساب. الإرسال التجريبي عبر المزود الخارجي غير مفعّل على الخادم بعد؛ احفظ الإعدادات أولاً.'
                : 'تم استلام طلب اختبار البريد. الإرسال التجريبي عبر SMTP/SendGrid غير مفعّل على الخادم بعد؛ احفظ الإعدادات أولاً.',
            'sent' => false,
            'trace_id' => app('trace_id'),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────
    public function testPosConnection(Request $request, int $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $this->authorize('view', $company);

        $data = $request->validate([
            'ip' => ['required', 'string', 'max:255'],
            'protocol' => ['nullable', 'string', 'in:http,https,tcp'],
            'timeout_ms' => ['nullable', 'integer', 'min:200', 'max:10000'],
        ]);

        $host = trim($data['ip']);
        $protocol = $data['protocol'] ?? 'http';
        $timeout = (int) ($data['timeout_ms'] ?? 1800);
        $ok = false;
        $latencyMs = null;
        $detail = null;

        $start = microtime(true);
        try {
            if ($protocol === 'tcp') {
                $target = $host;
                if (!str_contains($target, ':')) {
                    $target .= ':9100';
                }
                [$tcpHost, $tcpPort] = explode(':', $target, 2);
                $conn = @fsockopen($tcpHost, (int) $tcpPort, $errno, $errstr, max(1, (int) ceil($timeout / 1000)));
                if ($conn !== false) {
                    fclose($conn);
                    $ok = true;
                } else {
                    $detail = trim((string) $errstr) ?: "tcp_error_{$errno}";
                }
            } else {
                $url = str_starts_with($host, 'http://') || str_starts_with($host, 'https://')
                    ? $host
                    : "{$protocol}://{$host}";
                $response = Http::timeout(max(1, (int) ceil($timeout / 1000)))->get($url);
                $ok = $response->successful() || in_array($response->status(), [401, 403]);
                if (!$ok) {
                    $detail = "http_status_{$response->status()}";
                }
            }
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
        } finally {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);
        }

        return response()->json([
            'data' => [
                'ok' => $ok,
                'latency_ms' => $latencyMs,
                'protocol' => $protocol,
                'detail' => $detail,
            ],
            'trace_id' => app('trace_id'),
        ], $ok ? 200 : 422);
    }

}
