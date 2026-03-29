<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Contract::with('creator:id,name')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->party_type, fn($q) => $q->where('party_type', $request->party_type))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('title', 'ilike', "%{$request->search}%")
                  ->orWhere('party_name', 'ilike', "%{$request->search}%");
            }))
            ->orderBy('end_date')
            ->paginate(20);

        return response()->json(['data' => $q, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'party_name'        => 'required|string|max:255',
            'party_type'        => 'required|in:company,pos,service_center,individual',
            'party_email'       => 'nullable|email',
            'party_phone'       => 'nullable|string|max:20',
            'party_cr'          => 'nullable|string|max:20',
            'party_tax_number'  => 'nullable|string|max:20',
            'description'       => 'nullable|string',
            'value'             => 'nullable|numeric|min:0',
            'payment_policy'    => 'nullable|in:monthly,quarterly,annually,one_time,custom',
            'payment_day'       => 'nullable|integer|min:1|max:31',
            'payment_terms'     => 'nullable|array',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after:start_date',
            'alert_days_before' => 'integer|min:1|max:365',
        ]);

        $contract = Contract::create(array_merge($validated, [
            'uuid'       => Str::uuid(),
            'company_id' => app('tenant_company_id'),
            'created_by' => auth()->id(),
            'status'     => 'draft',
        ]));

        return response()->json(['data' => $contract, 'trace_id' => app('trace_id')], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json(['data' => $contract->load('creator:id,name', 'notifications'), 'trace_id' => app('trace_id')]);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'status'            => 'sometimes|in:draft,pending_signature,active,expired,terminated',
            'party_name'        => 'sometimes|string|max:255',
            'party_email'       => 'nullable|email',
            'party_phone'       => 'nullable|string|max:20',
            'description'       => 'nullable|string',
            'value'             => 'nullable|numeric|min:0',
            'payment_policy'    => 'sometimes|in:monthly,quarterly,annually,one_time,custom',
            'payment_terms'     => 'nullable|array',
            'start_date'        => 'sometimes|date',
            'end_date'          => 'sometimes|date',
            'alert_days_before' => 'integer|min:1|max:365',
        ]);

        $contract->update($validated);

        return response()->json(['data' => $contract, 'trace_id' => app('trace_id')]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();
        return response()->json(['message' => 'تم حذف العقد', 'trace_id' => app('trace_id')]);
    }

    public function uploadDocument(Request $request, Contract $contract): JsonResponse
    {
        $request->validate(['document' => 'required|file|mimes:pdf,doc,docx|max:10240']);

        $path = $request->file('document')->store("contracts/{$contract->id}", 'public');
        $contract->update(['document_url' => Storage::disk('public')->url($path)]);

        return response()->json(['data' => ['document_url' => $contract->document_url], 'trace_id' => app('trace_id')]);
    }

    public function sendForSignature(Request $request, Contract $contract): JsonResponse
    {
        $request->validate([
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:email,whatsapp',
        ]);

        $contract->update(['status' => 'pending_signature']);

        $channels = $request->channels;
        $notified = [];

        foreach ($channels as $channel) {
            if ($channel === 'email' && $contract->party_email) {
                $this->sendEmailNotification($contract, 'contract_signature_request');
                $notified[] = 'email';
            }
            if ($channel === 'whatsapp' && $contract->party_phone) {
                $this->sendWhatsAppNotification($contract, 'signature_request');
                $notified[] = 'whatsapp';
            }
        }

        $contract->notifications()->create([
            'type'      => 'signature_request',
            'channel'   => implode(',', $notified),
            'recipient' => $contract->party_email ?? $contract->party_phone ?? 'unknown',
            'status'    => 'sent',
            'sent_at'   => now(),
        ]);

        return response()->json(['data' => ['channels' => $notified], 'trace_id' => app('trace_id')]);
    }

    public function expiringContracts(): JsonResponse
    {
        $companyId = app('tenant_company_id');

        $contracts = Contract::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('end_date', '<=', now()->addDays(60))
            ->whereDate('end_date', '>=', now())
            ->orderBy('end_date')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), [
                'days_until_expiry' => $c->days_until_expiry,
            ]));

        return response()->json(['data' => $contracts, 'trace_id' => app('trace_id')]);
    }

    private function sendEmailNotification(Contract $contract, string $type): void
    {
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "عزيزي {$contract->party_name},\n\nتم إرسال عقد جديد للمراجعة والتوقيع: {$contract->title}\n\nتاريخ الانتهاء: {$contract->end_date->format('Y-m-d')}",
                function ($msg) use ($contract) {
                    $msg->to($contract->party_email)->subject("عقد جديد يستحق مراجعتك: {$contract->title}");
                }
            );
        } catch (\Exception $e) {
            \Log::warning("Contract email failed: " . $e->getMessage());
        }
    }

    private function sendWhatsAppNotification(Contract $contract, string $type): void
    {
        \Log::info("WhatsApp notification queued for {$contract->party_phone}: {$type}");
    }
}
