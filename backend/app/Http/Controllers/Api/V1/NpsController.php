<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchLowNpsAlertJob;
use App\Models\NpsRating;
use Illuminate\Http\Request;

class NpsController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'score'        => 'required|integer|min:1|max:5',
            'invoice_id'   => 'nullable|integer',
            'work_order_id'=> 'nullable|integer',
            'comment'      => 'nullable|string|max:500',
            'channel'      => 'nullable|in:invoice,sms,email',
        ]);

        $rating = NpsRating::create([
            'company_id'    => $request->user()->company_id,
            'invoice_id'    => $request->invoice_id,
            'work_order_id' => $request->work_order_id,
            'customer_id'   => $request->user()->customer_id ?? null,
            'score'         => $request->score,
            'comment'       => $request->comment,
            'channel'       => $request->channel ?? 'invoice',
            'alert_sent'    => false,
        ]);

        if ($rating->score <= 2) {
            $rating->update(['alert_sent' => true]);
            DispatchLowNpsAlertJob::dispatch($rating->id);
        }

        return response()->json(['message' => 'شكراً على تقييمك', 'data' => $rating], 201);
    }

    public function index(Request $request)
    {
        $ratings = NpsRating::where('company_id', $request->user()->company_id)
            ->with(['invoice'])
            ->orderByDesc('id')
            ->paginate(20);

        $avg = NpsRating::where('company_id', $request->user()->company_id)->avg('score');
        $counts = NpsRating::where('company_id', $request->user()->company_id)
            ->selectRaw('score, count(*) as total')
            ->groupBy('score')
            ->pluck('total', 'score');

        return response()->json([
            'data'    => $ratings,
            'average' => round($avg, 1),
            'counts'  => $counts,
        ]);
    }

    public function storePublic(Request $request)
    {
        $request->validate([
            'score'      => 'required|integer|min:1|max:5',
            'invoice_id' => 'required|integer',
            'comment'    => 'nullable|string|max:500',
            'token'      => 'required|string',
        ]);

        $invoice = \App\Models\Invoice::where('uuid', $request->token)->firstOrFail();

        $existing = NpsRating::where('invoice_id', $invoice->id)->first();
        if ($existing) {
            return response()->json(['message' => 'تم تسجيل تقييمك مسبقاً'], 200);
        }

        $rating = NpsRating::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'score'      => $request->score,
            'comment'    => $request->comment,
            'channel'    => 'invoice',
            'alert_sent' => $request->score <= 2,
        ]);

        if ($rating->score <= 2) {
            DispatchLowNpsAlertJob::dispatch($rating->id);
        }

        return response()->json(['message' => 'شكراً على تقييمك!', 'data' => ['score' => $rating->score]], 201);
    }
}
