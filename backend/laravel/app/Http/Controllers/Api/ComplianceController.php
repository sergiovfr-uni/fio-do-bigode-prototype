<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    public function acceptConsent(Request $request)
    {
        $data = $request->validate([
            'type' => ['required','in:terms,privacy,responsibility'],
            'version' => ['required','string','max:30'],
        ]);

        DB::table('consents')->updateOrInsert(
            ['user_id'=>$request->user()->id,'type'=>$data['type'],'version'=>$data['version']],
            ['accepted_at'=>now(),'ip_hash'=>hash('sha256',(string)$request->ip()),'user_agent_hash'=>hash('sha256',(string)$request->userAgent()),'updated_at'=>now(),'created_at'=>now()]
        );

        return response()->json(['accepted'=>true,'type'=>$data['type'],'version'=>$data['version']]);
    }

    public function consents(Request $request)
    {
        return DB::table('consents')->where('user_id',$request->user()->id)->orderByDesc('accepted_at')->get();
    }

    public function submitKyc(Request $request)
    {
        $data = $request->validate([
            'provider'=>['required','string','max:40'],
            'check_type'=>['required','in:cpf,document,face,liveness,full'],
            'external_id'=>['nullable','string','max:120'],
            'status'=>['required','in:pending,verified,rejected,review'],
            'risk_score'=>['nullable','integer','min:0','max:100'],
        ]);

        DB::transaction(function() use($request,$data){
            DB::table('kyc_checks')->insert([
                'user_id'=>$request->user()->id,'provider'=>$data['provider'],'check_type'=>$data['check_type'],
                'status'=>$data['status'],'external_id'=>$data['external_id']??null,
                'result'=>json_encode(['source'=>'api','normalized'=>true]),
                'verified_at'=>$data['status']==='verified'?now():null,'created_at'=>now(),'updated_at'=>now(),
            ]);
            if ($data['check_type']==='full') {
                $request->user()->update(['kyc_status'=>$data['status']==='verified'?'verified':$data['status'],'risk_score'=>$data['risk_score']??$request->user()->risk_score]);
            }
        });

        return response()->json(['kyc_status'=>$request->user()->fresh()->kyc_status,'risk_score'=>$request->user()->fresh()->risk_score]);
    }

    public function requestAccountDeletion(Request $request)
    {
        $data = $request->validate([
            'reason' => ['nullable','string','max:500'],
        ]);

        $existing = DB::table('account_deletion_requests')
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->first();

        if (!$existing) {
            DB::table('account_deletion_requests')->insert([
                'user_id'=>$request->user()->id,
                'status'=>'pending',
                'reason'=>$data['reason']??null,
                'requested_at'=>now(),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
        }

        $request->user()->tokens()->delete();

        return response()->json([
            'status'=>'pending',
            'message'=>'Solicitação de exclusão registrada. A conta será bloqueada para nova autenticação quando o fluxo definitivo de exclusão/anônimização for ativado.',
        ], 202);
    }
}
