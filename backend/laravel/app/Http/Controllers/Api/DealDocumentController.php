<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealDocumentController extends Controller
{
    public function index(Request $request, Deal $deal)
    {
        abort_unless(in_array($request->user()->id,[$deal->seller_id,$deal->buyer_id],true),403);
        return DB::table('deal_documents')->where('deal_id',$deal->id)->orderBy('created_at')->get();
    }

    public function store(Request $request, Deal $deal)
    {
        abort_unless(in_array($request->user()->id,[$deal->seller_id,$deal->buyer_id],true),403);
        $data = $request->validate([
            'type'=>['required','in:identity,asset_photo,contract,unsigned_contract,signed_contract,receipt,other'],
            'storage_path'=>['required','string','max:500'],
            'original_name'=>['required','string','max:255'],
            'mime_type'=>['nullable','string','max:100'],
            'sha256'=>['required','string','size:64'],
            'signed'=>['nullable','boolean'],
        ]);
        $id = DB::table('deal_documents')->insertGetId([
            ...$data,'deal_id'=>$deal->id,'uploaded_by'=>$request->user()->id,
            'signed'=>$data['signed']??false,'created_at'=>now(),'updated_at'=>now(),
        ]);
        if (($data['type']==='signed_contract') && ($data['signed']??false)) $deal->update(['status'=>'active']);
        return response()->json(DB::table('deal_documents')->find($id),201);
    }
}
