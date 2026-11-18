<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\LegalPartner;
use App\Services\LegalPartnerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class LegalPartnerAuthController extends Controller {
 public function requestCode(Request $r,LegalPartnerService $s){
  $d=$r->validate(['email'=>['required','email','max:255']]);$p=LegalPartner::where('email',mb_strtolower(trim($d['email'])))->first();$id=(string)Str::uuid();
  if($p?->hasAccess()){
   $code=(string)random_int(100000,999999);
   DB::transaction(function()use($p,$id,$code){$p=LegalPartner::whereKey($p->id)->lockForUpdate()->firstOrFail();abort_unless($p->hasAccess(),403);abort_if(DB::table('legal_login_challenges')->where('legal_partner_id',$p->id)->where('created_at','>',now()->subMinute())->exists(),429,'Aguarde um minuto para solicitar outro código.');DB::table('legal_login_challenges')->where('legal_partner_id',$p->id)->delete();DB::table('legal_login_challenges')->insert(['id'=>$id,'legal_partner_id'=>$p->id,'code_hash'=>hash_hmac('sha256',$code,config('app.key')),'attempts'=>0,'expires_at'=>now()->addMinutes(10),'created_at'=>now(),'updated_at'=>now()]);});
   if(!$s->email($p->email,'Código de acesso · Parceiro Jurídico',"<p>Seu código de acesso é:</p><p style='font-size:30px;letter-spacing:6px'><b>{$code}</b></p><p>Válido por 10 minutos, para uso único. Não compartilhe este código.</p>")){DB::table('legal_login_challenges')->where('id',$id)->delete();abort(503,'Envio temporariamente indisponível. Tente novamente mais tarde.');}
  }
  return response()->json(['challenge_id'=>$id,'message'=>'Se o escritório estiver aprovado e com acesso vigente, enviaremos um código ao e-mail cadastrado.']);
 }
 public function verify(Request $r,LegalPartnerService $s){
  $d=$r->validate(['challenge_id'=>['required','uuid'],'code'=>['required','digits:6']]);
  $result=DB::transaction(function()use($d){$c=DB::table('legal_login_challenges')->where('id',$d['challenge_id'])->lockForUpdate()->first();if(!$c)return null;
   if(now()->greaterThanOrEqualTo($c->expires_at)||$c->attempts>=5){DB::table('legal_login_challenges')->where('id',$c->id)->delete();return null;}
   if(!hash_equals($c->code_hash,hash_hmac('sha256',$d['code'],config('app.key')))){DB::table('legal_login_challenges')->where('id',$c->id)->increment('attempts');return null;}
   $p=LegalPartner::whereKey($c->legal_partner_id)->lockForUpdate()->first();DB::table('legal_login_challenges')->where('id',$c->id)->delete();if(!$p?->hasAccess())return null;
   $p->tokens()->delete();return ['partner'=>$p->only(['id','office_name','contact_name','email','status','access_until']),'token'=>$p->createToken('legal-partner',['legal:portal'],now()->addHours(8))->plainTextToken];});
  abort_unless($result,422,'Código inválido, expirado ou acesso ainda não liberado.');$s->audit($r,'legal.login',$result['partner']['id']);return response()->json($result)->header('Cache-Control','no-store');
 }
 public function logout(Request $r){$r->user()->currentAccessToken()?->delete();return response()->noContent();}
}
