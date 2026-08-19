<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
 private function guard(Request $request): void { abort_unless(($request->user()->email ?? '') === 'admin@fiodobigode.com.br',403); }
 public function dashboard(Request $request){
  $this->guard($request);
  return response()->json([
   'users'=>User::count(),'kyc_pending'=>User::where('kyc_status','pending')->count(),
   'listings_active'=>Listing::where('status','published')->count(),'deals_open'=>Deal::whereNotIn('status',['active','cancelled','closed'])->count(),
   'deals_active'=>Deal::where('status','active')->count(),
   'wallet_volume'=>DB::table('wallet_transactions')->where('status','posted')->sum('amount')
  ]);
 }
 public function users(Request $request){$this->guard($request);return User::latest()->paginate(30);}
 public function deals(Request $request){$this->guard($request);return Deal::with(['seller:id,name,email,kyc_status','buyer:id,name,email,kyc_status','listing','installments'])->latest()->paginate(30);}
}
