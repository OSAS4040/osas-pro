<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerWallet;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Models\Company;
use App\Services\BillingModelPolicyService;
use App\Services\Config\ConfigResolverService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PaymentService $paymentService,
        private readonly ConfigResolverService $configResolver,
        private readonly BillingModelPolicyService $billingModelPolicy,
    ) {}

    private function companyId(): int
    {
        return (int) app('tenant_company_id');
    }

    private function branchId(): ?int
    {
        $bid = app()->bound('tenant_branch_id') ? app('tenant_branch_id') : null;
        return $bid ? (int) $bid : null;
    }

    /**
     * GET /wallet - overview of all company wallets
     */
    public function show(Request $request): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $companyId = $this->companyId();
        $wallets = CustomerWallet::where('company_id', $companyId)
            ->with('customer:id,name,phone')
            ->orderByDesc('updated_at')
            ->paginate(50);

        $totals = [
            'cash'        => CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'cash')->sum('balance'),
            'promotional' => CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'promotional')->sum('balance'),
            'reserved'    => CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'reserved')->sum('balance'),
            'credit'      => CustomerWallet::where('company_id', $companyId)->where('wallet_type', 'credit')->sum('balance'),
        ];

        return response()->json(['wallets' => $wallets, 'totals' => $totals]);
    }

    /**
     * GET /wallets/{customerId}/summary
     */
    public function summary(Request $request, int $customerId): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $summary = $this->walletService->getBalanceSummary($this->companyId(), $customerId);
        return response()->json(['data' => $summary]);
    }

    /**
     * GET /wallet/transactions — requires ?wallet_id= or ?customer_id= (no unscoped company-wide listing).
     * GET /wallets/{walletId}/transactions — path wallet id (legacy alias).
     */
    public function transactions(Request $request, ?int $walletId = null): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $companyId = $this->companyId();

        $resolvedWalletId = $walletId;
        if ($resolvedWalletId === null && $request->filled('wallet_id')) {
            $resolvedWalletId = (int) $request->input('wallet_id');
        }

        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;

        if ($resolvedWalletId === null && $customerId === null) {
            return response()->json([
                'message'  => 'wallet_id or customer_id is required.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $query = WalletTransaction::where('company_id', $companyId)->orderByDesc('created_at');

        if ($resolvedWalletId !== null) {
            CustomerWallet::where('company_id', $companyId)->where('id', $resolvedWalletId)->firstOrFail();
            $query->where('customer_wallet_id', $resolvedWalletId);
        } else {
            $ids = CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $customerId)
                ->pluck('id');
            $query->whereIn('customer_wallet_id', $ids);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $paginator = $query->paginate($perPage);

        return response()->json(['data' => $paginator, 'trace_id' => app('trace_id')]);
    }

    /**
     * POST /wallet/top-up — routes to fleet or individual top-up (same WalletService calls as /wallets/*).
     */
    public function topUp(Request $request): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $data = $request->validate([
            'customer_id'     => 'required|integer',
            'vehicle_id'      => 'nullable|integer',
            'amount'          => 'required|numeric|min:0.01',
            'target'          => 'required|in:individual,fleet',
            'invoice_id'      => 'nullable|integer',
            'payment_id'      => 'nullable|integer',
            'idempotency_key' => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        try {
            $this->billingModelPolicy->assertPrepaidWalletTopUp($this->companyId());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $idem = $data['idempotency_key'] ?? $request->attributes->get('idempotency_key');
        if (! $idem) {
            return response()->json([
                'message'  => 'idempotency_key is required in body or use Idempotency-Key header.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $traceId = trim((string) (app('trace_id') ?? '')) ?: (string) Str::uuid();

        if ($data['target'] === 'fleet') {
            $txn = $this->walletService->topUpFleet(
                companyId:      $this->companyId(),
                customerId:     $data['customer_id'],
                vehicleId:      $data['vehicle_id'] ?? null,
                amount:         (float) $data['amount'],
                invoiceId:      $data['invoice_id'] ?? null,
                paymentId:      $data['payment_id'] ?? null,
                userId:         Auth::id(),
                traceId:        $traceId,
                idempotencyKey: $idem,
                branchId:       $this->branchId(),
                notes:          $data['notes'] ?? null,
            );

            return response()->json(['data' => $txn, 'message' => 'Fleet wallet top-up successful.', 'trace_id' => app('trace_id')], 201);
        }

        $txn = $this->walletService->topUpIndividual(
            companyId:      $this->companyId(),
            customerId:     $data['customer_id'],
            vehicleId:      $data['vehicle_id'] ?? null,
            amount:         (float) $data['amount'],
            invoiceId:      $data['invoice_id'] ?? null,
            paymentId:      $data['payment_id'] ?? null,
            userId:         Auth::id(),
            traceId:        $traceId,
            idempotencyKey: $idem,
            branchId:       $this->branchId(),
            notes:          $data['notes'] ?? null,
        );

        return response()->json(['data' => $txn, 'message' => 'Top-up successful.', 'trace_id' => app('trace_id')], 201);
    }

    /**
     * POST /wallets/top-up/individual
     */
    public function topUpIndividual(Request $request): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $data = $request->validate([
            'customer_id'     => 'required|integer',
            'vehicle_id'      => 'nullable|integer',
            'amount'          => 'required|numeric|min:0.01',
            'invoice_id'      => 'nullable|integer',
            'payment_id'      => 'nullable|integer',
            'idempotency_key' => 'required|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        try {
            $this->billingModelPolicy->assertPrepaidWalletTopUp($this->companyId());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $txn = $this->walletService->topUpIndividual(
            companyId:      $this->companyId(),
            customerId:     $data['customer_id'],
            vehicleId:      $data['vehicle_id'] ?? null,
            amount:         (float) $data['amount'],
            invoiceId:      $data['invoice_id'] ?? null,
            paymentId:      $data['payment_id'] ?? null,
            userId:         Auth::id(),
            traceId:        trim((string) (app('trace_id') ?? '')) ?: (string) Str::uuid(),
            idempotencyKey: $data['idempotency_key'],
            branchId:       $this->branchId(),
            notes:          $data['notes'] ?? null,
        );

        return response()->json(['data' => $txn, 'message' => 'Top-up successful.'], 201);
    }

    /**
     * POST /wallets/top-up/fleet
     */
    public function topUpFleet(Request $request): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $data = $request->validate([
            'customer_id'     => 'required|integer',
            'vehicle_id'      => 'nullable|integer',
            'amount'          => 'required|numeric|min:0.01',
            'invoice_id'      => 'nullable|integer',
            'payment_id'      => 'nullable|integer',
            'idempotency_key' => 'required|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        try {
            $this->billingModelPolicy->assertPrepaidWalletTopUp($this->companyId());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $txn = $this->walletService->topUpFleet(
            companyId:      $this->companyId(),
            customerId:     $data['customer_id'],
            vehicleId:      $data['vehicle_id'] ?? null,
            amount:         (float) $data['amount'],
            invoiceId:      $data['invoice_id'] ?? null,
            paymentId:      $data['payment_id'] ?? null,
            userId:         Auth::id(),
            traceId:        trim((string) (app('trace_id') ?? '')) ?: (string) Str::uuid(),
            idempotencyKey: $data['idempotency_key'],
            branchId:       $this->branchId(),
            notes:          $data['notes'] ?? null,
        );

        return response()->json(['data' => $txn, 'message' => 'Fleet wallet top-up successful.'], 201);
    }

    /**
     * POST /wallets/transfer
     */
    public function transfer(Request $request): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $data = $request->validate([
            'customer_id'     => 'required|integer',
            'vehicle_id'      => 'required|integer',
            'amount'          => 'required|numeric|min:0.01',
            'idempotency_key' => 'required|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $result = $this->walletService->transferToVehicle(
            companyId:      $this->companyId(),
            customerId:     $data['customer_id'],
            vehicleId:      $data['vehicle_id'],
            amount:         (float) $data['amount'],
            invoiceId:      null,
            paymentId:      null,
            userId:         Auth::id(),
            traceId:        trim((string) (app('trace_id') ?? '')) ?: (string) Str::uuid(),
            idempotencyKey: $data['idempotency_key'],
            branchId:       $this->branchId(),
            notes:          $data['notes'] ?? null,
        );

        return response()->json([
            'data'     => $result,
            'message'  => 'Transfer successful.',
            'trace_id' => app('trace_id'),
        ], 201);
    }

    /**
     * POST /wallets/reversal
     */
    public function reverse(Request $request): JsonResponse
    {
        if (! $this->isEnabled($request, 'wallet.enabled', true)) {
            return response()->json(['message' => 'Wallet is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $data = $request->validate([
            'transaction_id'  => 'required|integer',
            'idempotency_key' => 'required|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $companyId = $this->companyId();
        $original  = WalletTransaction::where('company_id', $companyId)
            ->where('id', $data['transaction_id'])
            ->firstOrFail();
        $wallet = CustomerWallet::where('company_id', $companyId)
            ->where('id', $original->customer_wallet_id)
            ->firstOrFail();

        $reversal = $this->walletService->reverse(
            companyId:              $companyId,
            customerId:             $wallet->customer_id,
            vehicleId:              $original->vehicle_id,
            amount:                 (float) $original->amount,
            invoiceId:              $original->invoice_id,
            paymentId:              $original->payment_id,
            userId:                 Auth::id(),
            traceId:                trim((string) (app('trace_id') ?? '')) ?: (string) Str::uuid(),
            idempotencyKey:         $data['idempotency_key'],
            branchId:               $this->branchId(),
            notes:                  $data['notes'] ?? null,
            transactionIdToReverse: $original->id,
        );

        return response()->json(['data' => $reversal, 'message' => 'Reversal created successfully.'], 201);
    }

    public function paymentsByInvoice(Request $request, int $invoiceId): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 50), 100);
        $payments = Payment::where('company_id', $this->companyId())
            ->where('invoice_id', $invoiceId)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json(['data' => $payments, 'trace_id' => app('trace_id')]);
    }

    public function refundPayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => 'nullable|string|max:255',
            'amount'          => 'nullable|numeric|min:0.01',
            'reason'          => 'nullable|string|max:500',
        ]);

        $idem = $data['idempotency_key'] ?? $request->attributes->get('idempotency_key');
        if (! $idem) {
            return response()->json([
                'message'  => 'idempotency_key in body or Idempotency-Key header is required.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $traceId = trim((string) (app('trace_id') ?? '')) ?: (string) Str::uuid();

        try {
            $refund = $this->paymentService->refund(
                $id,
                (int) Auth::id(),
                $traceId,
                $idem,
                isset($data['amount']) ? (float) $data['amount'] : null,
                $data['reason'] ?? null,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $refund, 'trace_id' => app('trace_id')], 201);
    }

    private function isEnabled(Request $request, string $key, bool $default): bool
    {
        $vertical = Company::query()->where('id', $this->companyId())->value('vertical_profile_code');

        return $this->configResolver->resolveBool($key, [
            'plan' => null,
            'vertical' => $vertical,
            'company_id' => $this->companyId(),
            'branch_id' => $this->branchId(),
        ], $default);
    }
}
