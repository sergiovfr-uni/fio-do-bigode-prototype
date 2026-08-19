<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AdminController {
 private function guard(Request $r):void{abort_unless(($r->user()->email??'')==='admin@fiodobigode.com.br',403);}
 public function dashboard(Request $r){$this->guard($r);return response()->json(['users'=>User::count(),'kyc_pending'=>User::where('kyc_status','pending')->count(),'listings_active'=>Listing::where('status','published')->count(),'deals_open'=>Deal::whereNotIn('status',['active','cancelled','closed'])->count(),'deals_active'=>Deal::where('status','active')->count(),'wallet_volume'=>DB::table('wallet_transactions')->where('status','posted')->sum('amount'),'ad_impressions'=>DB::table('ad_events')->where('type','impression')->count(),'ad_clicks'=>DB::table('ad_events')->where('type','click')->count()]);}
 public function users(Request $r){$this->guard($r);return User::latest()->paginate(30);}
 public function deals(Request $r){$this->guard($r);return Deal::with(['seller:id,name,email,kyc_status','buyer:id,name,email,kyc_status','listing','installments'])->latest()->paginate(30);}
 public function campaigns(Request $r){$this->guard($r);return DB::table('campaigns')->join('advertisers','advertisers.id','=','campaigns.advertiser_id')->leftJoin('ad_events','ad_events.campaign_id','=','campaigns.id')->groupBy('campaigns.id','advertisers.name','campaigns.name','campaigns.headline','campaigns.active','campaigns.starts_at','campaigns.ends_at')->selectRaw("campaigns.id, advertisers.name advertiser, campaigns.name, campaigns.headline, campaigns.active, campaigns.starts_at, campaigns.ends_at, SUM(CASE WHEN ad_events.type='impression' THEN 1 ELSE 0 END) impressions, SUM(CASE WHEN ad_events.type='click' THEN 1 ELSE 0 END) clicks")->latest('campaigns.id')->paginate(30);}
}