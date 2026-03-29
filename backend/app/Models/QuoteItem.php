<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model {
    public $timestamps = false;
    protected $fillable = ['quote_id','product_id','name','description','quantity','unit_price','discount_amount','tax_rate','subtotal','tax_amount','total'];
    protected $casts = ['quantity'=>'float','unit_price'=>'float','subtotal'=>'float','tax_amount'=>'float','total'=>'float'];
}
