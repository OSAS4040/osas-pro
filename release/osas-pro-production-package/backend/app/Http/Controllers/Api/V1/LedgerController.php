<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $entries = JournalEntry::where('company_id', $companyId)
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->from_date, fn($q) => $q->where('entry_date', '>=', $request->from_date))
            ->when($request->to_date,   fn($q) => $q->where('entry_date', '<=', $request->to_date))
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('entry_number', 'like', "%{$request->search}%")
                   ->orWhere('description', 'like', "%{$request->search}%");
            }))
            ->with(['lines.account', 'createdBy'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data'     => $entries,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $entry = JournalEntry::where('company_id', $request->user()->company_id)
            ->with(['lines.account', 'createdBy', 'reversalEntry', 'reversedEntry'])
            ->findOrFail($id);

        return response()->json(['data' => $entry, 'trace_id' => app('trace_id')]);
    }

    public function trialBalance(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $balance   = $this->ledger->getTrialBalance(
            $companyId,
            $request->from_date,
            $request->to_date,
        );

        return response()->json([
            'data'     => $balance,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $entry  = JournalEntry::where('company_id', $request->user()->company_id)->findOrFail($id);
        $reason = $request->validate(['reason' => 'required|string|max:255'])['reason'];

        $reversal = $this->ledger->reverse($entry, $reason, $request->user()->id);

        return response()->json([
            'data'     => $reversal->load('lines.account'),
            'trace_id' => app('trace_id'),
        ], 201);
    }
}
