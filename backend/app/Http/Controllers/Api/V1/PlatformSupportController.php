<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use App\Models\SlaPolicy;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * تذاكر الدعم عبر كل المستأجرين — لمشغّلي المنصة فقط (بدون سياق tenant).
 */
final class PlatformSupportController extends Controller
{
    /**
     * @return Builder<SupportTicket>
     */
    private static function allSupportTicketsQuery(): Builder
    {
        return SupportTicket::query()->withoutGlobalScope('tenant');
    }

    public function indexTickets(Request $request): JsonResponse
    {
        $q = self::allSupportTicketsQuery()
            ->with([
                'company:id,name',
                'customer:id,name,phone',
                'assignedTo:id,name',
                'createdBy:id,name',
            ]);

        if ($cid = $request->integer('company_id')) {
            $q->where('company_id', $cid);
        }
        if ($s = $request->string('status')->toString()) {
            $q->where('status', $s);
        }
        if ($p = $request->string('priority')->toString()) {
            $q->where('priority', $p);
        }
        if ($c = $request->string('category')->toString()) {
            $q->where('category', $c);
        }
        if ($search = $request->string('search')->toString()) {
            $q->where(function ($sub) use ($search) {
                $sub->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }
        if ($request->query('overdue') === 'true') {
            $q->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed']);
        }

        $tickets = $q->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        $tickets->getCollection()->transform(function ($t) {
            $t->append(['is_overdue', 'sla_remaining_minutes', 'sla_percentage']);

            return $t;
        });

        return response()->json(['data' => $tickets, 'trace_id' => app('trace_id')]);
    }

    public function stats(Request $request): JsonResponse
    {
        $cacheKey = 'platform:support:stats:v1';
        $data = Cache::remember($cacheKey, 120, function () {
            $base = self::allSupportTicketsQuery();

            $byStatus = (clone $base)->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')->pluck('count', 'status');

            $byPriority = (clone $base)->select('priority', DB::raw('COUNT(*) as count'))
                ->groupBy('priority')->pluck('count', 'priority');

            $overdue = (clone $base)->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['resolved', 'closed'])->count();

            $avgResolutionHours = (clone $base)
                ->whereNotNull('resolved_at')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/3600) as avg_hours'))
                ->value('avg_hours');

            $avgSatisfaction = (clone $base)
                ->whereNotNull('satisfaction_score')
                ->avg('satisfaction_score');

            $total = (clone $base)->count();

            return [
                'total' => $total,
                'open' => (int) ($byStatus['open'] ?? 0),
                'in_progress' => (int) ($byStatus['in_progress'] ?? 0),
                'pending_customer' => (int) ($byStatus['pending_customer'] ?? 0),
                'resolved' => (int) ($byStatus['resolved'] ?? 0),
                'closed' => (int) ($byStatus['closed'] ?? 0),
                'escalated' => (int) ($byStatus['escalated'] ?? 0),
                'overdue' => $overdue,
                'avg_resolution_hours' => $avgResolutionHours !== null ? round((float) $avgResolutionHours, 1) : 0,
                'avg_satisfaction' => $avgSatisfaction !== null ? round((float) $avgSatisfaction, 1) : 0,
                'by_priority' => $byPriority,
            ];
        });

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    public function showTicket(Request $request, int $id): JsonResponse
    {
        $ticket = self::allSupportTicketsQuery()
            ->with([
                'replies.user:id,name,role',
                'customer:id,name,phone,email',
                'assignedTo:id,name,role',
                'createdBy:id,name',
                'company:id,name',
                'slaPolicy',
                'watchers:id,name',
            ])
            ->findOrFail($id);

        $ticket->append(['is_overdue', 'sla_remaining_minutes', 'sla_percentage']);

        $suggested = [];
        if ($ticket->suggested_kb_articles) {
            $suggested = KnowledgeBase::withoutGlobalScope('tenant')
                ->where('company_id', $ticket->company_id)
                ->whereIn('id', $ticket->suggested_kb_articles)
                ->published()
                ->select('id', 'title', 'summary', 'views')
                ->get();
        }

        return response()->json([
            'data' => $ticket,
            'suggested_articles' => $suggested,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function updateTicket(Request $request, int $id): JsonResponse
    {
        $ticket = self::allSupportTicketsQuery()->findOrFail($id);
        $user = $request->user();

        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'priority' => 'nullable|in:critical,high,medium,low',
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $ticket->company_id),
            ],
            'tags' => 'nullable|array',
            'internal_notes' => 'nullable|string',
            'is_private' => 'nullable|boolean',
        ]);

        $oldPriority = $ticket->priority;
        $oldAssignedTo = $ticket->assigned_to;

        $ticket->update(array_filter($data, fn ($v) => ! is_null($v)));

        if (isset($data['assigned_to']) && (int) $data['assigned_to'] !== (int) $oldAssignedTo) {
            $assignee = User::withoutGlobalScopes()->where('id', $data['assigned_to'])->first();
            $this->logEvent($ticket, $user, 'assignment', 'system', [
                'assigned_to_name' => $assignee?->name,
            ]);
        }

        if (isset($data['priority']) && $data['priority'] !== $oldPriority) {
            $slaPolicy = SlaPolicy::withoutGlobalScope('tenant')
                ->where('company_id', $ticket->company_id)
                ->where('priority', $data['priority'])
                ->where('is_active', true)
                ->first();
            if ($slaPolicy) {
                $ticket->update([
                    'sla_policy_id' => $slaPolicy->id,
                    'sla_due_at' => now()->addHours($slaPolicy->resolution_hours),
                ]);
            }
            $this->logEvent($ticket, $user, 'priority_change', 'system', [
                'from' => $oldPriority, 'to' => $data['priority'],
            ]);
        }

        Cache::forget("support_stats_{$ticket->company_id}");
        Cache::forget('platform:support:stats:v1');

        return response()->json(['data' => $ticket->fresh()->load(['assignedTo', 'slaPolicy', 'company:id,name']), 'trace_id' => app('trace_id')]);
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $ticket = self::allSupportTicketsQuery()->findOrFail($id);
        $user = $request->user();

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,pending_customer,resolved,closed,escalated',
            'comment' => 'nullable|string',
        ]);

        $oldStatus = $ticket->status;
        $newStatus = $data['status'];
        $allowedTransitions = [
            'open' => ['in_progress', 'pending_customer', 'resolved', 'escalated', 'closed'],
            'in_progress' => ['pending_customer', 'resolved', 'escalated', 'closed'],
            'pending_customer' => ['in_progress', 'resolved', 'escalated', 'closed'],
            'resolved' => ['closed'],
            'escalated' => ['in_progress', 'pending_customer', 'resolved', 'closed'],
            'closed' => [],
        ];
        $allowed = $allowedTransitions[$oldStatus] ?? [];
        if ($newStatus !== $oldStatus && ! in_array($newStatus, $allowed, true)) {
            return response()->json([
                'message' => "Ticket status transition {$oldStatus} -> {$newStatus} is not allowed.",
                'code' => 'TRANSITION_NOT_ALLOWED',
                'trace_id' => app('trace_id'),
            ], 409);
        }

        $timestamps = [];
        if ($newStatus === 'resolved' && ! $ticket->resolved_at) {
            $timestamps['resolved_at'] = now();
        }
        if ($newStatus === 'closed' && ! $ticket->closed_at) {
            $timestamps['closed_at'] = now();
        }
        if ($newStatus === 'escalated' && ! $ticket->escalated_at) {
            $timestamps['escalated_at'] = now();
        }

        $ticket->update(array_merge(['status' => $newStatus], $timestamps));

        $this->logEvent($ticket, $user, 'status_change', 'system', [
            'from' => $oldStatus,
            'to' => $newStatus,
            'comment' => $data['comment'] ?? null,
        ]);

        if (! empty($data['comment'])) {
            TicketReply::create([
                'uuid' => Str::uuid(),
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'author_type' => 'staff',
                'author_name' => 'منصة: '.$user->name,
                'body' => $data['comment'],
                'is_internal' => false,
                'event_type' => 'status_change',
                'event_meta' => ['from' => $oldStatus, 'to' => $newStatus],
            ]);
        }

        Cache::forget("support_stats_{$ticket->company_id}");
        Cache::forget('platform:support:stats:v1');

        return response()->json(['data' => $ticket->fresh(), 'trace_id' => app('trace_id')]);
    }

    public function storeReply(Request $request, int $ticketId): JsonResponse
    {
        $ticket = self::allSupportTicketsQuery()->findOrFail($ticketId);
        $user = $request->user();

        $data = $request->validate([
            'body' => 'required|string',
            'is_internal' => 'nullable|boolean',
            'attachments' => 'nullable|array',
        ]);

        if (! $ticket->first_response_at && $user->id !== $ticket->created_by) {
            $ticket->update(['first_response_at' => now()]);
            $isBreached = $ticket->slaPolicy
                ? now()->gt($ticket->created_at->addHours($ticket->slaPolicy->first_response_hours))
                : false;
            if ($isBreached) {
                $ticket->update(['first_response_breached' => true]);
            }
        }

        if ($ticket->status === 'open' && ! ($data['is_internal'] ?? false)) {
            $ticket->update(['status' => 'in_progress']);
        }

        $reply = TicketReply::create([
            'uuid' => Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'author_type' => 'staff',
            'author_name' => 'منصة: '.$user->name,
            'body' => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
            'attachments' => $data['attachments'] ?? null,
            'event_type' => 'reply',
        ]);

        Cache::forget("support_stats_{$ticket->company_id}");
        Cache::forget('platform:support:stats:v1');

        return response()->json(['data' => $reply->load('user:id,name,role'), 'trace_id' => app('trace_id')], 201);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logEvent(SupportTicket $ticket, ?User $user, string $eventType, string $authorType, array $meta = []): void
    {
        TicketReply::create([
            'uuid' => Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id' => $user?->id,
            'author_type' => $authorType,
            'author_name' => $user !== null ? 'منصة: '.$user->name : 'System',
            'body' => $this->buildEventMessage($eventType, $meta),
            'is_internal' => true,
            'event_type' => $eventType,
            'event_meta' => $meta,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function buildEventMessage(string $type, array $meta): string
    {
        return match ($type) {
            'status_change' => 'تغيير الحالة من ['.($meta['from'] ?? '').'] إلى ['.($meta['to'] ?? '').']'
                .(isset($meta['comment']) ? ' — '.$meta['comment'] : ''),
            'assignment' => 'تم تعيين التذكرة إلى: '.($meta['assigned_to_name'] ?? ''),
            'priority_change' => 'تغيير الأولوية من ['.($meta['from'] ?? '').'] إلى ['.($meta['to'] ?? '').']',
            default => 'حدث: '.$type,
        };
    }
}
