<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            'file'=>['required','file','max:10240','mimes:pdf,jpg,jpeg,png,webp'],
            'signed'=>['nullable','boolean'],
        ]);
        $file=$request->file('file');
        $sha256=hash_file('sha256',$file->getRealPath());
        $path=$file->storeAs('deals/'.$deal->public_id,$sha256.'.'.$file->getClientOriginalExtension(),'private');
        $id = DB::table('deal_documents')->insertGetId([
            'deal_id'=>$deal->id,'uploaded_by'=>$request->user()->id,'type'=>$data['type'],'storage_path'=>$path,
            'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'sha256'=>$sha256,
            'signed'=>$data['signed']??false,'created_at'=>now(),'updated_at'=>now(),
        ]);
        if (($data['type']==='signed_contract') && ($data['signed']??false)) $deal->update(['status'=>'active']);
        return response()->json(DB::table('deal_documents')->find($id),201);
    }
}
