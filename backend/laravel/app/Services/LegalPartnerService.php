<?php
namespace App\Services;
use App\Models\LegalPartner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
class LegalPartnerService {
 const CONSENT_VERSION='legal-case-sharing-20261118';
 const PORTAL='https://app.nofiodobigode.app.br/parceiro-juridico/acesso/';
 public function email(string $to,string $subject,string $html): bool {
  try {if(!env('RESEND_API_KEY'))return false;
   Http::withToken(env('RESEND_API_KEY'))->timeout(15)->acceptJson()->post('https://api.resend.com/emails',['from'=>'Fio do Bigode <naoresponda@nofiodobigode.app.br>','to'=>[$to],'subject'=>$subject,'html'=>"<div style='font-family:Arial,sans-serif;max-width:600px;margin:auto'><h2>Fio do Bigode · Rede Jurídica</h2>{$html}</div>"])->throw();return true;
  }catch(\Throwable $e){return false;}
 }
 public function audit(Request $r,string $action,?int $partner=null,?int $referral=null,array $metadata=[]): void {
  $actor=$r->user();DB::table('legal_activity_logs')->insert(['legal_partner_id'=>$partner,'legal_referral_id'=>$referral,'actor_type'=>$actor instanceof LegalPartner?'partner':($actor?($actor->is_admin?'admin':'user'):'public'),'actor_id'=>$actor?->id,'action'=>$action,'metadata'=>$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE):null,'ip_hash'=>hash('sha256',(string)$r->ip()),'created_at'=>now(),'updated_at'=>now()]);
  if($actor instanceof \App\Models\User && $actor->is_admin)\App\Models\AdminAuditLog::create(['admin_user_id'=>$actor->id,'action'=>$action,'entity_type'=>'legal_partner','entity_id'=>(string)($partner??$metadata['invitation_id']??0),'after_data'=>$metadata,'ip_hash'=>hash('sha256',(string)$r->ip())]);
 }
 public function caseFor(Request $r,int $id){
  $row=DB::table('legal_referrals as r')->join('installment_delinquency_actions as a','a.id','=','r.delinquency_action_id')->where('r.id',$id)->where('r.legal_partner_id',$r->user()->id)->whereNull('r.revoked_at')->where('a.status','!=','cancelled')->select('r.*','a.deal_id','a.installment_id','a.actor_id','a.payload','a.created_at as requested_at')->first();
  abort_unless($row && !empty(json_decode($row->payload??'{}',true)['consented_at']) && (json_decode($row->payload??'{}',true)['consent_version']??null)===self::CONSENT_VERSION,404,'Solicitação não disponível para este escritório.');return $row;
 }
}
