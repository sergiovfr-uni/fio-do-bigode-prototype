<?php
namespace App\Http\Controllers\Api;
use App\Models\Deal;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminController {
 public function dashboard(Request $r){$impressions=DB::table('ad_events')->where('type','impression')->count();$clicks=DB::table('ad_events')->where('type','click')->count();return response()->json(['users'=>User::count(),'kyc_pending'=>User::where('kyc_status','pending')->count(),'kyc_verified'=>User::where('kyc_status','verified')->count(),'listings_active'=>Listing::where('status','published')->count(),'deals_open'=>Deal::whereNotIn('status',['active','cancelled','closed'])->count(),'deals_active'=>Deal::where('status','active')->count(),'wallet_volume'=>DB::table('wallet_transactions')->where('status','posted')->sum('amount'),'installments_open'=>DB::table('installments')->where('status','pending')->count(),'installments_overdue'=>DB::table('installments')->where('status','pending')->where('due_date','<',today())->count(),'ad_impressions'=>$impressions,'ad_clicks'=>$clicks,'ad_ctr'=>$impressions>0?round(($clicks/$impressions)*100,2):0]);}
 public function users(Request $r){return User::latest()->paginate(30);}
 public function deals(Request $r){return Deal::with(['seller:id,name,email,kyc_status,risk_score,reputation_score','buyer:id,name,email,kyc_status,risk_score,reputation_score','listing','installments'])->latest()->paginate(30);}
 public function listings(Request $r){return Listing::with(['seller:id,name,email,kyc_status'])->latest()->paginate(30);}
 public function wallets(Request $r){return DB::table('wallet_accounts')->join('users','users.id','=','wallet_accounts.user_id')->leftJoin('wallet_transactions','wallet_transactions.wallet_account_id','=','wallet_accounts.id')->groupBy('wallet_accounts.id','users.name','users.email','wallet_accounts.balance')->selectRaw('wallet_accounts.id, users.name, users.email, wallet_accounts.balance, COUNT(wallet_transactions.id) transactions')->orderByDesc('wallet_accounts.id')->paginate(30);}
 public function installments(Request $r){return DB::table('installments')->join('deals','deals.id','=','installments.deal_id')->orderBy('due_date')->select('installments.*','deals.public_id as deal_public_id')->paginate(50);}
 public function campaigns(Request $r){return DB::table('campaigns')->join('advertisers','advertisers.id','=','campaigns.advertiser_id')->leftJoin('ad_events','ad_events.campaign_id','=','campaigns.id')->groupBy('campaigns.id','advertisers.name','campaigns.name','campaigns.headline','campaigns.active','campaigns.starts_at','campaigns.ends_at')->selectRaw("campaigns.id, advertisers.name advertiser, campaigns.name, campaigns.headline, campaigns.active, campaigns.starts_at, campaigns.ends_at, SUM(CASE WHEN ad_events.type='impression' THEN 1 ELSE 0 END) impressions, SUM(CASE WHEN ad_events.type='click' THEN 1 ELSE 0 END) clicks")->latest('campaigns.id')->paginate(30);}
}
