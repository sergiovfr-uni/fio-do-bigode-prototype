<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return DB::table('notifications')
            ->where('user_id',$request->user()->id)
            ->orderByDesc('id')
            ->paginate(30);
    }

    public function read(Request $request, int $notification)
    {
        $updated = DB::table('notifications')
            ->where('id',$notification)
            ->where('user_id',$request->user()->id)
            ->update(['read_at'=>now(),'updated_at'=>now()]);
        abort_unless($updated, 404);
        return response()->json(['ok'=>true]);
    }

    public function readAll(Request $request)
    {
        DB::table('notifications')->where('user_id',$request->user()->id)->whereNull('read_at')
            ->update(['read_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function timeline(Request $request, Deal $deal)
    {
        abort_unless(in_array($request->user()->id, [$deal->seller_id,$deal->buyer_id], true), 403);
        return DB::table('deal_events')->leftJoin('users','users.id','=','deal_events.actor_id')
            ->where('deal_events.deal_id',$deal->id)
            ->orderBy('deal_events.id')
            ->select('deal_events.id','deal_events.type','deal_events.payload','deal_events.created_at','users.id as actor_id','users.name as actor_name')
            ->get();
    }
}
