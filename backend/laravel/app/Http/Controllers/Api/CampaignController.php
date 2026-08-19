<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CampaignController extends Controller {
 public function home(){return DB::table('campaigns')->join('advertisers','advertisers.id','=','campaigns.advertiser_id')->where('campaigns.active',1)->where('advertisers.active',1)->where('placement','home')->where('starts_at','<=',now())->where('ends_at','>=',now())->orderByDesc('priority')->get(['campaigns.id','advertisers.name as advertiser','headline','cta','target_url','media_path','priority']);}
 public function impression(Request $r,int $campaign){return $this->event($r,$campaign,'impression');}
 public function click(Request $r,int $campaign){return $this->event($r,$campaign,'click');}
 private function event(Request $r,int $campaign,string $type){abort_unless(DB::table('campaigns')->where('id',$campaign)->exists(),404);$data=$r->validate(['session_id'=>['nullable','uuid']]);DB::table('ad_events')->insert(['campaign_id'=>$campaign,'user_id'=>$r->user()?->id,'type'=>$type,'session_id'=>$data['session_id']??null,'fingerprint_hash'=>hash('sha256',($r->ip()??'').'|'.($r->userAgent()??'')),'occurred_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);return response()->noContent();}
}