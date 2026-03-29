<?php
$base = 'http://saas_nginx/api/v1';
function req($m,$u,$b=[],$h=[]){$ch=curl_init($u);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($m),CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$h),CURLOPT_TIMEOUT=>15]);if($b)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($b));$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return['code'=>$c,'body'=>json_decode($r,true)??[],'raw'=>$r];}

$r=req('POST',"$base/auth/login",['email'=>'owner@demo.sa','password'=>'Password123!']);
$token=$r['body']['token']??$r['body']['data']['token']??'';
$auth=["Authorization: Bearer $token"];
echo "Logged in\n";

// Delete conflicting invoices via DB
use Illuminate\Support\Facades\DB;
chdir('/var/www'); require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap();

// Delete payments first, then invoices
$conflictingInvoices = \Illuminate\Support\Facades\DB::table('invoices')
    ->where('company_id', 1)
    ->whereIn('invoice_number', ['INV-1-000001', 'INV-1-000002', 'INV-1-000003', 'INV-1-000004', 'INV-1-000005'])
    ->pluck('id');
$pDel = \Illuminate\Support\Facades\DB::table('payments')->whereIn('invoice_id', $conflictingInvoices)->delete();
echo "Deleted $pDel payments\n";
$deleted = \Illuminate\Support\Facades\DB::table('invoices')
    ->whereIn('id', $conflictingInvoices)
    ->delete();
echo "Deleted $deleted conflicting invoices\n";

$maxCounter = \Illuminate\Support\Facades\DB::table('invoices')->where('company_id', 1)->max('invoice_counter') ?? 0;
echo "Max invoice_counter now: $maxCounter\n";
