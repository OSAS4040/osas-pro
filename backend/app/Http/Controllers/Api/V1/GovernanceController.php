<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AlertNotification;
use App\Models\AlertRule;
use App\Models\ApprovalWorkflow;
use App\Models\AuditLog;
use App\Models\PolicyRule;
use App\Services\AlertService;
use App\Services\ApprovalWorkflowService;
use App\Services\AuditLogger;
use App\Services\PolicyEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GovernanceController extends Controller
{
    // ────────────────────────────────────────────────────────
    // POLICY RULES
    // ────────────────────────────────────────────────────────

    public function listPolicies(Request $request): JsonResponse
    {
        $user = $request->user();
        $rules = PolicyRule::where('company_id', $user->company_id)
            ->orderBy('code')
            ->get();
        return response()->json(['data' => $rules]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'code'        => 'required|string|max:80',
            'entity_type' => 'nullable|string|in:global,branch,role,user',
            'entity_id'   => 'nullable|integer',
            'operator'    => 'required|string|in:lte,gte,eq,neq,in,not_in,between',
            'value'       => 'required',
            'action'      => 'required|string|in:require_approval,block,alert',
            'is_active'   => 'boolean',
        ]);

        $rule = PolicyRule::updateOrCreate(
            [
                'company_id'  => $user->company_id,
                'code'        => $data['code'],
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id'   => $data['entity_id']   ?? null,
            ],
            array_merge($data, [
                'company_id' => $user->company_id,
                'value'      => is_array($data['value']) ? $data['value'] : [$data['value']],
                'created_by' => $user->id,
            ])
        );

        Cache::forget("policy:{$user->company_id}:{$data['code']}");

        app(AuditLogger::class)->log('policy.saved', PolicyRule::class, $rule->id, [], $rule->toArray());

        return response()->json(['data' => $rule, 'message' => 'تم حفظ السياسة.'], 201);
    }

    public function deletePolicy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $rule = PolicyRule::where('company_id', $user->company_id)->findOrFail($id);
        $rule->delete();
        Cache::forget("policy:{$user->company_id}:{$rule->code}");
        return response()->json(['message' => 'تم حذف السياسة.']);
    }

    public function evaluatePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'  => 'required|string',
            'value' => 'required|numeric',
        ]);

        $user   = $request->user();
        $result = app(PolicyEngine::class)->evaluate($user->company_id, $data['code'], $data['value']);

        return response()->json([
            'passed' => $result['passed'],
            'action' => $result['action'],
            'rule'   => $result['rule'],
        ]);
    }

    // ────────────────────────────────────────────────────────
    // APPROVAL WORKFLOWS
    // ────────────────────────────────────────────────────────

    public function listWorkflows(Request $request): JsonResponse
    {
        $user   = $request->user();
        $status = $request->query('status', 'pending');

        $workflows = ApprovalWorkflow::where('company_id', $user->company_id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('requester:id,name,email')
            ->latest()
            ->paginate(20);

        return response()->json($workflows);
    }

    public function approveWorkflow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $note = $request->input('note', '');

        $workflow = app(ApprovalWorkflowService::class)->approve($id, $user->id, $note);
        app(AuditLogger::class)->log('workflow.approved', ApprovalWorkflow::class, $id, ['status' => 'pending'], ['status' => 'approved', 'note' => $note]);

        return response()->json(['data' => $workflow, 'message' => 'تمت الموافقة.']);
    }

    public function rejectWorkflow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $note = $request->input('note', '');

        $workflow = app(ApprovalWorkflowService::class)->reject($id, $user->id, $note);
        app(AuditLogger::class)->log('workflow.rejected', ApprovalWorkflow::class, $id, ['status' => 'pending'], ['status' => 'rejected', 'note' => $note]);

        return response()->json(['data' => $workflow, 'message' => 'تم الرفض.']);
    }

    // ────────────────────────────────────────────────────────
    // AUDIT LOGS
    // ────────────────────────────────────────────────────────

    public function auditLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = AuditLog::where('company_id', $user->company_id)
            ->when($request->query('action'),       fn ($q, $v) => $q->where('action', $v))
            ->when($request->query('subject_type'), fn ($q, $v) => $q->where('subject_type', 'like', "%{$v}%"))
            ->when($request->query('user_id'),      fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('from'),         fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'),           fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }

    // ────────────────────────────────────────────────────────
    // ALERTS
    // ────────────────────────────────────────────────────────

    public function listAlertRules(Request $request): JsonResponse
    {
        $user  = $request->user();
        $rules = AlertRule::where('company_id', $user->company_id)->get();
        return response()->json(['data' => $rules]);
    }

    public function storeAlertRule(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'code'       => 'required|string|max:80',
            'channel'    => 'required|string|in:in_app,email,webhook',
            'condition'  => 'required|array',
            'recipients' => 'nullable|array',
            'is_active'  => 'boolean',
        ]);

        $rule = AlertRule::updateOrCreate(
            ['company_id' => $user->company_id, 'code' => $data['code']],
            array_merge($data, ['company_id' => $user->company_id])
        );

        return response()->json(['data' => $rule, 'message' => 'تم حفظ قاعدة التنبيه.'], 201);
    }

    public function myAlerts(Request $request): JsonResponse
    {
        $user = $request->user();

        $alerts = AlertNotification::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        $unread = app(AlertService::class)->unreadCount($user->company_id, $user->id);

        return response()->json(['data' => $alerts, 'unread_count' => $unread]);
    }

    public function markAlertsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids  = $request->input('ids', []);

        $count = app(AlertService::class)->markRead($user->company_id, $user->id, $ids);

        return response()->json(['marked' => $count]);
    }
}
