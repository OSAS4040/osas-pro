<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\KnowledgeBase;
use App\Models\KbCategory;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════════
    //  TICKETS
    // ═══════════════════════════════════════════════════════════════════════════

    public function indexTickets(Request $request): JsonResponse
    {
        $user      = $request->user();
        $companyId = $user->company_id;

        $q = SupportTicket::with(['customer:id,name,phone', 'assignedTo:id,name', 'createdBy:id,name'])
            ->where('company_id', $companyId);

        // Filters
        if ($s = $request->status)   $q->where('status', $s);
        if ($p = $request->priority) $q->where('priority', $p);
        if ($c = $request->category) $q->where('category', $c);
        if ($a = $request->assigned_to) $q->where('assigned_to', $a);
        if ($ch = $request->channel) $q->where('channel', $ch);
        if ($search = $request->search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }
        if ($request->overdue === 'true') {
            $q->where('sla_due_at', '<', now())
              ->whereNotIn('status', ['resolved','closed']);
        }
        if ($from = $request->from) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->to)   $q->whereDate('created_at', '<=', $to);

        $tickets = $q->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
                     ->orderBy('created_at', 'desc')
                     ->paginate($request->input('per_page', 20));

        // Append computed fields
        $tickets->getCollection()->transform(function ($t) {
            $t->append(['is_overdue', 'sla_remaining_minutes', 'sla_percentage']);
            return $t;
        });

        return response()->json(['data' => $tickets, 'trace_id' => app('trace_id')]);
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject'          => 'required|string|max:255',
            'description'      => 'required|string',
            'category'         => 'nullable|string',
            'priority'         => 'nullable|in:critical,high,medium,low',
            'channel'          => 'nullable|string',
            'customer_id'      => 'nullable|integer|exists:customers,id',
            'fleet_account_id' => 'nullable|integer',
            'assigned_to'      => 'nullable|integer|exists:users,id',
            'source_module'    => 'nullable|string',
            'source_id'        => 'nullable|integer',
            'tags'             => 'nullable|array',
            'attachments'      => 'nullable|array',
            'is_private'       => 'nullable|boolean',
        ]);

        $user = $request->user();

        // AI Analysis
        $ai = SupportTicket::analyzeTicket($data['subject'] . ' ' . $data['description']);
        if (empty($data['category'])) $data['category'] = $ai['category'];
        if (empty($data['priority'])) $data['priority'] = $ai['priority'];

        // Find best SLA policy
        $slaPolicy = SlaPolicy::where('company_id', $user->company_id)
                              ->where('priority', $data['priority'])
                              ->where('is_active', true)
                              ->first();

        $ticket = SupportTicket::create(array_merge($data, [
            'uuid'                    => Str::uuid(),
            'ticket_number'           => SupportTicket::generateTicketNumber(),
            'company_id'              => $user->company_id,
            'branch_id'               => $user->branch_id,
            'created_by'              => $user->id,
            'sla_policy_id'           => $slaPolicy?->id,
            'sla_due_at'              => $slaPolicy
                                         ? now()->addHours($slaPolicy->resolution_hours)
                                         : now()->addHours(24),
            'ai_sentiment_score'      => $ai['sentiment'],
            'ai_category_suggestion'  => $ai['category'],
            'ai_priority_suggestion'  => $ai['priority'],
            'suggested_kb_articles'   => $this->findRelatedKbArticles($user->company_id, $data['description']),
        ]));

        // Auto-log creation event
        $this->logEvent($ticket, $user, 'created', 'system', [
            'ai_category' => $ai['category'],
            'ai_priority' => $ai['priority'],
            'sla_due_at'  => $ticket->sla_due_at,
        ]);

        Cache::forget("support_stats_{$user->company_id}");

        return response()->json([
            'data'     => $ticket->load(['customer', 'assignedTo', 'slaPolicy']),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function showTicket(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::with([
            'replies.user:id,name,role',
            'customer:id,name,phone,email',
            'assignedTo:id,name,role',
            'createdBy:id,name',
            'slaPolicy',
            'watchers:id,name',
        ])->findOrFail($id);

        $ticket->append(['is_overdue', 'sla_remaining_minutes', 'sla_percentage']);

        // Increment KB article views if suggested
        $suggested = [];
        if ($ticket->suggested_kb_articles) {
            $suggested = KnowledgeBase::whereIn('id', $ticket->suggested_kb_articles)
                ->published()->select('id','title','summary','views')->get();
        }

        return response()->json([
            'data'              => $ticket,
            'suggested_articles'=> $suggested,
            'trace_id'          => app('trace_id'),
        ]);
    }

    public function updateTicket(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $user   = $request->user();

        $data = $request->validate([
            'subject'       => 'nullable|string|max:255',
            'description'   => 'nullable|string',
            'category'      => 'nullable|string',
            'priority'      => 'nullable|in:critical,high,medium,low',
            'assigned_to'   => 'nullable|integer|exists:users,id',
            'tags'          => 'nullable|array',
            'internal_notes'=> 'nullable|string',
            'is_private'    => 'nullable|boolean',
        ]);

        $oldPriority    = $ticket->priority;
        $oldAssignedTo  = $ticket->assigned_to;

        $ticket->update(array_filter($data, fn($v) => !is_null($v)));

        // Log assignment change
        if (isset($data['assigned_to']) && $data['assigned_to'] != $oldAssignedTo) {
            $assignee = User::find($data['assigned_to']);
            $this->logEvent($ticket, $user, 'assignment', 'system', [
                'assigned_to_name' => $assignee?->name,
            ]);
        }

        // Re-evaluate SLA if priority changed
        if (isset($data['priority']) && $data['priority'] !== $oldPriority) {
            $slaPolicy = SlaPolicy::where('company_id', $ticket->company_id)
                                  ->where('priority', $data['priority'])
                                  ->where('is_active', true)->first();
            if ($slaPolicy) {
                $ticket->update([
                    'sla_policy_id' => $slaPolicy->id,
                    'sla_due_at'    => now()->addHours($slaPolicy->resolution_hours),
                ]);
            }
            $this->logEvent($ticket, $user, 'priority_change', 'system', [
                'from' => $oldPriority, 'to' => $data['priority'],
            ]);
        }

        Cache::forget("support_stats_{$ticket->company_id}");

        return response()->json(['data' => $ticket->fresh()->load(['assignedTo','slaPolicy']), 'trace_id' => app('trace_id')]);
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);
        $user   = $request->user();

        $data = $request->validate([
            'status'  => 'required|in:open,in_progress,pending_customer,resolved,closed,escalated',
            'comment' => 'nullable|string',
        ]);

        $oldStatus   = $ticket->status;
        $newStatus   = $data['status'];
        $timestamps  = [];

        if ($newStatus === 'resolved' && !$ticket->resolved_at) {
            $timestamps['resolved_at'] = now();
        }
        if ($newStatus === 'closed' && !$ticket->closed_at) {
            $timestamps['closed_at']   = now();
        }
        if ($newStatus === 'escalated' && !$ticket->escalated_at) {
            $timestamps['escalated_at'] = now();
        }

        $ticket->update(array_merge(['status' => $newStatus], $timestamps));

        $this->logEvent($ticket, $user, 'status_change', 'system', [
            'from'    => $oldStatus,
            'to'      => $newStatus,
            'comment' => $data['comment'] ?? null,
        ]);

        // Add comment as reply if provided
        if (!empty($data['comment'])) {
            TicketReply::create([
                'uuid'        => Str::uuid(),
                'ticket_id'   => $ticket->id,
                'user_id'     => $user->id,
                'author_type' => 'staff',
                'author_name' => $user->name,
                'body'        => $data['comment'],
                'is_internal' => false,
                'event_type'  => 'status_change',
                'event_meta'  => ['from' => $oldStatus, 'to' => $newStatus],
            ]);
        }

        Cache::forget("support_stats_{$ticket->company_id}");

        return response()->json(['data' => $ticket->fresh(), 'trace_id' => app('trace_id')]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  REPLIES
    // ═══════════════════════════════════════════════════════════════════════════

    public function storeReply(Request $request, int $ticketId): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($ticketId);
        $user   = $request->user();

        $data = $request->validate([
            'body'        => 'required|string',
            'is_internal' => 'nullable|boolean',
            'attachments' => 'nullable|array',
        ]);

        // Mark first response time
        if (!$ticket->first_response_at && $user->id !== $ticket->created_by) {
            $ticket->update(['first_response_at' => now()]);
            $isBreached = $ticket->slaPolicy
                ? now()->gt($ticket->created_at->addHours($ticket->slaPolicy->first_response_hours))
                : false;
            if ($isBreached) {
                $ticket->update(['first_response_breached' => true]);
            }
        }

        // Auto move to in_progress on first staff reply
        if ($ticket->status === 'open' && !($data['is_internal'] ?? false)) {
            $ticket->update(['status' => 'in_progress']);
        }

        $reply = TicketReply::create([
            'uuid'        => Str::uuid(),
            'ticket_id'   => $ticket->id,
            'user_id'     => $user->id,
            'author_type' => 'staff',
            'author_name' => $user->name,
            'body'        => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
            'attachments' => $data['attachments'] ?? null,
            'event_type'  => 'reply',
        ]);

        return response()->json(['data' => $reply->load('user:id,name,role'), 'trace_id' => app('trace_id')], 201);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SATISFACTION RATING
    // ═══════════════════════════════════════════════════════════════════════════

    public function rateSatisfaction(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'score'   => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $ticket->update([
            'satisfaction_score'      => $data['score'],
            'satisfaction_comment'    => $data['comment'] ?? null,
            'satisfaction_rated_at'   => now(),
        ]);

        $this->logEvent($ticket, $request->user(), 'satisfaction', 'system', $data);

        return response()->json(['data' => $ticket->fresh(), 'trace_id' => app('trace_id')]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  STATISTICS & DASHBOARD
    // ═══════════════════════════════════════════════════════════════════════════

    public function stats(Request $request): JsonResponse
    {
        $user      = $request->user();
        $companyId = $user->company_id;
        $cacheKey  = "support_stats_{$companyId}";

        $data = Cache::remember($cacheKey, 120, function () use ($companyId) {
            $base = SupportTicket::where('company_id', $companyId);

            $byStatus = (clone $base)->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')->pluck('count', 'status');

            $byPriority = (clone $base)->select('priority', DB::raw('COUNT(*) as count'))
                ->groupBy('priority')->pluck('count', 'priority');

            $byCategory = (clone $base)->select('category', DB::raw('COUNT(*) as count'))
                ->groupBy('category')->orderByDesc('count')->pluck('count', 'category');

            $overdue = (clone $base)->where('sla_due_at', '<', now())
                ->whereNotIn('status', ['resolved','closed'])->count();

            $avgResolutionHours = (clone $base)
                ->whereNotNull('resolved_at')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/3600) as avg_hours'))
                ->value('avg_hours');

            $avgSatisfaction = (clone $base)
                ->whereNotNull('satisfaction_score')
                ->avg('satisfaction_score');

            $slaBreachRate = (clone $base)->where('sla_breached', true)->count()
                           / max(1, (clone $base)->count()) * 100;

            // Last 30 days trend
            $trend = (clone $base)
                ->where('created_at', '>=', now()->subDays(30))
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')->orderBy('date')->get();

            // Top agents by resolved tickets
            $topAgents = (clone $base)->where('status', 'resolved')
                ->whereNotNull('assigned_to')
                ->select('assigned_to', DB::raw('COUNT(*) as resolved_count'))
                ->groupBy('assigned_to')->orderByDesc('resolved_count')->limit(5)
                ->with(['assignedTo:id,name'])->get();

            return [
                'total'                => (clone $base)->count(),
                'open'                 => $byStatus['open'] ?? 0,
                'in_progress'          => $byStatus['in_progress'] ?? 0,
                'resolved'             => $byStatus['resolved'] ?? 0,
                'closed'               => $byStatus['closed'] ?? 0,
                'escalated'            => $byStatus['escalated'] ?? 0,
                'overdue'              => $overdue,
                'by_priority'          => $byPriority,
                'by_category'          => $byCategory,
                'avg_resolution_hours' => round($avgResolutionHours ?? 0, 1),
                'avg_satisfaction'     => round($avgSatisfaction ?? 0, 2),
                'sla_breach_rate'      => round($slaBreachRate, 1),
                'trend_30d'            => $trend,
                'top_agents'           => $topAgents,
            ];
        });

        return response()->json(['data' => $data, 'trace_id' => app('trace_id')]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SLA POLICIES
    // ═══════════════════════════════════════════════════════════════════════════

    public function indexSla(Request $request): JsonResponse
    {
        $policies = SlaPolicy::where('company_id', $request->user()->company_id)
                             ->orderBy('priority')->get();
        return response()->json(['data' => $policies, 'trace_id' => app('trace_id')]);
    }

    public function storeSla(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                      => 'required|string|max:100',
            'priority'                  => 'required|in:critical,high,medium,low',
            'first_response_hours'      => 'required|integer|min:1',
            'resolution_hours'          => 'required|integer|min:1',
            'escalation_after_hours'    => 'nullable|integer|min:1',
            'escalate_to_roles'         => 'nullable|array',
            'notify_customer_on_breach' => 'nullable|boolean',
        ]);

        $policy = SlaPolicy::create(array_merge($data, [
            'uuid'       => Str::uuid(),
            'company_id' => $request->user()->company_id,
        ]));

        return response()->json(['data' => $policy, 'trace_id' => app('trace_id')], 201);
    }

    public function updateSla(Request $request, int $id): JsonResponse
    {
        $policy = SlaPolicy::findOrFail($id);
        $policy->update($request->only([
            'name','priority','first_response_hours','resolution_hours',
            'escalation_after_hours','escalate_to_roles','notify_customer_on_breach','is_active',
        ]));
        return response()->json(['data' => $policy, 'trace_id' => app('trace_id')]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  KNOWLEDGE BASE
    // ═══════════════════════════════════════════════════════════════════════════

    public function indexKb(Request $request): JsonResponse
    {
        $q = KnowledgeBase::with('category:id,name,icon,color')
            ->where('company_id', $request->user()->company_id);

        if ($request->status)      $q->where('status', $request->status);
        if ($request->category_id) $q->where('kb_category_id', $request->category_id);
        if ($s = $request->search) {
            $q->where(fn($sub) => $sub->where('title', 'like', "%{$s}%")
                                      ->orWhere('summary', 'like', "%{$s}%"));
        }
        if ($request->featured === 'true') $q->where('is_featured', true);

        $articles = $q->orderByDesc('is_featured')
                      ->orderByDesc('views')
                      ->paginate($request->input('per_page', 15));

        return response()->json(['data' => $articles, 'trace_id' => app('trace_id')]);
    }

    public function storeKb(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'title_ar'           => 'nullable|string|max:255',
            'content'            => 'required|string',
            'content_ar'         => 'nullable|string',
            'summary'            => 'nullable|string|max:500',
            'kb_category_id'     => 'nullable|integer',
            'tags'               => 'nullable|array',
            'related_categories' => 'nullable|array',
            'status'             => 'nullable|in:draft,published,archived',
            'is_public'          => 'nullable|boolean',
            'is_featured'        => 'nullable|boolean',
        ]);

        $user    = $request->user();
        $article = KnowledgeBase::create(array_merge($data, [
            'uuid'         => Str::uuid(),
            'company_id'   => $user->company_id,
            'author_id'    => $user->id,
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]));

        return response()->json(['data' => $article->load('category'), 'trace_id' => app('trace_id')], 201);
    }

    public function updateKb(Request $request, int $id): JsonResponse
    {
        $article = KnowledgeBase::findOrFail($id);
        $data    = $request->only([
            'title','title_ar','content','content_ar','summary','kb_category_id',
            'tags','related_categories','status','is_public','is_featured',
        ]);

        if (isset($data['status']) && $data['status'] === 'published' && !$article->published_at) {
            $data['published_at'] = now();
        }

        $article->update(array_filter($data, fn($v) => !is_null($v)));

        return response()->json(['data' => $article->fresh()->load('category'), 'trace_id' => app('trace_id')]);
    }

    public function voteKb(Request $request, int $id): JsonResponse
    {
        $article = KnowledgeBase::findOrFail($id);
        $data    = $request->validate(['helpful' => 'required|boolean']);

        $data['helpful']
            ? $article->increment('helpful_yes')
            : $article->increment('helpful_no');

        $article->increment('views');

        return response()->json([
            'data'             => ['helpful_yes' => $article->helpful_yes, 'helpful_no' => $article->helpful_no],
            'helpfulness_rate' => $article->helpfulness_rate,
            'trace_id'         => app('trace_id'),
        ]);
    }

    public function searchKb(Request $request): JsonResponse
    {
        $q    = $request->input('q', '');
        $user = $request->user();

        $results = KnowledgeBase::where('company_id', $user->company_id)
            ->published()
            ->where(fn($sub) =>
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('summary', 'like', "%{$q}%")
                    ->orWhereJsonContains('tags', $q)
            )
            ->orderByDesc('views')
            ->limit(10)
            ->get(['id','title','title_ar','summary','views','helpful_yes','helpful_no','kb_category_id']);

        return response()->json(['data' => $results, 'trace_id' => app('trace_id')]);
    }

    // KB Categories
    public function indexKbCategories(Request $request): JsonResponse
    {
        $cats = KbCategory::where('company_id', $request->user()->company_id)
                          ->withCount('articles')
                          ->orderBy('sort_order')->get();
        return response()->json(['data' => $cats, 'trace_id' => app('trace_id')]);
    }

    public function storeKbCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'name_ar'    => 'nullable|string|max:100',
            'icon'       => 'nullable|string|max:50',
            'color'      => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_public'  => 'nullable|boolean',
        ]);

        $cat = KbCategory::create(array_merge($data, [
            'uuid'       => Str::uuid(),
            'company_id' => $request->user()->company_id,
        ]));

        return response()->json(['data' => $cat, 'trace_id' => app('trace_id')], 201);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SLA AUTO-BREACH CHECK (called via scheduler or on-demand)
    // ═══════════════════════════════════════════════════════════════════════════

    public function checkSlaBreaches(Request $request): JsonResponse
    {
        $breached = SupportTicket::where('sla_due_at', '<', now())
            ->whereNotIn('status', ['resolved','closed'])
            ->where('sla_breached', false)
            ->get();

        $count = 0;
        foreach ($breached as $ticket) {
            $ticket->update(['sla_breached' => true, 'status' => 'escalated', 'escalated_at' => now()]);
            $this->logEvent($ticket, null, 'sla_breach', 'system', [
                'sla_due_at' => $ticket->sla_due_at,
                'breached_at' => now(),
            ]);
            $count++;
        }

        return response()->json(['breached_count' => $count, 'trace_id' => app('trace_id')]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    private function logEvent(SupportTicket $ticket, ?object $user, string $eventType, string $authorType, array $meta = []): void
    {
        TicketReply::create([
            'uuid'        => Str::uuid(),
            'ticket_id'   => $ticket->id,
            'user_id'     => $user?->id,
            'author_type' => $authorType,
            'author_name' => $user?->name ?? 'System',
            'body'        => $this->buildEventMessage($eventType, $meta),
            'is_internal' => true,
            'event_type'  => $eventType,
            'event_meta'  => $meta,
        ]);
    }

    private function buildEventMessage(string $type, array $meta): string
    {
        return match($type) {
            'created'       => "تم إنشاء التذكرة. التصنيف الذكي: {$meta['ai_category']} | الأولوية: {$meta['ai_priority']} | موعد SLA: {$meta['sla_due_at']}",
            'status_change' => "تغيير الحالة من [{$meta['from']}] إلى [{$meta['to']}]" . (isset($meta['comment']) ? " — {$meta['comment']}" : ''),
            'assignment'    => "تم تعيين التذكرة إلى: {$meta['assigned_to_name']}",
            'priority_change'=> "تغيير الأولوية من [{$meta['from']}] إلى [{$meta['to']}]",
            'sla_breach'    => "⚠️ تنبيه: تجاوز وقت SLA المحدد. تصعيد تلقائي.",
            'satisfaction'  => "تقييم العميل: {$meta['score']}/5" . (isset($meta['comment']) ? " — {$meta['comment']}" : ''),
            default         => "حدث: {$type}",
        };
    }

    private function findRelatedKbArticles(int $companyId, string $text): array
    {
        $keywords = collect(explode(' ', $text))
                    ->filter(fn($w) => mb_strlen($w) > 3)
                    ->take(5)
                    ->values();

        if ($keywords->isEmpty()) return [];

        $query = KnowledgeBase::where('company_id', $companyId)->published();
        $keywords->each(fn($kw) => $query->orWhere('title', 'like', "%{$kw}%"));

        return $query->limit(3)->pluck('id')->toArray();
    }
}
