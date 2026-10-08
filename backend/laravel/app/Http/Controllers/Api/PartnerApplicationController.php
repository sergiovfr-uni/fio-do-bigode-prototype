<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class PartnerApplicationController extends Controller {
    public function store(Request $request) {
        $data=$request->validate([
            'name'=>['required','string','max:160'], 'email'=>['required','email','max:255'],
            'phone'=>['required','string','regex:/^\+?[0-9 ()-]{10,20}$/'],
            'platform'=>['required',Rule::in(['instagram','youtube','tiktok','facebook','whatsapp','other'])],
            'profile_url'=>['required','url:http,https','max:1000'],
            'audience_label'=>['nullable','string','max:160'], 'message'=>['nullable','string','max:2000'],
            'consent'=>['required','accepted'], 'website'=>['nullable','string','max:0'],
        ]);
        unset($data['consent'],$data['website']);
        $data['email']=mb_strtolower(trim($data['email']));
        // One application per email; do not disclose whether it already exists.
        DB::table('partner_applications')->insertOrIgnore([...$data,'status'=>'new','consented_at'=>now(),'privacy_version'=>'2026-10-08','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Recebemos seu interesse. Nossa equipe entrará em contato pelos dados informados.'],201);
    }
}
