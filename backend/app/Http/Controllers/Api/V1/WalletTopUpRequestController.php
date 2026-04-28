<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WalletTopUpRequest;
use App\Services\Config\ConfigResolverService;
use App\Services\Wallet\WalletTransferInstructionsPdfService;
use App\Services\WalletTopUpRequestService;
use App\Support\Media\TenantUploadDisk;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class WalletTopUpRequestController extends Controller
{
    public function __construct(
        private readonly WalletTopUpRequestService $service,
        private readonly ConfigResolverService $configResolver,
        private readonly WalletTransferInstructionsPdfService $transferInstructionsPdf,
    ) {}

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', WalletTopUpRequest::class);
        $this->ensureWalletEnabled($request);

        $data = $request->validate([
            'customer_id' => 'required|integer',
            'target' => 'required|in:individual,fleet',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:bank_transfer,cash,other',
            'reference_number' => 'nullable|string|max:120',
            'notes_from_customer' => 'nullable|string|max:2000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($data['payment_method'] === 'bank_transfer' && ! $request->hasFile('receipt')) {
            return response()->json([
                'message' => 'إيصال التحويل مطلوب عند اختيار تحويل بنكي.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        try {
            $row = $this->service->createRequest(
                $this->companyId(),
                $this->branchId(),
                (int) $request->user()->id,
                $data,
                $request->file('receipt'),
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $this->transform($row)], 201);
    }

    public function my(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WalletTopUpRequest::class);
        $this->ensureWalletEnabled($request);

        $q = WalletTopUpRequest::query()
            ->where('requested_by', $request->user()->id)
            ->with(['customer:id,name,phone'])
            ->orderByDesc('id');

        $paginator = $q->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($r) => $this->transform($r)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureWalletEnabled($request);
        $row = WalletTopUpRequest::query()->findOrFail($id);
        Gate::authorize('view', $row); // مراجعة أو صاحب الطلب (مع صلاحية العرض)

        $row->load(['customer:id,name,phone', 'requester:id,name', 'reviewer:id,name', 'approvedWalletTransaction:id,uuid,amount,created_at']);

        return response()->json(['data' => $this->transform($row, true), 'trace_id' => app('trace_id')]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureWalletEnabled($request);
        $row = WalletTopUpRequest::query()->findOrFail($id);
        Gate::authorize('updateReturned', $row);

        $data = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_method' => 'sometimes|in:bank_transfer,cash,other',
            'reference_number' => 'nullable|string|max:120',
            'notes_from_customer' => 'nullable|string|max:2000',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if (($data['payment_method'] ?? $row->payment_method->value) === 'bank_transfer'
            && ! $row->receipt_path
            && ! $request->hasFile('receipt')) {
            return response()->json([
                'message' => 'إيصال التحويل مطلوب لتحويل بنكي.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        try {
            $row = $this->service->updateReturnedRequest($row, $data, $request->file('receipt'));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $this->transform($row), 'trace_id' => app('trace_id')]);
    }

    public function resubmit(Request $request, int $id): JsonResponse
    {
        $this->ensureWalletEnabled($request);
        $row = WalletTopUpRequest::query()->findOrFail($id);
        Gate::authorize('resubmit', $row);

        try {
            $row = $this->service->resubmit($row);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $this->transform($row), 'trace_id' => app('trace_id')]);
    }

    public function receipt(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $this->ensureWalletEnabled($request);
        $row = WalletTopUpRequest::query()->findOrFail($id);
        Gate::authorize('downloadReceipt', $row);

        if (! $row->receipt_path) {
            return response()->json(['message' => 'لا يوجد إيصال مرفوع.', 'trace_id' => app('trace_id')], 404);
        }

        $disk = TenantUploadDisk::name();
        if (! Storage::disk($disk)->exists($row->receipt_path)) {
            return response()->json(['message' => 'الملف غير موجود على الخادم.', 'trace_id' => app('trace_id')], 404);
        }

        return Storage::disk($disk)->response($row->receipt_path, 'receipt-'.$row->uuid, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * PDF منسّق: بيانات العميل الطالب + مرجع التحويل + جدول الحسابات المعرّفة للمنشأة.
     */
    public function transferInstructions(Request $request, int $id): Response
    {
        $this->ensureWalletEnabled($request);
        $row = WalletTopUpRequest::query()->with(['customer:id,name,phone', 'requester:id,name'])->findOrFail($id);
        Gate::authorize('downloadTransferInstructions', $row);

        try {
            return $this->transferInstructionsPdf->streamPdf($row);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }
    }

    private function companyId(): int
    {
        return (int) app('tenant_company_id');
    }

    private function branchId(): ?int
    {
        $b = app()->bound('tenant_branch_id') ? app('tenant_branch_id') : null;

        return $b ? (int) $b : null;
    }

    private function ensureWalletEnabled(Request $request): void
    {
        $vertical = Company::query()->where('id', $this->companyId())->value('vertical_profile_code');
        $enabled = $this->configResolver->resolveBool('wallet.enabled', [
            'plan' => null,
            'vertical' => $vertical,
            'company_id' => $this->companyId(),
            'branch_id' => $this->branchId(),
        ], true);
        if (! $enabled) {
            abort(403, 'Wallet is disabled by configuration.');
        }
    }

    private function transform(WalletTopUpRequest $r, bool $detailed = false): array
    {
        $base = [
            'id' => $r->id,
            'uuid' => $r->uuid,
            'customer_id' => $r->customer_id,
            'customer' => $r->relationLoaded('customer') ? [
                'id' => $r->customer?->id,
                'name' => $r->customer?->name,
                'phone' => $r->customer?->phone,
            ] : null,
            'requested_by' => $r->requested_by,
            'target' => $r->target,
            'amount' => (string) $r->amount,
            'currency' => $r->currency,
            'payment_method' => $r->payment_method->value,
            'reference_number' => $r->reference_number,
            'has_receipt' => $r->receipt_path !== null && $r->receipt_path !== '',
            'status' => $r->status->value,
            'notes_from_customer' => $r->notes_from_customer,
            'review_notes' => $r->review_notes,
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base['requester'] = $r->relationLoaded('requester') ? ['id' => $r->requester?->id, 'name' => $r->requester?->name] : null;
            $base['reviewer'] = $r->relationLoaded('reviewer') ? ['id' => $r->reviewer?->id, 'name' => $r->reviewer?->name] : null;
            $base['approved_wallet_transaction_id'] = $r->approved_wallet_transaction_id;
            if ($r->relationLoaded('approvedWalletTransaction') && $r->approvedWalletTransaction) {
                $base['approved_wallet_transaction'] = [
                    'id' => $r->approvedWalletTransaction->id,
                    'uuid' => $r->approvedWalletTransaction->uuid,
                    'amount' => (string) $r->approvedWalletTransaction->amount,
                    'created_at' => $r->approvedWalletTransaction->created_at?->toIso8601String(),
                ];
            } else {
                $base['approved_wallet_transaction'] = null;
            }
        }

        return $base;
    }
}
