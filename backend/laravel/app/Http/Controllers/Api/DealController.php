<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealOffer;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        return Deal::query()
            ->with(['listing','seller:id,name,kyc_status,reputation_score','buyer:id,name,kyc_status,reputation_score','offers'])
            ->where(fn($q)=>$q->where('seller_id',$user->id)->orWhere('buyer_id',$user->id))
            ->latest()
            ->paginate(20);
    }

    public function fromListing(Request $request, Listing $listing)
    {
        abort_unless($listing->status === 'published', 404);
        abort_if($listing->seller_id === $request->user()->id, 422, 'Você não pode fazer proposta no próprio anúncio.');
        abort_unless($request->user()->kyc_status === 'verified', 403, 'Conclua a validação de identidade antes de negociar.');

        $data = $request->validate([
            'total_amount'=>['required','numeric','min:0.01'],
            'down_payment'=>['nullable','numeric','min:0'],
            'installments'=>['required','integer','min:1','max:120'],
            'monthly_interest'=>['nullable','numeric','min:0','max:20'],
        ]);

        $deal = DB::transaction(function() use ($request,$listing,$data) {
            $deal = Deal::create([
                'seller_id'=>$listing->seller_id,
                'buyer_id'=>$request->user()->id,
                'listing_id'=>$listing->id,
                'origin'=>'classified',
                'status'=>'proposal_sent',
                'total_amount'=>$data['total_amount'],
                'down_payment'=>$data['down_payment'] ?? 0,
                'installments'=>$data['installments'],
                'monthly_interest'=>$data['monthly_interest'] ?? 0,
            ]);

            DealOffer::create([
                'deal_id'=>$deal->id,
                'created_by'=>$request->user()->id,
                'type'=>'proposal',
                'total_amount'=>$deal->total_amount,
                'down_payment'=>$deal->down_payment,
                'installments'=>$deal->installments,
                'monthly_interest'=>$deal->monthly_interest,
                'status'=>'pending',
            ]);

            return $deal;
        });

        return response()->json($deal->load(['listing','seller:id,name','buyer:id,name','offers']), 201);
    }

    public function store(Request $request)
    {
        return response()->json(['message'=>'Fluxo direto será conectado após a jornada de Classificados.'], 501);
    }

    public function counteroffer(Request $request, Deal $deal)
    {
        $user = $request->user();
        abort_unless(in_array($user->id, [$deal->seller_id,$deal->buyer_id], true), 403);
        abort_if($deal->terms_locked_at, 422, 'Condições já consolidadas.');

        $data = $request->validate([
            'total_amount'=>['required','numeric','min:0.01'],
            'down_payment'=>['nullable','numeric','min:0'],
            'installments'=>['required','integer','min:1','max:120'],
            'monthly_interest'=>['nullable','numeric','min:0','max:20'],
        ]);

        $deal->offers()->where('status','pending')->update(['status'=>'superseded']);
        $offer = $deal->offers()->create([
            'created_by'=>$user->id,'type'=>'counteroffer',
            'total_amount'=>$data['total_amount'],'down_payment'=>$data['down_payment']??0,
            'installments'=>$data['installments'],'monthly_interest'=>$data['monthly_interest']??0,
            'status'=>'pending',
        ]);
        $deal->update(['status'=>'counteroffer']);
        return response()->json($offer, 201);
    }

    public function accept(Request $request, Deal $deal)
    {
        abort_unless($request->user()->id === $deal->seller_id, 403, 'Somente o vendedor pode consolidar a proposta nesta etapa.');
        $offer = $deal->offers()->where('status','pending')->latest()->firstOrFail();
        $offer->update(['status'=>'accepted','accepted_at'=>now()]);
        $deal->update([
            'total_amount'=>$offer->total_amount,'down_payment'=>$offer->down_payment,
            'installments'=>$offer->installments,'monthly_interest'=>$offer->monthly_interest,
            'status'=>'accepted','terms_locked_at'=>now(),
        ]);
        return response()->json($deal->fresh('offers'));
    }
}
