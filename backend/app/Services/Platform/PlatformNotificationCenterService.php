<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\PlatformControlledAction;
use App\Models\PlatformDecisionLogEntry;
use App\Models\PlatformIncident;
use App\Models\RegistrationProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WorkOrderCancellationRequest;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchStatus;
use App\Modules\SubscriptionsV2\Models\BankTransferSubmission;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\InsightsService;
use App\Modules\SubscriptionsV2\Services\PlatformSubscriptionAttentionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-heavy aggregator for platform attention notifications.
 * No remediation or domain actions are executed here.
 */
final class PlatformNotificationCenterService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildNotificationsFor(User $user): array
    {
        $out = [];
        $now = Carbon::now();

        if ($this->can($user, 'platform.registration.read')) {
            $pendingRegistrations = RegistrationProfile::query()
                ->where('company_activation_status', 'pending_review')
                ->orderByDesc('submitted_at')
                ->limit(10)
                ->get(['id', 'company_name', 'submitted_at', 'created_at']);
            if ($pendingRegistrations->count() > 0) {
                $first = $pendingRegistrations->first();
                $count = $pendingRegistrations->count();
                $out[] = $this->makeNotification(
                    id: 'registration_approval_pending',
                    type: 'approval',
                    title: $count === 1 ? 'اعتماد منشأة' : sprintf('%d منشآت بانتظار الاعتماد', $count),
                    summary: $count === 1
                        ? sprintf('طلب منشأة بانتظار المراجعة: %s', (string) ($first?->company_name ?? '—'))
                        : sprintf('%d طلب منشأة بانتظار اعتماد المنصة.', $count),
                    priority: 'high',
                    status: 'new',
                    targetType: 'registration_profile',
                    targetId: (string) ($first?->id ?? ''),
                    targetRoute: '/admin/registration-profiles',
                    targetParams: ['profile_id' => (string) ($first?->id ?? '')],
                    ctaLabel: 'اعتماد',
                    groupKey: 'registration.pending_review',
                    requiresAction: true,
                    createdAt: (string) ($first?->submitted_at?->toIso8601String() ?? $first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $count],
                );
            }
        }

        if ($this->can($user, 'platform.financial_model.manage')) {
            $financialPending = Company::query()
                ->where('financial_model_status', 'pending_platform_review')
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'name', 'updated_at']);
            if ($financialPending->count() > 0) {
                $first = $financialPending->first();
                $count = $financialPending->count();
                $out[] = $this->makeNotification(
                    id: 'financial_model_pending',
                    type: 'financial',
                    title: $count === 1 ? 'اعتماد نموذج مالي' : sprintf('%d نماذج مالية بانتظار الاعتماد', $count),
                    summary: $count === 1
                        ? sprintf('شركة بانتظار قرار النموذج المالي: %s', (string) ($first?->name ?? '—'))
                        : sprintf('%d شركات بانتظار مراجعة النموذج المالي.', $count),
                    priority: 'high',
                    status: 'requires_action',
                    targetType: 'company_financial_model',
                    targetId: (string) ($first?->id ?? ''),
                    targetRoute: '/platform/companies/'.(string) ($first?->id ?? ''),
                    targetParams: ['focus' => 'financial-model'],
                    ctaLabel: 'مراجعة',
                    groupKey: 'financial_model.pending_review',
                    requiresAction: true,
                    createdAt: (string) ($first?->updated_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $count],
                    relatedCompany: $first !== null ? ['id' => $first->id, 'name' => $first->name] : null,
                );
            }
        }

        if ($this->can($user, 'platform.support.read')) {
            $supportHot = SupportTicket::query()
                ->withoutGlobalScope('tenant')
                ->whereIn('status', ['open', 'escalated', 'in_progress'])
                ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'ticket_number', 'subject', 'company_id', 'priority', 'status', 'updated_at', 'created_at']);
            if ($supportHot->count() > 0) {
                $first = $supportHot->first();
                $count = $supportHot->count();
                $priority = ((string) ($first?->priority ?? 'medium')) === 'critical' ? 'critical' : 'high';
                $out[] = $this->makeNotification(
                    id: 'support_ticket_attention',
                    type: 'support',
                    title: $count === 1 ? 'تذكرة دعم تحتاج متابعة' : sprintf('%d تذاكر دعم تحتاج متابعة', $count),
                    summary: $count === 1
                        ? sprintf('التذكرة %s: %s', (string) ($first?->ticket_number ?? '#'), (string) ($first?->subject ?? '—'))
                        : sprintf('%d تذاكر دعم مفتوحة/مصعّدة تحتاج متابعة.', $count),
                    priority: $priority,
                    status: 'requires_action',
                    targetType: 'support_ticket',
                    targetId: (string) ($first?->id ?? ''),
                    targetRoute: '/platform/support',
                    targetParams: ['ticket' => (string) ($first?->id ?? '')],
                    ctaLabel: 'فتح التذكرة',
                    groupKey: 'support.attention',
                    requiresAction: true,
                    createdAt: (string) ($first?->updated_at?->toIso8601String() ?? $first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $count],
                    relatedTicketId: $first?->id,
                );
            }
        }

        if ($this->can($user, 'platform.intelligence.incidents.read')) {
            $incidents = PlatformIncident::query()
                ->whereIn('status', ['open', 'under_review', 'escalated', 'monitoring'])
                ->whereIn('severity', ['critical', 'high'])
                ->orderByDesc('last_seen_at')
                ->limit(20)
                ->get(['incident_key', 'title', 'severity', 'status', 'last_seen_at', 'created_at']);
            if ($incidents->count() > 0) {
                $first = $incidents->first();
                $count = $incidents->count();
                $out[] = $this->makeNotification(
                    id: 'incident_attention',
                    type: 'operational',
                    title: $count === 1 ? 'حادث تشغيلي يحتاج متابعة' : sprintf('%d حوادث تحتاج متابعة', $count),
                    summary: $count === 1
                        ? sprintf('%s — الحالة: %s', (string) ($first?->title ?? '—'), (string) ($first?->status ?? 'open'))
                        : sprintf('%d حوادث تشغيلية عالية/حرجة تحتاج متابعة.', $count),
                    priority: ((string) ($first?->severity ?? 'high')) === 'critical' ? 'critical' : 'high',
                    status: 'requires_action',
                    targetType: 'platform_incident',
                    targetId: (string) ($first?->incident_key ?? ''),
                    targetRoute: '/platform/intelligence/incidents/'.(string) ($first?->incident_key ?? ''),
                    targetParams: [],
                    ctaLabel: 'عرض الحادث',
                    groupKey: 'incident.attention',
                    requiresAction: true,
                    createdAt: (string) ($first?->last_seen_at?->toIso8601String() ?? $first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $count],
                    relatedIncidentKey: $first?->incident_key,
                );
            }
        }

        if ($this->can($user, 'platform.intelligence.controlled_actions.view')) {
            $scheduledFollowUps = PlatformControlledAction::query()
                ->where('action_type', 'follow_up')
                ->whereIn('status', ['open', 'assigned', 'scheduled'])
                ->whereNotNull('scheduled_for')
                ->where('scheduled_for', '<=', $now->copy()->addDay())
                ->orderBy('scheduled_for')
                ->limit(20)
                ->get(['action_id', 'incident_key', 'action_summary', 'scheduled_for', 'created_at']);
            if ($scheduledFollowUps->count() > 0) {
                $first = $scheduledFollowUps->first();
                $count = $scheduledFollowUps->count();
                $out[] = $this->makeNotification(
                    id: 'scheduled_follow_up',
                    type: 'follow_up',
                    title: $count === 1 ? 'متابعة مجدولة' : sprintf('%d متابعات مجدولة قريبة', $count),
                    summary: $count === 1
                        ? sprintf('متابعة مرتبطة بالحادث %s.', (string) ($first?->incident_key ?? '—'))
                        : sprintf('%d متابعات مجدولة خلال 24 ساعة.', $count),
                    priority: 'medium',
                    status: 'requires_action',
                    targetType: 'controlled_action',
                    targetId: (string) ($first?->action_id ?? ''),
                    targetRoute: '/platform/intelligence/incidents/'.(string) ($first?->incident_key ?? ''),
                    targetParams: ['focus' => 'controlled-actions', 'action_id' => (string) ($first?->action_id ?? '')],
                    ctaLabel: 'مراجعة',
                    groupKey: 'follow_up.scheduled',
                    requiresAction: true,
                    createdAt: (string) ($first?->scheduled_for?->toIso8601String() ?? $first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $count],
                    relatedIncidentKey: $first?->incident_key,
                );
            }
        }

        if ($this->can($user, 'platform.intelligence.decisions.read')) {
            $decisionsNeedFollowUp = PlatformDecisionLogEntry::query()
                ->where('follow_up_required', true)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['decision_id', 'incident_key', 'decision_summary', 'created_at']);
            if ($decisionsNeedFollowUp->count() > 0) {
                $first = $decisionsNeedFollowUp->first();
                $count = $decisionsNeedFollowUp->count();
                $out[] = $this->makeNotification(
                    id: 'decision_follow_up',
                    type: 'decision',
                    title: $count === 1 ? 'قرار يحتاج مراجعة' : sprintf('%d قرارات تحتاج متابعة', $count),
                    summary: $count === 1
                        ? (string) ($first?->decision_summary ?? 'قرار يحتاج متابعة')
                        : sprintf('%d قرارات مُعلّمة بمتابعة لازمة.', $count),
                    priority: 'medium',
                    status: 'requires_action',
                    targetType: 'decision_log_entry',
                    targetId: (string) ($first?->decision_id ?? ''),
                    targetRoute: '/platform/intelligence/incidents/'.(string) ($first?->incident_key ?? ''),
                    targetParams: ['focus' => 'decisions', 'decision_id' => (string) ($first?->decision_id ?? '')],
                    ctaLabel: 'مراجعة',
                    groupKey: 'decision.follow_up_required',
                    requiresAction: true,
                    createdAt: (string) ($first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $count],
                    relatedIncidentKey: $first?->incident_key,
                );
            }
        }

        if ($this->can($user, 'platform.subscription.manage')) {
            $attention = app(PlatformSubscriptionAttentionService::class)->summary();
            $awaiting = (int) ($attention['awaiting_review'] ?? 0);
            if ($awaiting > 0) {
                $first = PaymentOrder::query()
                    ->where('status', PaymentOrderStatus::AwaitingReview)
                    ->with(['company:id,name'])
                    ->orderByDesc('id')
                    ->first(['id', 'company_id', 'reference_code', 'total', 'currency', 'created_at', 'updated_at']);
                $out[] = $this->makeNotification(
                    id: 'subscription_payment_awaiting_review',
                    type: 'operational',
                    title: $awaiting === 1 ? 'طلب دفع اشتراك بانتظار المراجعة' : sprintf('%d طلبات دفع اشتراك بانتظار المراجعة', $awaiting),
                    summary: $first !== null
                        ? sprintf('الشركة: %s — مرجع الطلب: %s — المبلغ: %s %s', (string) ($first->company?->name ?? '—'), (string) $first->reference_code, (string) $first->total, (string) $first->currency)
                        : sprintf('%d طلبات تحتاج مراجعة في طابور الاشتراكات.', $awaiting),
                    priority: 'high',
                    status: 'requires_action',
                    targetType: 'subscription_payment_order',
                    targetId: (string) ($first?->id ?? ''),
                    targetRoute: '/admin/subscriptions',
                    targetParams: ['focus_order' => (string) ($first?->id ?? '')],
                    ctaLabel: 'فتح الطابور',
                    groupKey: 'subscription.payment.awaiting_review',
                    requiresAction: true,
                    createdAt: (string) ($first?->updated_at?->toIso8601String() ?? $first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $awaiting],
                    relatedCompany: $first !== null && $first->company !== null
                        ? ['id' => (int) $first->company->id, 'name' => (string) $first->company->name]
                        : null,
                );
            }

            $approveN = (int) ($attention['matched_pending_final_approval'] ?? 0);
            if ($approveN > 0) {
                $first = PaymentOrder::query()
                    ->where('status', PaymentOrderStatus::Matched)
                    ->whereHas(
                        'reconciliationMatches',
                        static fn (Builder $m) => $m->where('status', ReconciliationMatchStatus::Confirmed),
                    )
                    ->with(['company:id,name'])
                    ->orderByDesc('id')
                    ->first(['id', 'company_id', 'reference_code', 'total', 'currency', 'created_at', 'updated_at']);
                $out[] = $this->makeNotification(
                    id: 'subscription_payment_pending_final_approval',
                    type: 'operational',
                    title: $approveN === 1 ? 'طلب اشتراك بانتظار الموافقة النهائية' : sprintf('%d طلبات بانتظار الموافقة النهائية', $approveN),
                    summary: $first !== null
                        ? sprintf('تمت مطابقة بنكية — الشركة: %s — مرجع الطلب: %s', (string) ($first->company?->name ?? '—'), (string) $first->reference_code)
                        : sprintf('%d طلبات جاهزة للاعتماد بعد المطابقة.', $approveN),
                    priority: 'high',
                    status: 'requires_action',
                    targetType: 'subscription_payment_order',
                    targetId: (string) ($first?->id ?? ''),
                    targetRoute: '/admin/subscriptions/payment-orders/'.(string) ($first?->id ?? ''),
                    targetParams: [],
                    ctaLabel: 'فتح الطلب',
                    groupKey: 'subscription.payment.pending_approval',
                    requiresAction: true,
                    createdAt: (string) ($first?->updated_at?->toIso8601String() ?? $first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $approveN],
                    relatedCompany: $first !== null && $first->company !== null
                        ? ['id' => (int) $first->company->id, 'name' => (string) $first->company->name]
                        : null,
                );
            }

            $lastReceipt = BankTransferSubmission::query()
                ->whereNotNull('receipt_path')
                ->where('receipt_path', '!=', '')
                ->where('updated_at', '>=', $now->copy()->subHours(24))
                ->whereHas('paymentOrder', static function (Builder $q): void {
                    $q->whereIn('status', [PaymentOrderStatus::AwaitingReview, PaymentOrderStatus::Matched, PaymentOrderStatus::PendingTransfer]);
                })
                ->with(['paymentOrder.company:id,name', 'paymentOrder'])
                ->orderByDesc('updated_at')
                ->first();
            if ($lastReceipt !== null && $lastReceipt->paymentOrder !== null) {
                $po = $lastReceipt->paymentOrder;
                $out[] = $this->makeNotification(
                    id: 'subscription_receipt_uploaded_'.$po->id,
                    type: 'operational',
                    title: 'تم رفع إيصال جديد لطلب اشتراك',
                    summary: sprintf(
                        'الشركة: %s — طلب دفع #%d — مرجع: %s',
                        (string) ($po->company?->name ?? '—'),
                        (int) $po->id,
                        (string) $po->reference_code,
                    ),
                    priority: 'medium',
                    status: 'requires_action',
                    targetType: 'subscription_payment_order',
                    targetId: (string) $po->id,
                    targetRoute: '/admin/subscriptions/payment-orders/'.$po->id,
                    targetParams: [],
                    ctaLabel: 'مراجعة الطلب',
                    groupKey: 'subscription.payment.receipt_uploaded',
                    requiresAction: true,
                    createdAt: (string) $lastReceipt->updated_at?->toIso8601String() ?? $now->toIso8601String(),
                    metadata: ['payment_order_id' => $po->id],
                    relatedCompany: $po->company !== null ? ['id' => (int) $po->company->id, 'name' => (string) $po->company->name] : null,
                );
            }

            $risks = app(InsightsService::class)->getRiskySubscriptions();
            $highRisk = array_values(array_filter($risks, static fn (array $r): bool => ($r['risk_level'] ?? '') === 'high'));
            if ($highRisk !== []) {
                $first = $highRisk[0];
                $count = count($highRisk);
                $out[] = $this->makeNotification(
                    id: 'subscription_high_risk_wallet',
                    type: 'operational',
                    title: $count === 1 ? 'اشتراك بحالة مخاطرة عالية' : sprintf('%d اشتراكات بحالة مخاطرة عالية', $count),
                    summary: sprintf('شركة #%s — اشتراك #%s — راجع التغطية والتجديد.', (string) ($first['company_id'] ?? ''), (string) ($first['subscription_id'] ?? '')),
                    priority: 'critical',
                    status: 'requires_action',
                    targetType: 'subscription',
                    targetId: (string) ($first['subscription_id'] ?? ''),
                    targetRoute: '/admin/subscriptions/control',
                    targetParams: [],
                    ctaLabel: 'عرض المؤشرات',
                    groupKey: 'subscription.risk.high',
                    requiresAction: true,
                    createdAt: $now->toIso8601String(),
                    metadata: ['count' => $count],
                );
            }
        }

        if ($this->can($user, 'platform.cancellations.read')) {
            $pendingCancellations = WorkOrderCancellationRequest::query()
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'company_id', 'created_at']);
            if ($pendingCancellations->count() > 0) {
                $first = $pendingCancellations->first();
                $count = $pendingCancellations->count();
                $out[] = $this->makeNotification(
                    id: 'cancellations_pending',
                    type: 'governance',
                    title: $count === 1 ? 'عنصر يحتاج اعتماد أو تدخل' : sprintf('%d عناصر تحتاج اعتماد أو تدخل', $count),
                    summary: $count === 1
                        ? 'طلب إلغاء أمر عمل بانتظار قرار.'
                        : sprintf('%d طلبات إلغاء بانتظار قرار.', $count),
                    priority: 'high',
                    status: 'requires_action',
                    targetType: 'work_order_cancellation_request',
                    targetId: (string) ($first?->id ?? ''),
                    targetRoute: '/platform/cancellations',
                    targetParams: ['request_id' => (string) ($first?->id ?? '')],
                    ctaLabel: 'اعتماد',
                    groupKey: 'cancellation.pending',
                    requiresAction: true,
                    createdAt: (string) ($first?->created_at?->toIso8601String() ?? $now->toIso8601String()),
                    metadata: ['count' => $count],
                );
            }
        }

        usort($out, static function (array $a, array $b): int {
            $ap = self::priorityWeight((string) ($a['priority'] ?? 'informational'));
            $bp = self::priorityWeight((string) ($b['priority'] ?? 'informational'));
            if ($ap !== $bp) {
                return $bp <=> $ap;
            }

            $at = strtotime((string) ($a['created_at'] ?? ''));
            $bt = strtotime((string) ($b['created_at'] ?? ''));

            return $bt <=> $at;
        });

        return $out;
    }

    public static function priorityWeight(string $priority): int
    {
        return match ($priority) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private function can(User $user, string $permission): bool
    {
        return app(PlatformPermissionService::class)->hasPermission($user, $permission);
    }

    /**
     * @param  array<string, mixed>  $targetParams
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $relatedCompany
     * @return array<string, mixed>
     */
    private function makeNotification(
        string $id,
        string $type,
        string $title,
        string $summary,
        string $priority,
        string $status,
        string $targetType,
        string $targetId,
        string $targetRoute,
        array $targetParams,
        string $ctaLabel,
        ?string $groupKey,
        bool $requiresAction,
        string $createdAt,
        array $metadata = [],
        ?array $relatedCompany = null,
        ?string $relatedIncidentKey = null,
        ?int $relatedTicketId = null,
    ): array {
        return [
            'notification_id' => $id,
            'notification_type' => $type,
            'title' => $title,
            'summary' => $summary,
            'priority' => $priority,
            'status' => $status,
            'created_at' => $createdAt,
            'is_read' => false,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_route' => $targetRoute,
            'target_params' => $targetParams,
            'cta_label' => $ctaLabel,
            'group_key' => $groupKey,
            'metadata' => $metadata,
            'requires_action' => $requiresAction,
            'related_company' => $relatedCompany,
            'related_incident_key' => $relatedIncidentKey,
            'related_ticket_id' => $relatedTicketId,
        ];
    }
}

