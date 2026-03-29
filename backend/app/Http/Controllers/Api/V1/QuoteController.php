<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller {
    public function index(Request $r) {
        $q = Quote::with(['customer','createdBy'])
            ->where('company_id', $r->user()->company_id)
            ->when($r->status, fn($q,$s)=>$q->where('status',$s))
            ->when($r->customer_id, fn($q,$id)=>$q->where('customer_id',$id))
            ->orderByDesc('id')
            ->paginate(20);
        return response()->json(['data'=>$q]);
    }

    public function store(Request $r) {
        $r->validate([
            'customer_id' => 'nullable|integer',
            'issue_date'  => 'required|date',
            'expiry_date' => 'nullable|date',
            'notes'       => 'nullable|string',
            'items'       => 'required|array|min:1',
            'items.*.name'       => 'required|string',
            'items.*.quantity'   => 'required|numeric|min:0',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $cid = $r->user()->company_id;
        $last = Quote::where('company_id',$cid)->max('id') ?? 0;
        $num  = sprintf('QUO-%d-%05d', $cid, $last+1);

        $subtotal = 0; $taxTotal = 0; $discount = floatval($r->discount_amount ?? 0);
        $itemsData = [];
        foreach($r->items as $item) {
            $qty  = floatval($item['quantity']);
            $up   = floatval($item['unit_price']);
            $disc = floatval($item['discount_amount'] ?? 0);
            $rate = floatval($item['tax_rate'] ?? 15);
            $sub  = round($qty * $up - $disc, 4);
            $tax  = round($sub * $rate / 100, 4);
            $subtotal += $sub; $taxTotal += $tax;
            $itemsData[] = ['name'=>$item['name'],'description'=>$item['description']??null,'product_id'=>$item['product_id']??null,'quantity'=>$qty,'unit_price'=>$up,'discount_amount'=>$disc,'tax_rate'=>$rate,'subtotal'=>$sub,'tax_amount'=>$tax,'total'=>$sub+$tax];
        }
        $total = round($subtotal + $taxTotal - $discount, 4);

        $quote = DB::transaction(function() use($r,$cid,$num,$subtotal,$taxTotal,$discount,$total,$itemsData) {
            $q = Quote::create(['uuid'=>Str::uuid(),'company_id'=>$cid,'branch_id'=>$r->user()->branch_id,'customer_id'=>$r->customer_id,'created_by_user_id'=>$r->user()->id,'quote_number'=>$num,'status'=>'draft','issue_date'=>$r->issue_date,'expiry_date'=>$r->expiry_date,'subtotal'=>$subtotal,'discount_amount'=>$discount,'tax_amount'=>$taxTotal,'total'=>$total,'currency'=>'SAR','notes'=>$r->notes,'terms'=>$r->terms]);
            foreach($itemsData as $i) { QuoteItem::create(array_merge($i,['quote_id'=>$q->id])); }
            return $q->load(['customer','items']);
        });

        return response()->json(['data'=>$quote], 201);
    }

    public function show(Quote $quote) {
        return response()->json(['data'=>$quote->load(['customer','items','createdBy'])]);
    }

    public function update(Request $r, Quote $quote) {
        $r->validate(['status'=>'sometimes|in:draft,sent,accepted,rejected,expired,converted']);
        $quote->update($r->only(['status','notes','terms','expiry_date']));
        return response()->json(['data'=>$quote->fresh(['customer','items'])]);
    }

    public function destroy(Quote $quote) {
        $quote->delete();
        return response()->json(['message'=>'تم الحذف'], 200);
    }
}
