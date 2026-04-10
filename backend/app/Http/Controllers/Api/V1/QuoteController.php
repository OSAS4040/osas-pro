<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Company;
use App\Services\Config\ConfigResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller {
    public function __construct(private readonly ConfigResolverService $configResolver) {}

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
        if (! $this->isEnabled($r, 'quotes.enabled', true)) {
            return response()->json(['message' => 'عروض الأسعار غير مفعّلة في إعدادات النظام.', 'trace_id' => app('trace_id')], 403);
        }

        $r->validate(
            [
                'customer_id' => 'nullable|integer',
                'issue_date'  => 'required|date',
                'expiry_date' => 'nullable|date',
                'notes'       => 'nullable|string',
                'items'       => 'required|array|min:1',
                'items.*.name'       => 'required|string',
                'items.*.quantity'   => 'required|numeric|min:0',
                'items.*.unit_price' => 'required|numeric|min:0',
            ],
            [
                'customer_id.integer'         => 'معرّف العميل غير صالح.',
                'issue_date.required'        => 'تاريخ إصدار العرض مطلوب.',
                'issue_date.date'          => 'تاريخ الإصدار غير صالح.',
                'expiry_date.date'         => 'تاريخ الانتهاء غير صالح.',
                'items.required'           => 'أضف بندًا واحدًا على الأقل باسم وسعر.',
                'items.min'                => 'أضف بندًا واحدًا على الأقل باسم وسعر.',
                'items.*.name.required'    => 'اسم البند مطلوب.',
                'items.*.quantity.required'=> 'كمية البند مطلوبة.',
                'items.*.quantity.numeric' => 'الكمية يجب أن تكون رقمًا.',
                'items.*.quantity.min'     => 'الكمية لا يمكن أن تكون سالبة.',
                'items.*.unit_price.required'=> 'سعر وحدة البند مطلوب.',
                'items.*.unit_price.numeric' => 'سعر الوحدة يجب أن يكون رقمًا.',
                'items.*.unit_price.min'     => 'سعر الوحدة لا يمكن أن يكون سالبًا.',
            ],
            [
                'customer_id' => 'العميل',
                'issue_date'  => 'تاريخ الإصدار',
                'expiry_date' => 'تاريخ الانتهاء',
                'notes'       => 'ملاحظات',
                'items'       => 'البنود',
                'items.*.name'       => 'اسم البند',
                'items.*.quantity'   => 'الكمية',
                'items.*.unit_price' => 'سعر الوحدة',
            ],
        );

        $cid = $r->user()->company_id;
        $last = Quote::where('company_id',$cid)->max('id') ?? 0;
        $num  = sprintf('QUO-%d-%05d', $cid, $last+1);

        $subtotal = 0;
        $taxTotal = 0;
        $discount = floatval($r->discount_amount ?? $r->input('discount') ?? 0);
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

    private function isEnabled(Request $request, string $key, bool $default): bool
    {
        $user = $request->user();
        $vertical = Company::query()->where('id', $user->company_id)->value('vertical_profile_code');

        return $this->configResolver->resolveBool($key, [
            'plan' => null,
            'vertical' => $vertical,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
        ], $default);
    }
}
