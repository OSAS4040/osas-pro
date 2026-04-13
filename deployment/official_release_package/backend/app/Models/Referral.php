<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Referral extends Model {
    use HasTenantScope;
    protected $fillable = ['company_id','referrer_user_id','referred_user_id','referred_customer_id','code','status','reward_amount','reward_points','reward_type','completed_at','expires_at','channel','notes'];
    protected $casts = ['completed_at'=>'datetime','expires_at'=>'datetime'];
    public function referrer()         { return $this->belongsTo(User::class,'referrer_user_id'); }
    public function referredCustomer() { return $this->belongsTo(Customer::class,'referred_customer_id'); }
}
