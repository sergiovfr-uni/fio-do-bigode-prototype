<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Installment;
use App\Models\WalletAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentController extends Controller
{
 public function index(Request $request, Deal $deal){
  abort_unless(in_array($request->user()->id,[$deal->seller_id,$deal->buyer_id],true),403);
  return response()->json($deal->installments()->get());
 }
 public function markPaid(Request $request, Deal $deal, Installment $installment){
  abort_unless($installment->deal_id===$deal->id,404);
  abort_unless(in_array($request->user()->id,[$deal->seller_id,$deal->buyer_id],true),403);
  $data=$request->validate(['external_payment_id'=>['nullable','string','max:120'],'description'=>['nullable','string','max:255']]);
  DB::transaction(function()use($deal,$installment,$data){
   if($installment->status==='paid') return;
   $installment->update(['status'=>'paid','paid_at'=>now(),'external_payment_id'=>$data['external_payment_id']??null]);
   $sellerWallet=WalletAccount::firstOrCreate(['user_id'=>$deal->seller_id],['provider'=>'mock','status'=>'active','available_balance'=>0]);
   $sellerWallet->transactions()->create(['deal_id'=>$deal->id,'installment_id'=>$installment->id,'type'=>'installment','direction'=>'credit','amount'=>$installment->amount,'status'=>'posted','external_id'=>$data['external_payment_id']??null,'description'=>$data['description']??('Parcela '.$installment->number),'occurred_at'=>now()]);
   $sellerWallet->increment('available_balance',(float)$installment->amount);
  });
  return response()->json($installment->fresh());
 }
}
