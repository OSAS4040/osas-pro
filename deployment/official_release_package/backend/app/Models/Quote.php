<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model {
    use SoftDeletes;
    protected $fillable = [
        'uuid','company_id','branch_id','customer_id','created_by_user_id',
        'quote_number','status','issue_date','expiry_date',
        'subtotal','discount_amount','tax_amount','total','currency',
        'notes','terms','converted_invoice_id',
    ];
    protected $casts = ['issue_date'=>'date','expiry_date'=>'date','subtotal'=>'float','tax_amount'=>'float','total'=>'float'];
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(QuoteItem::class); }
    public function createdBy() { return $this->belongsTo(User::class,'created_by_user_id'); }
    public function scopeForCompany($q, $id) { return $q->where('company_id',$id); }
}
