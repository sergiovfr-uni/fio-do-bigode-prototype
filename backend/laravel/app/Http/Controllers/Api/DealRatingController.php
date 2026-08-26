<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\User;
use App\Services\DealEventService;
use App\Services\DischargeTermService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealRatingController extends Controller
{
    private function authorizeParty(Request $request, Deal $deal): void
    {
        abort_unless(in_array((int) $request->user()->id, [(int) $deal->seller_id, (int) $deal->buyer_id], true), 403);
    }

    public function index(Request $request, Deal $deal)
    {
        $this->authorizeParty($request, $deal);
        $ratings = DB::table('deal_ratings')->where('deal_id', $deal->id)->get();
        return response()->json([
            'ratings'=>$ratings,
            'my_rating'=>$ratings->firstWhere('rater_id', $request->user()->id),
            'complete'=>$ratings->count() === 2,
        ]);
    }

    public function store(Request $request, Deal $deal, DischargeTermService $terms, DealEventService $events)
    {
        $this->authorizeParty($request, $deal);
        abort_unless($deal->status === 'paid_off', 422, 'A avaliação é liberada após a quitação.');
        $data = $request->validate(['rating'=>['required','integer','between:1,5'], 'comment'=>['nullable','string','max:500']]);
        $ratedUserId = (int) $request->user()->id === (int) $deal->seller_id ? $deal->buyer_id : $deal->seller_id;
        DB::table('deal_ratings')->updateOrInsert(
            ['deal_id'=>$deal->id, 'rater_id'=>$request->user()->id],
            ['rated_user_id'=>$ratedUserId, 'rating'=>$data['rating'], 'comment'=>$data['comment'] ?? null, 'created_at'=>now(), 'updated_at'=>now()]
        );
        $average = DB::table('deal_ratings')->where('rated_user_id', $ratedUserId)->avg('rating');
        User::whereKey($ratedUserId)->update(['reputation_score'=>(int) round(((float) $average) * 20)]);
        $events->record($deal, $request->user()->id, 'deal_rating_submitted', ['rated_user_id'=>$ratedUserId, 'rating'=>$data['rating']]);
        $count = DB::table('deal_ratings')->where('deal_id', $deal->id)->count();
        $document = null;
        if ($count === 2) {
            $document = $terms->generate($deal->fresh(), $request->user()->id);
            $events->notify($deal, $deal->seller_id, 'discharge_term_ready', 'Termo de quitação disponível', 'As duas avaliações foram concluídas e o termo está disponível para download.', ['deal_id'=>$deal->id]);
            $events->notify($deal, $deal->buyer_id, 'discharge_term_ready', 'Termo de quitação disponível', 'As duas avaliações foram concluídas e o termo está disponível para download.', ['deal_id'=>$deal->id]);
        }
        return response()->json(['message'=>$count === 2 ? 'Avaliação registrada. Termo de quitação gerado.' : 'Avaliação registrada. Aguardando a avaliação da outra parte.', 'complete'=>$count === 2, 'document'=>$document]);
    }
}
