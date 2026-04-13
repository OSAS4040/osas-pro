<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralPolicy;
use App\Models\LoyaltyPoints;
use App\Models\LoyaltyTransaction;
use App\Models\Customer;
use App\Models\CustomerWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    private function cid(): int { return app('tenant_company_id'); }

    public function index(Request $request): JsonResponse
    {
        $q = Referral::where('company_id', $this->cid())
            ->with(['referrer:id,name,email', 'referredCustomer:id,name,phone'])
            ->orderByDesc('created_at');

        if ($r = $request->status) $q->where('status', $r);

        return response()->json($q->paginate((int)($request->per_page ?? 50)));
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => 'nullable|in:whatsapp,email,sms,link',
        ]);

        $existing = Referral::where('company_id', $this->cid())
            ->where('referrer_user_id', auth()->id())
            ->where('status', 'pending')
            ->first();

        if ($existing) return response()->json($existing);

        $ref = Referral::create([
            'company_id'        => $this->cid(),
            'referrer_user_id'  => auth()->id(),
            'code'              => strtoupper(Str::random(8)),
            'status'            => 'pending',
            'channel'           => $data['channel'] ?? 'link',
            'expires_at'        => now()->addDays(90),
        ]);

        return response()->json($ref, 201);
    }

    public function getPolicy(): JsonResponse
    {
        $policy = ReferralPolicy::firstOrCreate(
            ['company_id' => $this->cid()],
            [
                'enabled'           => true,
                'reward_type'       => 'wallet',
                'referrer_reward'   => 50,
                'referred_reward'   => 25,
                'points_per_sar'    => 1,
            ]
        );
        return response()->json($policy);
    }

    public function savePolicy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled'               => 'boolean',
            'reward_type'           => 'in:wallet,points,discount',
            'referrer_reward'       => 'numeric|min:0',
            'referred_reward'       => 'numeric|min:0',
            'referrer_points'       => 'integer|min:0',
            'referred_points'       => 'integer|min:0',
            'points_per_sar'        => 'integer|min:0',
            'min_purchase_to_earn'  => 'numeric|min:0',
            'points_expiry_days'    => 'nullable|integer|min:1',
            'terms'                 => 'nullable|string',
        ]);

        $policy = ReferralPolicy::updateOrCreate(['company_id' => $this->cid()], $data);
        return response()->json($policy);
    }

    public function customerPoints(int $customerId): JsonResponse
    {
        $lp = LoyaltyPoints::where('company_id', $this->cid())
            ->where('customer_id', $customerId)
            ->first();

        $txns = LoyaltyTransaction::where('company_id', $this->cid())
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')->limit(20)->get();

        return response()->json(['points' => $lp, 'transactions' => $txns]);
    }

    public function redeemPoints(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'points'      => 'required|integer|min:1',
        ]);

        $lp = LoyaltyPoints::where('company_id', $this->cid())
            ->where('customer_id', $data['customer_id'])
            ->first();

        if (!$lp || $lp->points < $data['points']) {
            return response()->json(['message' => 'رصيد النقاط غير كافٍ'], 422);
        }

        DB::transaction(function () use ($data, $lp) {
            $policy = ReferralPolicy::where('company_id', $this->cid())->first();
            $sarValue = ($data['points'] * ($policy->points_to_sar_rate ?? 0.1));

            $lp->decrement('points', $data['points']);
            $lp->increment('points_used', $data['points']);

            LoyaltyTransaction::create([
                'company_id'  => $this->cid(),
                'customer_id' => $data['customer_id'],
                'type'        => 'redeem',
                'points'      => -$data['points'],
                'description' => "استبدال {$data['points']} نقطة بـ {$sarValue} ريال",
                'source_type' => 'loyalty_redemption',
                'source_id'   => $lp->id,
            ]);
        });

        return response()->json(['message' => 'تم استبدال النقاط بنجاح']);
    }

    public function leaderboard(): JsonResponse
    {
        $data = LoyaltyPoints::where('company_id', $this->cid())
            ->with('customer:id,name,phone')
            ->orderByDesc('points')
            ->limit(20)->get();

        return response()->json($data);
    }
}
