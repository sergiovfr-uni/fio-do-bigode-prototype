<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletAccount;
use Illuminate\Http\Request;

class WalletController extends Controller
{
 public function show(Request $request){
  $wallet=WalletAccount::firstOrCreate(['user_id'=>$request->user()->id],['provider'=>'mock','status'=>'active','available_balance'=>0]);
  return response()->json($wallet->load(['transactions'=>fn($q)=>$q->latest('occurred_at')->limit(50)]));
 }
}
