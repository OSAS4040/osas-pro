<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\BillingModelPolicyService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Support\Media\TenantUploadDisk;
use App\Support\TenantBusinessFeatures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(name="Invoices", description="Invoice management")
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PaymentService $paymentService,
        private readonly WalletService $walletService,
        private readonly BillingModelPolicyService $billingModelPolicy,
        private readonly InvoicePdfService $invoicePdfService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/invoices",
     *     tags={"Invoices"},
     *     summary="List invoices",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="customer_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $base = $this->invoicesIndexBaseQuery($request);

        $portalCounts = null;
        if ($request->boolean('include_portal_counts')) {
            $portalCounts = [
                'all' => (clone $base)->count(),
                'paid' => (clone $base)->where('status', InvoiceStatus::Paid)->count(),
                'pending' => $this->portalBucketCount(clone $base, 'pending'),
                'overdue' => $this->portalBucketCount(clone $base, 'overdue'),
            ];
        }

        $perPage = max(1, min((int) ($request->per_page ?? 25), 100));

        $invoices = (clone $base)
            ->with(['customer', 'branch', 'createdBy'])
            ->when($request->filled('status') && ! $request->filled('portal_quick_status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('portal_quick_status'), fn ($q) => $this->applyPortalQuickFilter($q, (string) $request->portal_quick_status))
            ->orderByDesc('id')
            ->paginate($perPage);

        $payload = ['data' => $invoices, 'trace_id' => app('trace_id')];
        if ($portalCounts !== null) {
            $payload['portal_counts'] = $portalCounts;
        }

        return response()->json($payload);
    }

    /** Filters used for portal list + aggregate counts (excludes portal_quick_status). */
    private function invoicesIndexBaseQuery(Request $request): Builder
    {
        $q = Invoice::query()
            ->when($request->company_id, fn ($q) => $q->where('company_id', (int) $request->company_id))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->filled('billing_flow_type'), fn ($q) => $q->where('billing_flow_type', (string) $request->billing_flow_type))
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->from, fn ($q) => $q->whereDate('issued_at', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('issued_at', '<=', $request->to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(function ($q) use ($s) {
                    $q->where('invoice_number', 'ilike', "%{$s}%")
                        ->orWhere('notes', 'ilike', "%{$s}%")
                        ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'ilike', "%{$s}%"));
                });
            });

        $q = $this->applyCustomerFleetInvoiceQueryConstraints($q, $request->user());

        return $this->applyWorkshopExecutionPartnerInvoiceListing($q, $request->user());
    }

    /**
     * Customer / fleet actors must not list internal (e.g. provider→platform) invoices even with invoices.view.
     */
    private function applyCustomerFleetInvoiceQueryConstraints(Builder $q, ?User $user): Builder
    {
        if (! $user) {
            return $q;
        }
        if ($user->role->isWorkshopSide() || ($user->is_platform_user ?? false)) {
            return $q;
        }
        if (! $user->role->isCustomer() && ! $user->role->isFleetSide()) {
            return $q;
        }
        $q->customerPortalVisible();
        if ($user->customer_id) {
            $q->where('customer_id', $user->customer_id);
        } else {
            $q->whereRaw('1 = 0');
        }

        return $q;
    }

    private function assertCustomerFleetMayAccessInvoice(?User $user, Invoice $invoice): void
    {
        if (! $user) {
            return;
        }
        if ($user->role->isWorkshopSide() || ($user->is_platform_user ?? false)) {
            return;
        }
        if (! $user->role->isCustomer() && ! $user->role->isFleetSide()) {
            return;
        }
        if (! $user->customer_id) {
            throw (new ModelNotFoundException)->setModel(Invoice::class, [$invoice->id]);
        }
        if (! $invoice->isCustomerPortalVisible() || (int) $invoice->customer_id !== (int) $user->customer_id) {
            throw (new ModelNotFoundException)->setModel(Invoice::class, [$invoice->id]);
        }
    }

    private function applyWorkshopExecutionPartnerInvoiceListing(Builder $q, ?User $user): Builder
    {
        if (! $user || ! $user->role->isWorkshopSide()) {
            return $q;
        }
        if ($user->is_platform_user ?? false) {
            return $q;
        }
        $company = $user->company ?? ($user->company_id ? Company::find($user->company_id) : null);
        if (! $company || ! TenantBusinessFeatures::platformExecutionPartner($company)) {
            return $q;
        }

        return $q->omitPlatformToCustomerForWorkshopPartner();
    }

    /**
     * ورشة «شريك التنفيذ» لا تصل لفاتورة المنصّة للعميل (لا قائمة ولا تفاصيل ولا PDF).
     */
    private function assertWorkshopExecutionPartnerMayAccessInvoice(?User $user, Invoice $invoice): void
    {
        if (! $user || ! $user->role->isWorkshopSide()) {
            return;
        }
        if ($user->is_platform_user ?? false) {
            return;
        }
        $company = $user->company ?? ($user->company_id ? Company::find($user->company_id) : null);
        if (! $company || ! TenantBusinessFeatures::platformExecutionPartner($company)) {
            return;
        }
        if ((string) ($invoice->billing_flow_type ?? '') === 'platform_to_customer') {
            throw (new ModelNotFoundException)->setModel(Invoice::class, [$invoice->id]);
        }
    }

    private function workshopExecutionPartnerBlocksWorkshopIssuance(?User $user): bool
    {
        if (! $user || ! $user->role->isWorkshopSide()) {
            return false;
        }
        if ($user->is_platform_user ?? false) {
            return false;
        }
        $company = $user->company ?? ($user->company_id ? Company::find($user->company_id) : null);

        return $company !== null && TenantBusinessFeatures::platformExecutionPartner($company);
    }

    private function applyPortalQuickFilter(Builder $q, string $portal): void
    {
        $portal = strtolower(trim($portal));
        $today = now()->startOfDay();

        if ($portal === 'paid') {
            $q->where('status', InvoiceStatus::Paid);

            return;
        }

        $q->whereNotIn('status', [InvoiceStatus::Cancelled, InvoiceStatus::Refunded]);

        if ($portal === 'overdue') {
            $q->where('status', '!=', InvoiceStatus::Paid)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $today);

            return;
        }

        if ($portal === 'pending') {
            $q->where('status', '!=', InvoiceStatus::Paid)
                ->where(function ($q2) use ($today) {
                    $q2->whereNull('due_at')->orWhere('due_at', '>=', $today);
                });
        }
    }

    private function portalBucketCount(Builder $base, string $bucket): int
    {
        $q = clone $base;
        $this->applyPortalQuickFilter($q, $bucket);

        return $q->count();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices",
     *     tags={"Invoices"},
     *     summary="Create an invoice (B2B / manual)",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"items"},
     *
     *         @OA\Property(property="customer_id", type="integer"),
     *         @OA\Property(property="vehicle_id", type="integer"),
     *         @OA\Property(property="type", type="string", enum={"sale","refund","proforma"}),
     *         @OA\Property(property="customer_type", type="string", enum={"b2c","b2b"}),
     *         @OA\Property(property="discount_amount", type="number"),
     *         @OA\Property(property="notes", type="string"),
     *         @OA\Property(property="due_at", type="string", format="date"),
     *         @OA\Property(property="items", type="array", @OA\Items(type="object")),
     *         @OA\Property(property="payment", type="object")
     *     )),
     *
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse"),
     *     @OA\Response(response=409, description="Idempotency payload mismatch")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        if ($this->workshopExecutionPartnerBlocksWorkshopIssuance($request->user())) {
            return response()->json([
                'message' => 'إصدار الفواتير يدوياً غير متاح لوضع شريك تنفيذ المنصة؛ تُدار الفواتير آلياً حسب التعاقد.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $request->user()->company_id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $data = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'customer_type' => 'nullable|in:b2c,b2b',
            'type' => 'nullable|in:sale,refund,proforma',
            'discount_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'due_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.service_id' => 'nullable|integer|exists:services,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'payment' => 'nullable|array',
            'payment.method' => 'required_with:payment|in:cash,card,wallet,bank_transfer',
            'payment.amount' => 'required_with:payment|numeric|min:0',
            'payment.reference' => 'nullable|string',
        ]);

        $data['idempotency_key'] = $idempotencyKey;
        $user = $request->user();

        try {
            $invoice = $this->invoiceService->createInvoice(
                data: $data,
                companyId: $user->company_id,
                branchId: $user->branch_id,
                userId: $user->id,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 409);
        }

        return response()->json(['data' => $invoice, 'trace_id' => app('trace_id')], 201);
    }

    /**
     * POST /invoices/{id}/pay — record payment; wallet debits use existing WalletService entry points (same rules as create-invoice payment).
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card,wallet,bank_transfer',
            'reference' => 'nullable|string|max:255',
            'wallet_idempotency_key' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $invoiceProbe = Invoice::where('id', $id)->firstOrFail();
        $this->assertCustomerFleetMayAccessInvoice($user, $invoiceProbe);
        $this->assertWorkshopExecutionPartnerMayAccessInvoice($user, $invoiceProbe);
        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $invoiceProbe->company_id);
            if ($data['method'] === 'wallet') {
                $this->billingModelPolicy->assertPrepaidWalletTopUp((int) $invoiceProbe->company_id);
            }
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        try {
            $result = DB::transaction(function () use ($data, $id, $user) {
                $invoice = Invoice::where('id', $id)->lockForUpdate()->firstOrFail();

                if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Refunded, InvoiceStatus::Draft], true)) {
                    throw new \DomainException('This invoice cannot accept a payment in its current status.');
                }

                if ($data['method'] === 'wallet' && ! $invoice->customer_id) {
                    throw new \DomainException('Wallet payment requires an invoice customer.');
                }

                $traceId = trim((string) (app('trace_id') ?? '')) ?: Str::uuid()->toString();

                $payment = $this->paymentService->createPayment(
                    invoice: $invoice,
                    amount: (float) $data['amount'],
                    method: $data['method'],
                    userId: $user->id,
                    traceId: $traceId,
                    branchId: $invoice->branch_id,
                    reference: $data['reference'] ?? null,
                );

                $invoice->refresh();

                if ($data['method'] === 'wallet' && $invoice->customer_id) {
                    $walletKey = $data['wallet_idempotency_key']
                        ?? ($invoice->idempotency_key !== null && $invoice->idempotency_key !== ''
                            ? $invoice->idempotency_key.'_wallet'
                            : null);
                    if ($walletKey === null || $walletKey === '') {
                        throw new \DomainException('wallet_idempotency_key is required when method is wallet.');
                    }

                    $vehicleId = $invoice->vehicle_id;

                    if ($vehicleId && $invoice->customer_type === 'b2b') {
                        $this->walletService->debitVehicleForInvoice(
                            companyId: $invoice->company_id,
                            customerId: $invoice->customer_id,
                            vehicleId: $vehicleId,
                            amount: (float) $payment->amount,
                            invoiceId: $invoice->id,
                            paymentId: $payment->id,
                            userId: $user->id,
                            traceId: $traceId,
                            idempotencyKey: $walletKey,
                            branchId: $invoice->branch_id,
                            notes: null,
                            paymentMode: 'prepaid',
                        );
                    } else {
                        $this->walletService->debitIndividualForInvoice(
                            companyId: $invoice->company_id,
                            customerId: $invoice->customer_id,
                            vehicleId: $vehicleId,
                            amount: (float) $payment->amount,
                            invoiceId: $invoice->id,
                            paymentId: $payment->id,
                            userId: $user->id,
                            traceId: $traceId,
                            idempotencyKey: $walletKey,
                            branchId: $invoice->branch_id,
                            notes: null,
                            paymentMode: 'prepaid',
                        );
                    }
                }

                return ['payment' => $payment, 'invoice' => $invoice->fresh(['payments'])];
            });
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json([
            'data' => $result,
            'trace_id' => app('trace_id'),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Get invoice details",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with([
            'items.product', 'items.service',
            'payments', 'customer', 'vehicle',
            'branch', 'createdBy',
        ])->findOrFail($id);
        $this->assertCustomerFleetMayAccessInvoice($request->user(), $invoice);
        $this->assertWorkshopExecutionPartnerMayAccessInvoice($request->user(), $invoice);

        return response()->json(['data' => $invoice, 'trace_id' => app('trace_id')]);
    }

    /**
     * Download invoice as PDF (server-rendered DomPDF; reliable vs client html2canvas).
     */
    public function pdf(Request $request, Invoice $invoice): Response
    {
        $this->assertCustomerFleetMayAccessInvoice($request->user(), $invoice);
        $this->assertWorkshopExecutionPartnerMayAccessInvoice($request->user(), $invoice);
        $binary = $this->invoicePdfService->render($invoice);

        $safeBase = $invoice->invoice_number !== null && $invoice->invoice_number !== ''
            ? preg_replace('/[^A-Za-z0-9._-]+/', '_', $invoice->invoice_number)
            : 'invoice_'.$invoice->id;

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$safeBase.'.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Update a draft invoice",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);
        $this->assertWorkshopExecutionPartnerMayAccessInvoice($request->user(), $invoice);

        if (in_array($invoice->status->value, ['paid', 'cancelled', 'refunded'])) {
            return response()->json(['message' => 'Cannot update a finalized invoice.'], 422);
        }

        $data = $request->validate([
            'notes' => 'nullable|string',
            'due_at' => 'nullable|date',
            'status' => 'nullable|in:draft,pending,cancelled',
        ]);

        if (array_key_exists('status', $data)) {
            $current = $invoice->status->value;
            $target = (string) $data['status'];
            $allowedTransitions = [
                'draft' => ['pending', 'cancelled'],
                'pending' => ['draft', 'cancelled'],
            ];
            $allowed = $allowedTransitions[$current] ?? [];
            if ($target !== $current && ! in_array($target, $allowed, true)) {
                return response()->json([
                    'message' => "Invoice status transition {$current} -> {$target} is not allowed.",
                    'code' => 'TRANSITION_NOT_ALLOWED',
                    'status' => 409,
                    'trace_id' => app('trace_id'),
                ], 409);
            }
        }

        $invoice->update($data);

        return response()->json(['data' => $invoice->fresh(), 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Delete a draft invoice",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);
        $this->assertWorkshopExecutionPartnerMayAccessInvoice($request->user(), $invoice);

        if ($invoice->status->value !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be deleted.'], 422);
        }

        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted.', 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices/{id}/media",
     *     tags={"Invoices"},
     *     summary="Upload before/after images or video for an invoice",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="before[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="after[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="video", type="string", format="binary"),
     *                 @OA\Property(property="video_link", type="string", format="uri")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function uploadMedia(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);
        $this->assertWorkshopExecutionPartnerMayAccessInvoice($request->user(), $invoice);

        $request->validate([
            'before.*' => 'image|max:5120',
            'after.*' => 'image|max:5120',
            'video' => 'file|mimes:mp4,webm,ogg|max:51200',
            'video_link' => 'nullable|url',
            'provider_invoice_files.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'provider_invoice_links' => 'nullable|array',
            'provider_invoice_links.*' => 'url',
            'provider_invoice_note' => 'nullable|string|max:1000',
        ]);

        $media = $invoice->media ?? [];
        $disk = TenantUploadDisk::name();
        $user = $request->user();

        if ($request->hasFile('before')) {
            $urls = [];
            foreach ($request->file('before') as $file) {
                $path = $file->store("invoices/{$id}/before", $disk);
                $urls[] = Storage::disk($disk)->url($path);
            }
            $media['before'] = array_merge($media['before'] ?? [], $urls);
        }

        if ($request->hasFile('after')) {
            $urls = [];
            foreach ($request->file('after') as $file) {
                $path = $file->store("invoices/{$id}/after", $disk);
                $urls[] = Storage::disk($disk)->url($path);
            }
            $media['after'] = array_merge($media['after'] ?? [], $urls);
        }

        if ($request->hasFile('video')) {
            $path = $request->file('video')->store("invoices/{$id}/video", $disk);
            $media['video_url'] = Storage::disk($disk)->url($path);
        }

        if ($request->filled('video_link')) {
            $media['video_link'] = $request->video_link;
        }

        if ($request->hasFile('provider_invoice_files') || $request->filled('provider_invoice_links')) {
            $isPlatformUser = (bool) ($user->is_platform_user ?? false);
            if (! $user->role->isWorkshopSide() && ! $isPlatformUser) {
                return response()->json([
                    'message' => 'إرفاق فواتير مزود الخدمة متاح لمزود الخدمة أو إدارة المنصة فقط.',
                    'trace_id' => app('trace_id'),
                ], 403);
            }
            if ((string) ($invoice->billing_flow_type ?? '') !== 'provider_to_platform') {
                return response()->json([
                    'message' => 'مرفقات فواتير مزود الخدمة تُحفظ فقط على فواتير مزود الخدمة إلى المنصة.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }

            $attachments = $media['provider_invoice_attachments'] ?? [];
            if ($request->hasFile('provider_invoice_files')) {
                foreach ($request->file('provider_invoice_files') as $file) {
                    $path = $file->store("invoices/{$id}/provider-invoices", $disk);
                    $attachments[] = [
                        'kind' => 'file',
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'path' => $path,
                        'url' => Storage::disk($disk)->url($path),
                        'uploaded_by_user_id' => (int) $user->id,
                        'uploaded_at' => now()->toIso8601String(),
                        'note' => $request->input('provider_invoice_note'),
                    ];
                }
            }
            foreach ((array) $request->input('provider_invoice_links', []) as $link) {
                $attachments[] = [
                    'kind' => 'link',
                    'url' => (string) $link,
                    'uploaded_by_user_id' => (int) $user->id,
                    'uploaded_at' => now()->toIso8601String(),
                    'note' => $request->input('provider_invoice_note'),
                ];
            }
            $media['provider_invoice_attachments'] = $attachments;
        }

        $invoice->update(['media' => $media]);

        return response()->json(['data' => $media, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/invoices/from-work-order/{workOrderId}",
     *     tags={"Invoices"},
     *     summary="Issue invoice from a completed work order (B2B)",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="workOrderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse"),
     *     @OA\Response(response=422, description="Work order not completed or invoice already exists")
     * )
     */
    public function fromWorkOrder(Request $request, int $workOrderId): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return response()->json(['message' => 'Idempotency-Key header is required.', 'trace_id' => app('trace_id')], 422);
        }

        if ($this->workshopExecutionPartnerBlocksWorkshopIssuance($request->user())) {
            return response()->json([
                'message' => 'إصدار فاتورة من أمر العمل عبر الواجهة غير متاح لوضع شريك تنفيذ المنصة؛ يُدار الإصدار آلياً.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $providerPurchase = $request->validate([
            'provider_purchase' => 'nullable|array',
            'provider_purchase.supplier_id' => 'required_with:provider_purchase|integer|exists:suppliers,id',
            'provider_purchase.items' => 'required_with:provider_purchase|array|min:1',
            'provider_purchase.items.*.name' => 'required_with:provider_purchase|string',
            'provider_purchase.items.*.quantity' => 'required_with:provider_purchase|numeric|min:0.001',
            'provider_purchase.items.*.unit_cost' => 'required_with:provider_purchase|numeric|min:0',
            'provider_purchase.items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'provider_purchase.items.*.product_id' => 'nullable|integer|exists:products,id',
            'provider_purchase.items.*.sku' => 'nullable|string',
        ]);

        $order = WorkOrder::findOrFail($workOrderId);

        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $order->company_id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        try {
            $invoice = $this->invoiceService->issueFromWorkOrder(
                order: $order,
                userId: $request->user()->id,
                idempotencyKey: $idempotencyKey,
                providerPurchasePayload: $providerPurchase['provider_purchase'] ?? null,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $invoice, 'trace_id' => app('trace_id')], 201);
    }
}
