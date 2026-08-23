<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\Listing;
use App\Models\User;
use App\Services\DealEventService;
use App\Services\InstallmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        return Deal::query()->with(['listing','seller:id,name,kyc_status,reputation_score,risk_score','buyer:id,name,kyc_status,reputation_score,risk_score','offers','witnesses','installments'])
            ->where(fn($q)=>$q->where('seller_id',$user->id)->orWhere('buyer_id',$user->id))->latest()->paginate(20);
    }

    public function fromListing(Request $request, Listing $listing, DealEventService $events)
    {
        abort_unless($listing->status === 'published', 404);
        abort_if($listing->seller_id === $request->user()->id, 422, 'Você não pode fazer proposta no próprio anúncio.');
        abort_unless($request->user()->kyc_status === 'verified', 403, 'Conclua a validação de identidade antes de negociar.');
        $seller = User::findOrFail($listing->seller_id);
        abort_unless($seller->kyc_status === 'verified', 422, 'O vendedor precisa estar com identidade verificada.');
        $data = $this->offerData($request);
        $deal = DB::transaction(fn()=> $this->createDealWithOffer($listing->seller_id,$request->user()->id,$data,'classified',$listing->id,$request->user()->id,$listing->title,$listing->description));
        $events->record($deal,$request->user()->id,'proposal_created',$data);
        $events->notify($deal,$listing->seller_id,'proposal_received','Nova proposta recebida',$request->user()->name.' enviou uma proposta para '.$listing->title,['deal_id'=>$deal->id]);
        return response()->json($deal->load(['listing','seller:id,name','buyer:id,name','offers']), 201);
    }

    public function store(Request $request, DealEventService $events)
    {
        abort_unless($request->user()->kyc_status === 'verified', 403, 'Conclua a validação de identidade antes de negociar.');
        $data = $request->validate(['buyer_id'=>['required','integer','exists:users,id'],'title'=>['required','string','max:180'],'description'=>['required','string','max:5000'],'total_amount'=>['required','numeric','min:0.01'],'down_payment'=>['nullable','numeric','min:0'],'installments'=>['required','integer','min:1','max:120'],'monthly_interest'=>['nullable','numeric','min:0','max:20']]);
        abort_if((int)$data['buyer_id'] === $request->user()->id, 422, 'Comprador e vendedor precisam ser pessoas diferentes.');
        $buyer = User::findOrFail($data['buyer_id']);
        abort_unless($buyer->kyc_status === 'verified', 422, 'O comprador precisa estar com identidade verificada.');
        $offer = collect($data)->only(['total_amount','down_payment','installments','monthly_interest'])->all();
        $deal = DB::transaction(fn()=> $this->createDealWithOffer($request->user()->id,$buyer->id,$offer,'direct',null,$request->user()->id,$data['title'],$data['description']));
        $events->record($deal,$request->user()->id,'proposal_created',$offer);
        $events->notify($deal,$buyer->id,'proposal_received','Nova negociação',$request->user()->name.' iniciou uma negociação com você.',['deal_id'=>$deal->id]);
        return response()->json($deal->load(['seller:id,name','buyer:id,name','offers']), 201);
    }

    public function counteroffer(Request $request, Deal $deal, DealEventService $events)
    {
        $user = $request->user();
        abort_unless(in_array($user->id, [$deal->seller_id,$deal->buyer_id], true), 403);
        abort_if($deal->terms_locked_at, 422, 'Condições já consolidadas.');
        $data = $this->offerData($request);
        $offer = DB::transaction(function() use($deal,$user,$data){
            $deal->offers()->where('status','pending')->update(['status'=>'superseded']);
            $offer=$deal->offers()->create(['created_by'=>$user->id,'type'=>'counteroffer','total_amount'=>$data['total_amount'],'down_payment'=>$data['down_payment']??0,'installments'=>$data['installments'],'monthly_interest'=>$data['monthly_interest']??0,'status'=>'pending']);
            $deal->update(['status'=>'counteroffer']); return $offer;
        });
        $events->record($deal,$user->id,'counteroffer_created',$data);
        $events->notify($deal,$events->otherParty($deal,$user->id),'counteroffer_received','Contraproposta recebida',$user->name.' enviou novas condições para a negociação.',['deal_id'=>$deal->id]);
        return response()->json($offer, 201);
    }

    public function accept(Request $request, Deal $deal, InstallmentService $installments, DealEventService $events)
    {
        $user=$request->user();
        abort_unless(in_array($user->id, [$deal->seller_id,$deal->buyer_id], true), 403);
        abort_if($deal->terms_locked_at, 422, 'Condições já consolidadas.');
        $offer = $deal->offers()->where('status','pending')->latest()->firstOrFail();
        abort_if((int)$offer->created_by === (int)$user->id, 422, 'Quem enviou a proposta não pode aceitar a própria proposta.');
        DB::transaction(function() use($deal,$offer,$installments){
            $offer->update(['status'=>'accepted','accepted_at'=>now()]);
            $deal->update(['total_amount'=>$offer->total_amount,'down_payment'=>$offer->down_payment,'installments'=>$offer->installments,'monthly_interest'=>$offer->monthly_interest,'status'=>'accepted','terms_locked_at'=>now()]);
            $installments->generate($deal->fresh());
        });
        $deal->update(['status'=>'witnesses_pending']);
        $events->record($deal,$user->id,'terms_accepted',['offer_id'=>$offer->id]);
        $events->notify($deal,$events->otherParty($deal,$user->id),'terms_accepted','Condições aceitas',$user->name.' aceitou as condições. Cadastre duas testemunhas para gerar o dossiê.',['deal_id'=>$deal->id]);
        return response()->json($deal->fresh(['offers','witnesses']));
    }

    public function reject(Request $request, Deal $deal, DealEventService $events)
    {
        $user=$request->user();
        abort_unless(in_array($user->id, [$deal->seller_id,$deal->buyer_id], true), 403);
        abort_if($deal->terms_locked_at, 422, 'Condições já consolidadas.');
        $data=$request->validate(['reason'=>['nullable','string','max:500']]);
        DB::transaction(function() use($deal){$deal->offers()->where('status','pending')->update(['status'=>'rejected']);$deal->update(['status'=>'rejected']);});
        $events->record($deal,$user->id,'proposal_rejected',$data);
        $events->notify($deal,$events->otherParty($deal,$user->id),'proposal_rejected','Proposta recusada',$user->name.' recusou a proposta.',['deal_id'=>$deal->id,'reason'=>$data['reason']??null]);
        return response()->json($deal->fresh(['offers']));
    }

    private function offerData(Request $request): array
    { return $request->validate(['total_amount'=>['required','numeric','min:0.01'],'down_payment'=>['nullable','numeric','min:0'],'installments'=>['required','integer','min:1','max:120'],'monthly_interest'=>['nullable','numeric','min:0','max:20']]); }

    private function createDealWithOffer(int $sellerId,int $buyerId,array $data,string $origin,?int $listingId,int $createdBy,?string $title=null,?string $description=null): Deal
    {
        $deal = Deal::create(['seller_id'=>$sellerId,'buyer_id'=>$buyerId,'listing_id'=>$listingId,'origin'=>$origin,'title'=>$title,'description'=>$description,'status'=>'proposal_sent','total_amount'=>$data['total_amount'],'down_payment'=>$data['down_payment']??0,'installments'=>$data['installments'],'monthly_interest'=>$data['monthly_interest']??0]);
        DealOffer::create(['deal_id'=>$deal->id,'created_by'=>$createdBy,'type'=>'proposal','total_amount'=>$deal->total_amount,'down_payment'=>$deal->down_payment,'installments'=>$deal->installments,'monthly_interest'=>$deal->monthly_interest,'status'=>'pending']);
        return $deal;
    }
}
