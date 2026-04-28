<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Modules\SubscriptionsV2\Models\AuditLog;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Models\SubscriptionChange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * استعلامات قراءة فقط لواجهات إدارة المنصة — لا تعديلات مالية أو ترحيل.
 */
final class PlatformSubscriptionOperationsQueryService
{
    public function __construct(
        private readonly InsightsService $insightsService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateSubscriptionDirectory(int $perPage = 30): LengthAwarePaginator
    {
        $perPage = max(10, min(100, $perPage));

        $paginator = Subscription::withoutGlobalScopes()
            ->with(['company:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage, [
                'id', 'uuid', 'company_id', 'plan', 'status', 'starts_at', 'ends_at', 'grace_ends_at',
                'amount', 'currency', 'features', 'max_branches', 'max_users', 'created_at', 'updated_at',
            ]);

        $collection = $paginator->getCollection();
        if ($collection->isEmpty()) {
            return $paginator;
        }

        $companyIds = $collection->pluck('company_id')->unique()->filter()->map(static fn ($v) => (int) $v)->values()->all();
        $subIds = $collection->pluck('id')->map(static fn ($v) => (int) $v)->values()->all();
        $planSlugs = $collection->pluck('plan')->filter()->unique()->values()->all();

        $plansBySlug = Plan::query()->whereIn('slug', $planSlugs)->get()->keyBy('slug');
        $walletsByCompany = Wallet::withoutGlobalScopes()
            ->whereNull('customer_id')
            ->whereIn('company_id', $companyIds)
            ->get()
            ->keyBy(static fn (Wallet $w) => (int) $w->company_id);

        $lastPoByCompany = PaymentOrder::query()
            ->whereIn('company_id', $companyIds)
            ->selectRaw('company_id, max(updated_at) as last_po_at')
            ->groupBy('company_id')
            ->pluck('last_po_at', 'company_id');

        $lastPayByCompany = Payment::withoutGlobalScopes()
            ->whereIn('company_id', $companyIds)
            ->selectRaw('company_id, max(created_at) as last_pay_at')
            ->groupBy('company_id')
            ->pluck('last_pay_at', 'company_id');

        $lastInvByCompany = Invoice::withoutGlobalScopes()
            ->withTrashed()
            ->where('type', 'subscription')
            ->whereIn('company_id', $companyIds)
            ->selectRaw('company_id, max(issued_at) as last_inv_at')
            ->groupBy('company_id')
            ->pluck('last_inv_at', 'company_id');

        $invoiceBreakdownBySub = $this->invoiceStatusBreakdownForSubscriptions($subIds);
        $companyInvoiceBreakdown = $this->invoiceStatusBreakdownForCompanies($companyIds);

        $mapped = $collection->map(function (Subscription $sub) use (
            $plansBySlug,
            $walletsByCompany,
            $lastPoByCompany,
            $lastPayByCompany,
            $lastInvByCompany,
            $invoiceBreakdownBySub,
            $companyInvoiceBreakdown,
        ): array {
            $plan = $plansBySlug->get((string) $sub->plan);
            $wallet = $walletsByCompany->get((int) $sub->company_id);
            $cid = (int) $sub->company_id;

            $lastActivity = $this->maxCarbonIso([
                $sub->updated_at,
                $lastPoByCompany[$cid] ?? null,
                $lastPayByCompany[$cid] ?? null,
                $lastInvByCompany[$cid] ?? null,
            ]);

            return [
                'subscription' => $this->serializeSubscription($sub),
                'company' => $sub->company ? ['id' => (int) $sub->company->id, 'name' => (string) $sub->company->name] : null,
                'plan_catalog' => $plan ? $this->serializePlan($plan) : null,
                'wallet' => $wallet ? $this->serializeWallet($wallet) : null,
                'invoice_status_breakdown' => $invoiceBreakdownBySub[(int) $sub->id] ?? [],
                'company_subscription_invoice_status_breakdown' => $companyInvoiceBreakdown[$cid] ?? [],
                'last_activity_at' => $lastActivity,
            ];
        });

        $paginator->setCollection($mapped);

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionDetail(int $subscriptionId): array
    {
        $sub = Subscription::withoutGlobalScopes()
            ->with(['company:id,name', 'purchasedAddons'])
            ->whereKey($subscriptionId)
            ->firstOrFail();

        $plan = Plan::query()->where('slug', (string) $sub->plan)->first();
        $wallet = Wallet::withoutGlobalScopes()
            ->whereNull('customer_id')
            ->where('company_id', (int) $sub->company_id)
            ->orderByDesc('id')
            ->first();

        $changes = SubscriptionChange::query()
            ->where('subscription_id', $sub->id)
            ->with(['fromPlan:id,slug,name', 'toPlan:id,slug,name', 'createdBy:id,name,email'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $paymentOrderIds = PaymentOrder::query()
            ->where('company_id', (int) $sub->company_id)
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('id')
            ->all();

        $payments = $paymentOrderIds === []
            ? collect()
            : Payment::withoutGlobalScopes()
                ->where('company_id', (int) $sub->company_id)
                ->whereIn('payment_order_id', $paymentOrderIds)
                ->with([
                    'invoice' => static fn ($q) => $q->withoutGlobalScopes()->withTrashed()
                        ->select('id', 'invoice_number', 'status', 'total', 'currency', 'issued_at', 'due_at', 'type', 'source_type', 'source_id'),
                    'paymentOrder:id,reference_code,status,total,currency,created_at',
                ])
                ->orderByDesc('id')
                ->limit(200)
                ->get();

        $paymentOrders = PaymentOrder::query()
            ->where('company_id', (int) $sub->company_id)
            ->with(['plan:id,slug,name', 'approvedBy:id,name,email', 'createdBy:id,name,email'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $invoices = Invoice::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', (int) $sub->company_id)
            ->where('type', 'subscription')
            ->with(['items:id,invoice_id,name,sku,quantity,unit_price,tax_amount,line_total'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $auditTimeline = $this->auditTimelineForCompanySubscription(
            (int) $sub->id,
            (int) $sub->company_id,
            $paymentOrderIds,
        );

        $risk = $this->insightsService->getChurnSignalForSubscriptionId((int) $sub->id);

        return [
            'subscription' => $this->serializeSubscription($sub),
            'company' => $sub->company ? ['id' => (int) $sub->company->id, 'name' => (string) $sub->company->name] : null,
            'plan_catalog' => $plan ? $this->serializePlan($plan) : null,
            'wallet' => $wallet ? $this->serializeWallet($wallet) : null,
            'suspension_signals' => [
                'status' => $sub->status instanceof SubscriptionStatus ? $sub->status->value : (string) $sub->status,
                'grace_ends_at' => $sub->grace_ends_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
            ],
            'at_risk' => $risk,
            'subscription_addons' => $sub->purchasedAddons->map(static fn ($a) => $a->toArray())->values()->all(),
            'subscription_changes' => $changes->map(fn (SubscriptionChange $c) => $c->toArray())->values()->all(),
            'payments' => $payments->map(fn (Payment $p) => $this->serializePayment($p))->values()->all(),
            'payment_orders' => $paymentOrders->map(fn (PaymentOrder $o) => $this->serializePaymentOrderSummary($o))->values()->all(),
            'invoices' => $invoices->map(fn (Invoice $inv) => $this->serializeInvoice($inv))->values()->all(),
            'audit_timeline' => $auditTimeline,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentOrderDetail(int $paymentOrderId): array
    {
        $order = PaymentOrder::query()
            ->whereKey($paymentOrderId)
            ->with([
                'company:id,name',
                'plan:id,slug,name,name_ar,price_monthly,price_yearly,currency',
                'bankTransferSubmissions.submittedBy:id,name,email',
                'reconciliationMatches.bankTransaction',
                'reconciliationMatches.matchedByUser:id,name,email',
                'approvedBy:id,name,email',
                'createdBy:id,name,email',
                'payments' => static fn ($q) => $q->withoutGlobalScopes()->with([
                    'invoice' => static fn ($iq) => $iq->withoutGlobalScopes()->withTrashed(),
                ]),
            ])
            ->firstOrFail();

        $disk = Storage::disk('public');
        $submissions = $order->bankTransferSubmissions->map(function ($s) use ($disk): array {
            $row = $s->toArray();
            $path = $s->receipt_path;
            $row['receipt_url'] = ($path !== null && $path !== '' && $disk->exists($path))
                ? $disk->url($path)
                : null;

            return $row;
        })->values()->all();

        $invoicesFromPayments = $order->payments
            ->map(fn (Payment $p) => $p->invoice)
            ->filter()
            ->unique('id')
            ->values();

        $linkedSub = Subscription::withoutGlobalScopes()
            ->where('company_id', (int) $order->company_id)
            ->orderByDesc('id')
            ->first();

        return [
            'payment_order' => $this->serializePaymentOrderFull($order),
            'linked_subscription_id' => $linkedSub ? (int) $linkedSub->id : null,
            'company' => $order->company ? ['id' => (int) $order->company->id, 'name' => (string) $order->company->name] : null,
            'plan' => $order->plan ? $this->serializePlan($order->plan) : null,
            'bank_transfer_submissions' => $submissions,
            'reconciliation_matches' => $order->reconciliationMatches->map(function ($m): array {
                $a = $m->toArray();
                $a['bank_transaction'] = $m->bankTransaction?->toArray();
                $a['matched_by_user'] = $m->matchedByUser ? [
                    'id' => (int) $m->matchedByUser->id,
                    'name' => (string) $m->matchedByUser->name,
                    'email' => (string) $m->matchedByUser->email,
                ] : null;

                return $a;
            })->values()->all(),
            'payments' => $order->payments->map(fn (Payment $p) => $this->serializePayment($p))->values()->all(),
            'resulting_invoices' => $invoicesFromPayments->map(fn (Invoice $inv) => $this->serializeInvoice($inv))->values()->all(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginatePlatformSubscriptionInvoices(int $perPage = 30): LengthAwarePaginator
    {
        $perPage = max(10, min(100, $perPage));

        $paginator = Invoice::withoutGlobalScopes()
            ->withTrashed()
            ->where('type', 'subscription')
            ->with(['company:id,name'])
            ->orderByDesc('id')
            ->paginate($perPage);

        $collection = $paginator->getCollection();
        $mapped = $collection->map(function (Invoice $inv): array {
            $subId = $this->resolveLinkedSubscriptionId($inv);

            return [
                'invoice' => $this->serializeInvoice($inv),
                'company' => $inv->company ? ['id' => (int) $inv->company->id, 'name' => (string) $inv->company->name] : null,
                'linked_subscription_id' => $subId,
                'linked_payment_order_id' => $inv->source_type === PaymentOrder::class ? (int) $inv->source_id : null,
            ];
        });
        $paginator->setCollection($mapped);

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceDetail(int $invoiceId): array
    {
        $inv = Invoice::withoutGlobalScopes()
            ->withTrashed()
            ->with(['company:id,name', 'items', 'createdBy:id,name,email'])
            ->whereKey($invoiceId)
            ->firstOrFail();

        $subId = $this->resolveLinkedSubscriptionId($inv);
        $paymentOrderId = $inv->source_type === PaymentOrder::class ? (int) $inv->source_id : null;

        $payments = Payment::withoutGlobalScopes()
            ->where('invoice_id', (int) $inv->id)
            ->with([
                'paymentOrder:id,reference_code,status,total,currency,company_id',
                'invoice' => static fn ($q) => $q->withoutGlobalScopes()->withTrashed(),
            ])
            ->orderByDesc('id')
            ->get();

        return [
            'invoice' => $this->serializeInvoice($inv),
            'company' => $inv->company ? ['id' => (int) $inv->company->id, 'name' => (string) $inv->company->name] : null,
            'linked_subscription_id' => $subId,
            'linked_payment_order_id' => $paymentOrderId,
            'payments' => $payments->map(fn (Payment $p) => $this->serializePayment($p))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bankTransactionDetail(int $bankTransactionId): array
    {
        $tx = BankTransaction::query()
            ->with(['reconciliationMatches' => fn ($q) => $q->with(['paymentOrder:id,company_id,reference_code,status,total,currency', 'matchedByUser:id,name,email'])])
            ->whereKey($bankTransactionId)
            ->firstOrFail();

        return [
            'bank_transaction' => $tx->toArray(),
            'reconciliation_matches' => $tx->reconciliationMatches->map(function ($m): array {
                $a = $m->toArray();
                $a['payment_order'] = $m->paymentOrder ? $m->paymentOrder->toArray() : null;

                return $a;
            })->values()->all(),
        ];
    }

    /**
     * كل فواتير نوع subscription للشركة (بما فيها المرتبطة بطلبات دفع وليس فقط source=Subscription).
     *
     * @param  list<int>  $companyIds
     * @return array<int, array<string, int>>
     */
    private function invoiceStatusBreakdownForCompanies(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $rows = Invoice::withoutGlobalScopes()
            ->withTrashed()
            ->where('type', 'subscription')
            ->whereIn('company_id', $companyIds)
            ->selectRaw('company_id, status, count(*) as c')
            ->groupBy('company_id', 'status')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $cid = (int) $row->company_id;
            $st = (string) $row->status;
            if (! isset($out[$cid])) {
                $out[$cid] = [];
            }
            $out[$cid][$st] = (int) $row->c;
        }

        return $out;
    }

    /**
     * @param  list<int>  $subscriptionIds
     * @return array<int, array<string, int>>
     */
    private function invoiceStatusBreakdownForSubscriptions(array $subscriptionIds): array
    {
        if ($subscriptionIds === []) {
            return [];
        }

        $class = Subscription::class;
        $rows = Invoice::withoutGlobalScopes()
            ->withTrashed()
            ->where('type', 'subscription')
            ->where('source_type', $class)
            ->whereIn('source_id', $subscriptionIds)
            ->selectRaw('source_id as subscription_id, status, count(*) as c')
            ->groupBy('source_id', 'status')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $sid = (int) $row->subscription_id;
            $st = (string) $row->status;
            if (! isset($out[$sid])) {
                $out[$sid] = [];
            }
            $out[$sid][$st] = (int) $row->c;
        }

        return $out;
    }

    /**
     * @param  list<int>  $paymentOrderIds
     * @return list<array<string, mixed>>
     */
    private function auditTimelineForCompanySubscription(int $subscriptionId, int $companyId, array $paymentOrderIds): array
    {
        $paymentIds = $paymentOrderIds === []
            ? []
            : Payment::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereIn('payment_order_id', $paymentOrderIds)
                ->orderByDesc('id')
                ->limit(300)
                ->pluck('id')
                ->all();

        $invoiceIds = Invoice::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('type', 'subscription')
            ->orderByDesc('id')
            ->limit(300)
            ->pluck('id')
            ->all();

        $q = AuditLog::query()->with('actor:id,name,email')->orderByDesc('id')->limit(400);

        $q->where(function (Builder $outer) use ($subscriptionId, $paymentOrderIds, $paymentIds, $invoiceIds): void {
            $outer->where(function (Builder $b) use ($subscriptionId): void {
                $b->where('entity_type', 'Subscription')->where('entity_id', $subscriptionId);
            });
            if ($paymentOrderIds !== []) {
                $outer->orWhere(function (Builder $b) use ($paymentOrderIds): void {
                    $b->where('entity_type', 'PaymentOrder')->whereIn('entity_id', $paymentOrderIds);
                });
            }
            if ($paymentIds !== []) {
                $outer->orWhere(function (Builder $b) use ($paymentIds): void {
                    $b->where('entity_type', 'Payment')->whereIn('entity_id', $paymentIds);
                });
            }
            if ($invoiceIds !== []) {
                $outer->orWhere(function (Builder $b) use ($invoiceIds): void {
                    $b->where('entity_type', 'Invoice')->whereIn('entity_id', $invoiceIds);
                });
            }
        });

        return $q->get()->map(static function (AuditLog $log): array {
            $row = $log->toArray();
            $row['actor'] = $log->actor ? [
                'id' => (int) $log->actor->id,
                'name' => (string) $log->actor->name,
                'email' => (string) $log->actor->email,
            ] : null;

            return $row;
        })->values()->all();
    }

    /**
     * @param  list<Carbon|string|null>  $dates
     */
    private function maxCarbonIso(array $dates): ?string
    {
        $best = null;
        foreach ($dates as $d) {
            if ($d === null || $d === '') {
                continue;
            }
            $c = $d instanceof Carbon ? $d : Carbon::parse((string) $d);
            if ($best === null || $c->gt($best)) {
                $best = $c;
            }
        }

        return $best?->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSubscription(Subscription $sub): array
    {
        $a = $sub->toArray();
        if ($sub->status instanceof SubscriptionStatus) {
            $a['status'] = $sub->status->value;
        }

        return $a;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(Plan $plan): array
    {
        return $plan->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWallet(Wallet $w): array
    {
        return $w->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Payment $p): array
    {
        $a = $p->toArray();
        $a['invoice'] = $p->invoice ? $this->serializeInvoice($p->invoice) : null;
        $a['payment_order'] = $p->paymentOrder ? $this->serializePaymentOrderSummary($p->paymentOrder) : null;

        return $a;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePaymentOrderSummary(PaymentOrder $o): array
    {
        $a = $o->toArray();
        if ($o->status !== null) {
            $a['status'] = $o->status->value;
        }
        if ($o->relationLoaded('plan') && $o->plan) {
            $a['plan'] = $this->serializePlan($o->plan);
        }

        return $a;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePaymentOrderFull(PaymentOrder $o): array
    {
        $a = $this->serializePaymentOrderSummary($o);
        $a['approved_by_user'] = $o->approvedBy ? [
            'id' => (int) $o->approvedBy->id,
            'name' => (string) $o->approvedBy->name,
            'email' => (string) $o->approvedBy->email,
        ] : null;
        $a['created_by_user'] = $o->createdBy ? [
            'id' => (int) $o->createdBy->id,
            'name' => (string) $o->createdBy->name,
            'email' => (string) $o->createdBy->email,
        ] : null;

        return $a;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInvoice(Invoice $inv): array
    {
        $a = $inv->toArray();
        if ($inv->status instanceof InvoiceStatus) {
            $a['status'] = $inv->status->value;
        }
        if ($inv->relationLoaded('items')) {
            $a['items'] = $inv->items->map(static fn ($i) => $i->toArray())->values()->all();
        }

        return $a;
    }

    private function resolveLinkedSubscriptionId(Invoice $inv): ?int
    {
        if ($inv->source_type === Subscription::class) {
            return (int) $inv->source_id;
        }
        if ($inv->source_type === PaymentOrder::class) {
            $po = PaymentOrder::query()->find((int) $inv->source_id);
            if ($po === null) {
                return null;
            }
            $sub = Subscription::withoutGlobalScopes()
                ->where('company_id', (int) $po->company_id)
                ->orderByDesc('id')
                ->first();

            return $sub ? (int) $sub->id : null;
        }

        return null;
    }
}
