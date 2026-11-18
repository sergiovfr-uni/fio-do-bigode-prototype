<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\LegalPartnerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
class LegalPartnerPortalController extends Controller {
 public function me(Request $r){$p=$r->user();return $p->only(['id','office_name','contact_name','email','phone','city','state','service_regions','specialties','status','plan_snapshot','access_until']);}
 public function cases(Request $r){return DB::table('legal_referrals as r')->join('installment_delinquency_actions as a','a.id','=','r.delinquency_action_id')->join('deals as d','d.id','=','a.deal_id')->join('users as u','u.id','=','a.actor_id')->where('r.legal_partner_id',$r->user()->id)->whereNull('r.revoked_at')->where('a.status','!=','cancelled')->orderByDesc('r.assigned_at')->limit(200)->get(['r.id','r.status','r.assigned_at','d.public_id','d.title','u.name as requester_name']);}
 public function dossier(Request $r,int $referral,LegalPartnerService $s){$row=$s->caseFor($r,$referral);$deal=Deal::findOrFail($row->deal_id);$s->audit($r,'legal.case_viewed',$r->user()->id,$referral);
  return response()->json(['case'=>['id'=>$row->id,'status'=>$row->status,'assigned_at'=>$row->assigned_at,'requested_at'=>$row->requested_at,'message'=>json_decode($row->payload??'{}',true)['message']??null,'consented_at'=>json_decode($row->payload??'{}',true)['consented_at']??null],
   'deal'=>$deal->only(['id','public_id','title','description','status','total_amount','down_payment','monthly_interest','formalized_at','first_due_date','entry_confirmed_at','entry_receipt_document_id']),
   'parties'=>DB::table('users')->whereIn('id',[$deal->seller_id,$deal->buyer_id])->get(['id','name','email','phone','city','state'])->map(fn($u)=>[...(array)$u,'role'=>(int)$u->id===(int)$deal->seller_id?'seller':'buyer']),
   'installments'=>DB::table('installments')->where('deal_id',$deal->id)->orderBy('number')->get(['id','number','due_date','amount','status','paid_at','receipt_document_id']),
   'documents'=>DB::table('deal_documents')->where('deal_id',$deal->id)->orderBy('id')->get(['id','type','original_name','mime_type','signed','created_at']),
   'signatures'=>DB::table('deal_electronic_signatures')->where('deal_id',$deal->id)->whereNotNull('signed_at')->get(['role','signed_at','source_document_sha256','signed_document_sha256']),
   'history'=>DB::table('deal_events')->where('deal_id',$deal->id)->orderBy('id')->get(['type','created_at']),
   'notes'=>DB::table('legal_case_notes')->where('legal_referral_id',$referral)->where('legal_partner_id',$r->user()->id)->orderBy('id')->get(['id','body','created_at']),
   'attendance'=>DB::table('legal_activity_logs')->where('legal_referral_id',$referral)->where('legal_partner_id',$r->user()->id)->whereIn('action',['legal.case_status_changed','legal.note_added','legal.case_assigned'])->orderBy('id')->get(['action','metadata','created_at'])]);
 }
 public function status(Request $r,int $referral,LegalPartnerService $s){$d=$r->validate(['status'=>['required',Rule::in(['received','analysis','contact','closed'])]]);DB::transaction(function()use($r,$referral,$s,$d){DB::table('legal_referrals')->where('id',$referral)->lockForUpdate()->first();$row=$s->caseFor($r,$referral);DB::table('legal_referrals')->where('id',$referral)->update(['status'=>$d['status'],'updated_at'=>now()]);$s->audit($r,'legal.case_status_changed',$r->user()->id,$referral,['from'=>$row->status,'to'=>$d['status']]);});return response()->json(['message'=>'Atendimento atualizado. O acordo e as parcelas permanecem inalterados.']);}
 public function note(Request $r,int $referral,LegalPartnerService $s){$d=$r->validate(['body'=>['required','string','max:4000']]);DB::transaction(function()use($r,$referral,$s,$d){DB::table('legal_referrals')->where('id',$referral)->lockForUpdate()->first();$s->caseFor($r,$referral);DB::table('legal_case_notes')->insert(['legal_referral_id'=>$referral,'legal_partner_id'=>$r->user()->id,'body'=>$d['body'],'created_at'=>now(),'updated_at'=>now()]);$s->audit($r,'legal.note_added',$r->user()->id,$referral);});return response()->json(['message'=>'Anotação registrada.'],201);}
 public function document(Request $r,int $referral,int $document,LegalPartnerService $s){$row=$s->caseFor($r,$referral);$f=DB::table('deal_documents')->where('id',$document)->where('deal_id',$row->deal_id)->first();abort_unless($f,404);$s->audit($r,'legal.document_downloaded',$r->user()->id,$referral,['document_id'=>$document]);
  if(!empty($f->storage_path)&&Storage::disk('local')->exists($f->storage_path))return Storage::disk('local')->download($f->storage_path,$f->original_name);
  abort_unless(!empty($f->content_blob),410,'Documento antigo indisponível.');return response()->streamDownload(fn()=>print($f->content_blob),$f->original_name,['Content-Type'=>$f->mime_type?:'application/octet-stream','Cache-Control'=>'private, no-store']);
 }
 public function revokeSharing(Request $r,Deal $deal,int $action,LegalPartnerService $s){
  DB::transaction(function()use($r,$deal,$action,$s){$a=DB::table('installment_delinquency_actions')->where('id',$action)->lockForUpdate()->first();abort_unless($a && $a->type==='legal_support_requested' && (int)$a->deal_id===(int)$deal->id && (int)$a->actor_id===(int)$r->user()->id,404);DB::table('installment_delinquency_actions')->where('id',$action)->update(['status'=>'cancelled','updated_at'=>now()]);$row=DB::table('legal_referrals')->where('delinquency_action_id',$action)->lockForUpdate()->first();if($row){DB::table('legal_referrals')->where('id',$row->id)->update(['revoked_at'=>now(),'updated_at'=>now()]);$s->audit($r,'legal.sharing_revoked',$row->legal_partner_id,$row->id);}});return response()->json(['message'=>'Compartilhamento cancelado. O escritório não terá mais acesso ao caso pela plataforma.']);
 }
}
