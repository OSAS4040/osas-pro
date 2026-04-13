<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ApprovalWorkflowService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeetingController extends Controller
{
    public function __construct(private readonly ApprovalWorkflowService $approvalWorkflowService)
    {
    }

    private array $transitions = [
        'draft' => ['scheduled', 'cancelled'],
        'scheduled' => ['in_progress', 'closed', 'cancelled'],
        'in_progress' => ['closed', 'cancelled'],
        'closed' => [],
        'cancelled' => [],
    ];

    /**
     * قائمة الاجتماعات للمنشأة الحالية — يتطلب صلاحية إدارة الاجتماعات.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 25)));

        $query = DB::table('meetings')
            ->where('company_id', (int) $request->user()->company_id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id');

        $meetings = $query->paginate($perPage);

        return response()->json(['data' => $meetings, 'trace_id' => app('trace_id')]);
    }

    /**
     * تفاصيل اجتماع واحد ضمن نفس المنشأة.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $meeting = $this->meetingForCompany($request, $id);

        return response()->json(['data' => $meeting, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:180',
            'agenda' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'linked_entity_type' => 'nullable|string|in:governance_item,work_order,support_ticket,employee',
            'linked_entity_id' => 'nullable|integer',
        ]);

        $id = DB::table('meetings')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_id' => (int) $request->user()->company_id,
            'branch_id' => (int) $request->user()->branch_id ?: null,
            'title' => $validated['title'],
            'agenda' => $validated['agenda'] ?? null,
            'status' => 'draft',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'created_by_user_id' => (int) $request->user()->id,
            'linked_entity_type' => $validated['linked_entity_type'] ?? null,
            'linked_entity_id' => $validated['linked_entity_id'] ?? null,
            'trace_id' => (string) app('trace_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit($request, 'meeting.created', $id, [], ['status' => 'draft']);

        return response()->json([
            'data' => DB::table('meetings')->where('id', $id)->first(),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:180',
            'agenda' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'status' => 'sometimes|string|in:draft,scheduled,in_progress,cancelled',
            'linked_entity_type' => 'nullable|string|in:governance_item,work_order,support_ticket,employee',
            'linked_entity_id' => 'nullable|integer',
        ]);

        $meeting = $this->meetingForCompany($request, $id);
        if (in_array((string) $meeting->status, ['closed', 'cancelled'], true) && isset($validated['status'])) {
            return $this->transitionNotAllowed((string) $meeting->status, (string) $validated['status']);
        }

        if (isset($validated['status']) && ! in_array((string) $validated['status'], $this->transitions[(string) $meeting->status] ?? [], true)) {
            return $this->transitionNotAllowed((string) $meeting->status, (string) $validated['status']);
        }

        DB::table('meetings')->where('id', $id)->update([
            'title' => $validated['title'] ?? $meeting->title,
            'agenda' => array_key_exists('agenda', $validated) ? $validated['agenda'] : $meeting->agenda,
            'scheduled_at' => array_key_exists('scheduled_at', $validated) ? $validated['scheduled_at'] : $meeting->scheduled_at,
            'status' => $validated['status'] ?? $meeting->status,
            'linked_entity_type' => array_key_exists('linked_entity_type', $validated) ? $validated['linked_entity_type'] : $meeting->linked_entity_type,
            'linked_entity_id' => array_key_exists('linked_entity_id', $validated) ? $validated['linked_entity_id'] : $meeting->linked_entity_id,
            'started_at' => ($validated['status'] ?? null) === 'in_progress' ? now() : $meeting->started_at,
            'updated_at' => now(),
        ]);

        $this->audit($request, 'meeting.updated', $id, ['status' => $meeting->status], ['status' => $validated['status'] ?? $meeting->status]);

        return response()->json([
            'data' => DB::table('meetings')->where('id', $id)->first(),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function addParticipant(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'name' => 'nullable|string|max:120',
            'role' => 'nullable|string|max:80',
        ]);
        $meeting = $this->meetingForCompany($request, $id);
        DB::table('meeting_participants')->updateOrInsert(
            ['meeting_id' => $meeting->id, 'user_id' => $validated['user_id'] ?? null],
            [
                'company_id' => (int) $request->user()->company_id,
                'name' => $validated['name'] ?? null,
                'role' => $validated['role'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        return response()->json(['message' => 'Participant added.', 'trace_id' => app('trace_id')]);
    }

    public function removeParticipant(Request $request, int $id, int $participantId): JsonResponse
    {
        $this->meetingForCompany($request, $id);
        DB::table('meeting_participants')
            ->where('id', $participantId)
            ->where('meeting_id', $id)
            ->delete();
        return response()->json(['message' => 'Participant removed.', 'trace_id' => app('trace_id')]);
    }

    public function addMinutes(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string']);
        $meeting = $this->meetingForCompany($request, $id);
        DB::table('meeting_minutes')->insert([
            'meeting_id' => $meeting->id,
            'company_id' => (int) $request->user()->company_id,
            'content' => $validated['content'],
            'created_by_user_id' => (int) $request->user()->id,
            'recorded_at' => now(),
            'trace_id' => (string) app('trace_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($request, 'meeting.minutes_added', $meeting->id, [], ['content' => 'added']);
        return response()->json(['message' => 'Minutes added.', 'trace_id' => app('trace_id')], 201);
    }

    public function listMinutes(Request $request, int $id): JsonResponse
    {
        $this->meetingForCompany($request, $id);
        $rows = DB::table('meeting_minutes')->where('meeting_id', $id)->orderByDesc('id')->get();
        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }

    public function addDecision(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision_text' => 'required|string',
            'requires_approval' => 'sometimes|boolean',
        ]);
        $meeting = $this->meetingForCompany($request, $id);
        $decisionId = DB::table('meeting_decisions')->insertGetId([
            'meeting_id' => $meeting->id,
            'company_id' => (int) $request->user()->company_id,
            'decision_text' => $validated['decision_text'],
            'requires_approval' => (bool) ($validated['requires_approval'] ?? false),
            'approval_status' => (bool) ($validated['requires_approval'] ?? false) ? 'pending' : null,
            'created_by_user_id' => (int) $request->user()->id,
            'decided_at' => now(),
            'trace_id' => (string) app('trace_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($request, 'meeting.decision_added', $meeting->id, [], ['decision_id' => $decisionId]);
        return response()->json([
            'data' => DB::table('meeting_decisions')->where('id', $decisionId)->first(),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function startDecisionApproval(Request $request, int $id, int $decisionId): JsonResponse
    {
        $meeting = $this->meetingForCompany($request, $id);
        $decision = $this->decisionForMeeting($request, $id, $decisionId);
        if (! (bool) $decision->requires_approval) {
            return response()->json([
                'message' => 'Decision does not require approval.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $workflow = $this->approvalWorkflowService->ensurePendingWorkflow(
            (int) $request->user()->company_id,
            'meeting_decision',
            (int) $decisionId,
            (int) $request->user()->id,
            null,
            'meeting.decision.approval',
            'Decision approval requested',
            ['meeting_id' => $meeting->id],
            1
        );

        DB::table('meeting_decisions')->where('id', $decisionId)->update([
            'approval_workflow_id' => (int) $workflow->id,
            'approval_status' => (string) $workflow->status,
            'updated_at' => now(),
        ]);
        $this->audit($request, 'meeting.decision.approval_started', $meeting->id, [], ['decision_id' => $decisionId, 'workflow_id' => (int) $workflow->id]);

        return response()->json([
            'data' => [
                'decision_id' => $decisionId,
                'approval_workflow_id' => (int) $workflow->id,
                'approval_status' => (string) $workflow->status,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function decisionApprovalStatus(Request $request, int $id, int $decisionId): JsonResponse
    {
        $decision = $this->decisionForMeeting($request, $id, $decisionId);
        $status = $decision->approval_status;
        if ($decision->approval_workflow_id) {
            $status = DB::table('approval_workflows')->where('id', $decision->approval_workflow_id)->value('status') ?: $status;
        }

        return response()->json([
            'data' => [
                'decision_id' => $decisionId,
                'requires_approval' => (bool) $decision->requires_approval,
                'approval_workflow_id' => $decision->approval_workflow_id,
                'approval_status' => $status,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function approveDecision(Request $request, int $id, int $decisionId): JsonResponse
    {
        $meeting = $this->meetingForCompany($request, $id);
        $decision = $this->decisionForMeeting($request, $id, $decisionId);
        if (! $decision->approval_workflow_id) {
            return response()->json(['message' => 'Decision approval workflow is not started.', 'trace_id' => app('trace_id')], 422);
        }
        try {
            $workflow = $this->approvalWorkflowService->approve((int) $decision->approval_workflow_id, (int) $request->user()->id, (string) $request->input('note', ''));
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }
        DB::table('meeting_decisions')->where('id', $decisionId)->update(['approval_status' => (string) $workflow->status, 'updated_at' => now()]);
        $this->audit($request, 'meeting.decision.approved', $meeting->id, ['approval_status' => $decision->approval_status], ['approval_status' => $workflow->status]);
        return response()->json(['message' => 'Decision approved.', 'trace_id' => app('trace_id')]);
    }

    public function rejectDecision(Request $request, int $id, int $decisionId): JsonResponse
    {
        $meeting = $this->meetingForCompany($request, $id);
        $decision = $this->decisionForMeeting($request, $id, $decisionId);
        if (! $decision->approval_workflow_id) {
            return response()->json(['message' => 'Decision approval workflow is not started.', 'trace_id' => app('trace_id')], 422);
        }
        try {
            $workflow = $this->approvalWorkflowService->reject((int) $decision->approval_workflow_id, (int) $request->user()->id, (string) $request->input('note', ''));
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }
        DB::table('meeting_decisions')->where('id', $decisionId)->update(['approval_status' => (string) $workflow->status, 'updated_at' => now()]);
        $this->audit($request, 'meeting.decision.rejected', $meeting->id, ['approval_status' => $decision->approval_status], ['approval_status' => $workflow->status]);
        return response()->json(['message' => 'Decision rejected.', 'trace_id' => app('trace_id')]);
    }

    public function addAction(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action_text' => 'required|string',
            'owner_user_id' => 'nullable|integer',
            'owner_employee_id' => 'nullable|integer',
            'due_date' => 'nullable|date',
            'decision_id' => 'nullable|integer',
        ]);
        $meeting = $this->meetingForCompany($request, $id);
        if (! empty($validated['decision_id'])) {
            $this->decisionForMeeting($request, $id, (int) $validated['decision_id']);
        }
        DB::table('meeting_actions')->insert([
            'meeting_id' => $meeting->id,
            'decision_id' => $validated['decision_id'] ?? null,
            'company_id' => (int) $request->user()->company_id,
            'action_text' => $validated['action_text'],
            'owner_user_id' => $validated['owner_user_id'] ?? null,
            'owner_employee_id' => $validated['owner_employee_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'open',
            'follow_up_status' => 'open',
            'created_by_user_id' => (int) $request->user()->id,
            'trace_id' => (string) app('trace_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($request, 'meeting.action_added', $meeting->id, [], ['action' => 'added']);
        return response()->json(['message' => 'Action added.', 'trace_id' => app('trace_id')], 201);
    }

    public function updateAction(Request $request, int $id, int $actionId): JsonResponse
    {
        $validated = $request->validate([
            'owner_user_id' => 'nullable|integer',
            'owner_employee_id' => 'nullable|integer',
            'status' => 'nullable|string|in:open,in_progress,done,cancelled',
            'due_date' => 'nullable|date',
        ]);
        $this->meetingForCompany($request, $id);
        $action = DB::table('meeting_actions')
            ->where('id', $actionId)
            ->where('meeting_id', $id)
            ->where('company_id', (int) $request->user()->company_id)
            ->firstOrFail();

        if (isset($validated['status']) && ! $this->isValidActionTransition((string) $action->status, (string) $validated['status'])) {
            return response()->json([
                'message' => "Action status transition {$action->status} -> {$validated['status']} is not allowed.",
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        DB::table('meeting_actions')->where('id', $actionId)->update([
            'owner_user_id' => array_key_exists('owner_user_id', $validated) ? $validated['owner_user_id'] : $action->owner_user_id,
            'owner_employee_id' => array_key_exists('owner_employee_id', $validated) ? $validated['owner_employee_id'] : $action->owner_employee_id,
            'status' => $validated['status'] ?? $action->status,
            'follow_up_status' => $this->toFollowUpStatus($validated['status'] ?? $action->status),
            'due_date' => array_key_exists('due_date', $validated) ? $validated['due_date'] : $action->due_date,
            'updated_at' => now(),
        ]);
        $this->audit($request, 'meeting.action.updated', $id, ['action_id' => $actionId, 'status' => $action->status], ['status' => $validated['status'] ?? $action->status]);
        return response()->json(['message' => 'Action updated.', 'trace_id' => app('trace_id')]);
    }

    public function closeAction(Request $request, int $id, int $actionId): JsonResponse
    {
        $this->meetingForCompany($request, $id);
        $action = DB::table('meeting_actions')
            ->where('id', $actionId)
            ->where('meeting_id', $id)
            ->where('company_id', (int) $request->user()->company_id)
            ->firstOrFail();
        if (! in_array((string) $action->status, ['open', 'in_progress'], true)) {
            return response()->json([
                'message' => "Action status transition {$action->status} -> done is not allowed.",
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }
        DB::table('meeting_actions')->where('id', $actionId)->update([
            'status' => 'done',
            'follow_up_status' => 'done',
            'closed_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($request, 'meeting.action.closed', $id, ['action_id' => $actionId, 'status' => $action->status], ['status' => 'done']);
        return response()->json(['message' => 'Action closed.', 'trace_id' => app('trace_id')]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $meeting = $this->meetingForCompany($request, $id);
        if (! in_array((string) $meeting->status, ['scheduled', 'in_progress'], true)) {
            return $this->transitionNotAllowed((string) $meeting->status, 'closed');
        }
        DB::table('meetings')->where('id', $id)->update([
            'status' => 'closed',
            'closed_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit($request, 'meeting.closed', $id, ['status' => $meeting->status], ['status' => 'closed']);
        return response()->json(['message' => 'Meeting closed.', 'trace_id' => app('trace_id')]);
    }

    private function meetingForCompany(Request $request, int $id): object
    {
        return DB::table('meetings')
            ->where('id', $id)
            ->where('company_id', (int) $request->user()->company_id)
            ->firstOrFail();
    }

    private function transitionNotAllowed(string $from, string $to): JsonResponse
    {
        return response()->json([
            'message' => "Meeting status transition {$from} -> {$to} is not allowed.",
            'code' => 'TRANSITION_NOT_ALLOWED',
            'status' => 409,
            'trace_id' => app('trace_id'),
        ], 409);
    }

    private function decisionForMeeting(Request $request, int $meetingId, int $decisionId): object
    {
        return DB::table('meeting_decisions')
            ->where('id', $decisionId)
            ->where('meeting_id', $meetingId)
            ->where('company_id', (int) $request->user()->company_id)
            ->firstOrFail();
    }

    private function isValidActionTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        $map = [
            'open' => ['in_progress', 'done', 'cancelled'],
            'in_progress' => ['done', 'cancelled'],
            'done' => [],
            'cancelled' => [],
        ];
        return in_array($to, $map[$from] ?? [], true);
    }

    private function toFollowUpStatus(string $status): string
    {
        return match ($status) {
            'in_progress' => 'in_progress',
            'done' => 'done',
            'cancelled' => 'blocked',
            default => 'open',
        };
    }

    private function audit(Request $request, string $action, int $subjectId, array $before, array $after): void
    {
        DB::table('audit_logs')->insert([
            'uuid' => (string) Str::uuid(),
            'company_id' => (int) $request->user()->company_id,
            'branch_id' => (int) $request->user()->branch_id ?: null,
            'user_id' => (int) $request->user()->id,
            'action' => $action,
            'subject_type' => 'meeting',
            'subject_id' => $subjectId,
            'before' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'after' => json_encode($after, JSON_UNESCAPED_UNICODE),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 300),
            'trace_id' => (string) app('trace_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
