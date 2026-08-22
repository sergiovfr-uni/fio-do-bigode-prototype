<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\SignatureValidationService;

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
    public function storeSignedBase64(Request $request, Deal $deal, SignatureValidationService $validator)
    {
        abort_unless(in_array($request->user()->id,[$deal->seller_id,$deal->buyer_id],true),403);
        abort_unless(in_array($deal->status,['signature_pending','signature_validation_pending','signature_validation_rejected'],true),422,'Cadastre as testemunhas e gere o dossiê antes de importar a versão assinada.');

        $data=$request->validate([
            'file_name'=>['required','string','max:255'],
            'file_base64'=>['required','string'],
            'signature_provider'=>['required','in:gov.br-external,icp-brasil-external'],
        ]);

        $encoded=preg_replace('/^data:application\/pdf;base64,/', '', $data['file_base64']);
        $binary=base64_decode($encoded, true);
        abort_unless($binary !== false && str_starts_with($binary, '%PDF-'),422,'PDF inválido.');
        abort_if(strlen($binary) > 10 * 1024 * 1024,422,'O PDF deve ter no máximo 10 MB.');

        $sha256=hash('sha256',$binary);
        $path='deals/'.$deal->public_id.'/quarantine/'.$sha256.'.pdf';
        Storage::disk('local')->put($path,$binary);

        $id=DB::table('deal_documents')->insertGetId([
            'deal_id'=>$deal->id,'uploaded_by'=>$request->user()->id,'type'=>'signed_contract','storage_path'=>$path,
            'original_name'=>basename($data['file_name']),'mime_type'=>'application/pdf','sha256'=>$sha256,
            'signed'=>false,'validation_status'=>'pending','signature_provider'=>$data['signature_provider'],
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        $deal->update(['status'=>'signature_validation_pending']);
        $result=$validator->validate($binary,$deal);

        DB::table('deal_documents')->where('id',$id)->update([
            'signed'=>$result['status']==='valid',
            'validation_status'=>$result['status'],
            'signer_identifiers'=>json_encode($result['signers'],JSON_UNESCAPED_UNICODE),
            'validation_report'=>$result['report'] ? json_encode($result['report'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            'validated_at'=>$result['status']==='pending' ? null : now(),
            'updated_at'=>now(),
        ]);

        $deal->update(['status'=>match($result['status']){
            'valid'=>'active',
            'rejected'=>'signature_validation_rejected',
            default=>'signature_validation_pending',
        }]);

        return response()->json([
            'document'=>DB::table('deal_documents')->find($id),
            'validation_status'=>$result['status'],
            'message'=>match($result['status']){
                'valid'=>'Todas as assinaturas exigidas foram validadas. Negociação ativada.',
                'rejected'=>$result['reason'],
                default=>'Documento recebido em quarentena e aguardando validação criptográfica.',
            },
        ],202);
    }

}
