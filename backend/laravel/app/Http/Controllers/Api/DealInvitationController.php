<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\User;
use App\Services\DealEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DealInvitationController extends Controller
{
    public function index(Request $request)
    {
        return DB::table('deal_invitations')
            ->where('created_by', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn($i) => [
                'code'=>$i->code,'title'=>$i->title,'description'=>$i->description,'status'=>$i->status,
                'invitee_name'=>$i->invitee_name,'invitee_email'=>$i->invitee_email,'invitee_phone'=>$i->invitee_phone,
                'initiator_role'=>$i->initiator_role,'total_amount'=>$i->total_amount,'down_payment'=>$i->down_payment,
                'installments'=>$i->installments,'monthly_interest'=>$i->monthly_interest,'expires_at'=>$i->expires_at,
                'invite_url'=>'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html?invite='.$i->code,
            ]);
    }

    public function show(string $code)
    {
        $invite = DB::table('deal_invitations')->where('code', strtoupper($code))->first();
        abort_unless($invite && $invite->status === 'pending' && (!$invite->expires_at || now()->lte($invite->expires_at)), 404, 'Convite inválido ou expirado.');
        $creator = User::find($invite->created_by);

        return response()->json([
            'code'=>$invite->code,'title'=>$invite->title,'description'=>$invite->description,
            'total_amount'=>$invite->total_amount,'down_payment'=>$invite->down_payment,'installments'=>$invite->installments,
            'monthly_interest'=>$invite->monthly_interest,'initiator_role'=>$invite->initiator_role,
            'created_by'=>['name'=>$creator?->name,'reputation_score'=>$creator?->reputation_score,'kyc_status'=>$creator?->kyc_status],
            'expires_at'=>$invite->expires_at,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->kyc_status === 'verified', 403, 'Conclua a validação de identidade antes de criar uma negociação.');

        // Homologação: negociações diretas ficam sem limite para permitir validação completa da jornada.

        $data = $request->validate([
            'initiator_role'=>['required','in:seller,buyer'],'invitee_name'=>['nullable','string','max:160'],
            'invitee_email'=>['nullable','email','max:255'],'invitee_phone'=>['nullable','string','max:20'],
            'title'=>['required','string','max:180'],'description'=>['required','string','max:5000'],
            'total_amount'=>['required','numeric','min:0.01'],'down_payment'=>['nullable','numeric','min:0'],
            'installments'=>['required','integer','min:1','max:120'],'monthly_interest'=>['nullable','numeric','min:0','max:20'],
        ]);

        $code = $this->newCode();
        $expires = now()->addDays(7);
        DB::table('deal_invitations')->insert([
            'code'=>$code,'created_by'=>$user->id,'initiator_role'=>$data['initiator_role'],
            'invitee_name'=>$data['invitee_name']??null,'invitee_email'=>$data['invitee_email']??null,'invitee_phone'=>$data['invitee_phone']??null,
            'title'=>$data['title'],'description'=>$data['description'],'total_amount'=>$data['total_amount'],
            'down_payment'=>$data['down_payment']??0,'installments'=>$data['installments'],'monthly_interest'=>$data['monthly_interest']??0,
            'status'=>'pending','expires_at'=>$expires,'created_at'=>now(),'updated_at'=>now(),
        ]);

        return response()->json([
            'code'=>$code,'invite_url'=>'https://sergiovfr-uni.github.io/fio-do-bigode-prototype/live.html?invite='.$code,
            'expires_at'=>$expires->toIso8601String(),'message'=>'Convite criado. Compartilhe o link ou o código com a outra parte.',
        ], 201);
    }

    public function accept(Request $request, string $code, DealEventService $events)
    {
        $user = $request->user();
        abort_unless($user->kyc_status === 'verified', 403, 'Conclua o KYC antes de entrar na negociação.');
        $inviteCreatorId = null;

        $deal = DB::transaction(function () use ($code, $user, &$inviteCreatorId) {
            $invite = DB::table('deal_invitations')->where('code', strtoupper($code))->lockForUpdate()->first();
            abort_unless($invite && $invite->status === 'pending' && (!$invite->expires_at || now()->lte($invite->expires_at)), 404, 'Convite inválido ou expirado.');
            abort_if((int)$invite->created_by === (int)$user->id, 422, 'O criador não pode aceitar o próprio convite.');
            $inviteCreatorId = (int)$invite->created_by;

            $sellerId = $invite->initiator_role === 'seller' ? $invite->created_by : $user->id;
            $buyerId = $invite->initiator_role === 'buyer' ? $invite->created_by : $user->id;
            $deal = Deal::create([
                'seller_id'=>$sellerId,'buyer_id'=>$buyerId,'listing_id'=>null,'origin'=>'direct','title'=>$invite->title,'description'=>$invite->description,
                'status'=>'proposal_sent','total_amount'=>$invite->total_amount,'down_payment'=>$invite->down_payment,
                'installments'=>$invite->installments,'monthly_interest'=>$invite->monthly_interest,
            ]);
            DealOffer::create([
                'deal_id'=>$deal->id,'created_by'=>$invite->created_by,'type'=>'proposal','total_amount'=>$invite->total_amount,
                'down_payment'=>$invite->down_payment,'installments'=>$invite->installments,'monthly_interest'=>$invite->monthly_interest,'status'=>'pending',
            ]);
            DB::table('deal_invitations')->where('id',$invite->id)->update(['status'=>'accepted','accepted_by'=>$user->id,'accepted_at'=>now(),'updated_at'=>now()]);
            return $deal;
        });

        $events->record($deal,$user->id,'invite_accepted',['code'=>strtoupper($code)]);
        $events->notify($deal,$inviteCreatorId,'invite_accepted','Convite aceito',$user->name.' entrou na negociação '.$deal->title.'.',['deal_id'=>$deal->id]);

        return response()->json($deal->load(['seller:id,name,reputation_score','buyer:id,name,reputation_score','offers']), 201);
    }

    private function newCode(): string
    {
        do { $code = strtoupper(Str::random(8)); } while (DB::table('deal_invitations')->where('code',$code)->exists());
        return $code;
    }
}
