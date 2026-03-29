<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReferralPolicy extends Model {
    use HasTenantScope;
    protected $fillable = ['company_id','enabled','reward_type','referrer_reward','referred_reward','referrer_points','referred_points','points_per_sar','min_purchase_to_earn','points_expiry_days','terms'];
    protected $casts = ['enabled'=>'boolean','referrer_reward'=>'float','referred_reward'=>'float','min_purchase_to_earn'=>'float'];
}
