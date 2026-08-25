<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\SignatureValidationService;
use App\Services\DealEventService;

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
    public function storeSignedBase64(Request $request, Deal $deal, SignatureValidationService $validator, DealEventService $events)
    {
        abort_unless(in_array($request->user()->id,[$deal->seller_id,$deal->buyer_id],true),403);
        $retrying = in_array($deal->status,['signature_validation_pending','signature_validation_rejected'],true);
        $sellerPhase = $deal->status === 'signature_pending' || ($retrying && !$deal->seller_signed_document_id && (int)$request->user()->id === (int)$deal->seller_id);
        $buyerPhase = $deal->status === 'counterparty_signature_pending' || ($retrying && $deal->seller_signed_document_id && (int)$request->user()->id === (int)$deal->buyer_id);
        abort_unless($sellerPhase || $buyerPhase || in_array($deal->status,['signature_validation_pending','signature_validation_rejected'],true),422,'O documento não está na etapa de assinatura.');
        if ($sellerPhase) abort_unless((int)$request->user()->id === (int)$deal->seller_id,403,'O vendedor precisa assinar primeiro.');
        if ($buyerPhase) abort_unless((int)$request->user()->id === (int)$deal->buyer_id,403,'Agora o comprador precisa assinar o documento recebido do vendedor.');

        if ($buyerPhase) {
            $sellerDocument = DB::table('deal_documents')->find($deal->seller_signed_document_id);
            abort_unless($sellerDocument && Storage::disk('local')->exists($sellerDocument->storage_path), 422, 'O documento assinado pelo vendedor não está disponível.');
        }

        $data=$request->validate([
            'file_name'=>['required','string','max:255'],
            'file_base64'=>['required','string'],
            'signature_provider'=>['required','in:gov.br-external,icp-brasil-external'],
        ]);

        $encoded=preg_replace('/^data:application\/pdf;base64,/', '', $data['file_base64']);
        $binary=base64_decode($encoded, true);
        abort_unless($binary !== false && str_starts_with($binary, '%PDF-'),422,'PDF inválido.');
        abort_if(strlen($binary) > 10 * 1024 * 1024,422,'O PDF deve ter no máximo 10 MB.');

        if ($buyerPhase) {
            $sellerBinary = Storage::disk('local')->get($sellerDocument->storage_path);
            abort_unless(str_starts_with($binary, $sellerBinary), 422, 'Envie o mesmo PDF recebido do vendedor, preservando a assinatura anterior.');
        }

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
        $phase = $sellerPhase ? 'seller' : 'final';
        $result=$validator->validate($binary,$deal,$phase);

        DB::table('deal_documents')->where('id',$id)->update([
            'signed'=>$result['status']==='valid',
            'validation_status'=>$result['status'],
            'signer_identifiers'=>json_encode($result['signers'],JSON_UNESCAPED_UNICODE),
            'validation_report'=>$result['report'] ? json_encode($result['report'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            'validated_at'=>$result['status']==='pending' ? null : now(),
            'updated_at'=>now(),
        ]);

        $nextValidStatus = $sellerPhase
            ? 'counterparty_signature_pending'
            : ((float) $deal->down_payment > 0 ? 'entry_receipt_pending' : 'active');
        $deal->update(['status'=>match($result['status']){
            'valid'=>$nextValidStatus,
            'rejected'=>'signature_validation_rejected',
            default=>'signature_validation_pending',
        }]);
        if ($result['status'] === 'valid' && $sellerPhase) {
            $deal->update(['seller_signed_document_id'=>$id]);
            $events->record($deal,$request->user()->id,'seller_signature_validated',['document_id'=>$id]);
            $events->notify($deal,$deal->buyer_id,'buyer_signature_required','Documento assinado pelo vendedor','Baixe o documento assinado pelo vendedor, assine digitalmente e devolva pela plataforma.',['deal_id'=>$deal->id]);
        }
        if ($result['status'] === 'valid' && !$sellerPhase) {
            $deal->update(['fully_signed_document_id'=>$id,'formalized_at'=>now()]);
            $events->record($deal,$request->user()->id,'all_signatures_validated',['document_id'=>$id]);
            $message = (float) $deal->down_payment > 0
                ? 'As assinaturas foram validadas. Aguardando o comprovante da entrada.'
                : 'As assinaturas foram validadas. A negociação entrou no acompanhamento das parcelas.';
            $events->notify($deal,$deal->seller_id,'documental_closing_complete','Etapa documental concluída',$message,['deal_id'=>$deal->id]);
        }

        return response()->json([
            'document'=>DB::table('deal_documents')->find($id),
            'validation_status'=>$result['status'],
            'message'=>match($result['status']){
                'valid'=>$sellerPhase
                    ? 'Assinatura do vendedor validada. Documento liberado para o comprador.'
                    : ((float) $deal->down_payment > 0
                        ? 'Assinaturas validadas. Agora envie o comprovante da entrada.'
                        : 'Assinaturas validadas. Negociação em acompanhamento.'),
                'rejected'=>$result['reason'],
                default=>'Documento recebido em quarentena e aguardando validação criptográfica.',
            },
        ],202);
    }

    public function storeEntryReceiptBase64(Request $request, Deal $deal, DealEventService $events)
    {
        abort_unless((int)$request->user()->id === (int)$deal->buyer_id,403,'Somente o comprador pode enviar o comprovante da entrada.');
        abort_unless($deal->status === 'entry_receipt_pending',422,'A etapa documental precisa estar concluída antes do comprovante.');
        $data=$request->validate(['file_name'=>['required','string','max:255'],'file_base64'=>['required','string']]);
        $encoded=preg_replace('/^data:(application\/pdf|image\/(jpeg|png|webp));base64,/', '', $data['file_base64']);
        $binary=base64_decode($encoded,true);
        abort_unless($binary!==false,422,'Arquivo inválido.');
        abort_if(strlen($binary)>10*1024*1024,422,'O comprovante deve ter no máximo 10 MB.');
        $sha256=hash('sha256',$binary);$path='deals/'.$deal->public_id.'/receipts/'.$sha256;
        Storage::disk('local')->put($path,$binary);
        $id=DB::table('deal_documents')->insertGetId(['deal_id'=>$deal->id,'uploaded_by'=>$request->user()->id,'type'=>'receipt','storage_path'=>$path,'original_name'=>basename($data['file_name']),'mime_type'=>'application/octet-stream','sha256'=>$sha256,'signed'=>false,'created_at'=>now(),'updated_at'=>now()]);
        $deal->update(['entry_receipt_document_id'=>$id,'status'=>'entry_confirmation_pending']);
        $events->record($deal,$request->user()->id,'entry_receipt_uploaded',['document_id'=>$id]);
        $events->notify($deal,$deal->seller_id,'entry_receipt_received','Comprovante da entrada recebido','Confira o comprovante e confirme o recebimento da entrada.',['deal_id'=>$deal->id]);
        return response()->json(['message'=>'Comprovante enviado ao vendedor.','document_id'=>$id],201);
    }

    public function confirmEntryReceipt(Request $request, Deal $deal, DealEventService $events)
    {
        abort_unless((int)$request->user()->id === (int)$deal->seller_id,403,'Somente o vendedor pode confirmar o recebimento.');
        abort_unless($deal->status === 'entry_confirmation_pending' && $deal->entry_receipt_document_id,422,'Não há comprovante aguardando confirmação.');
        $deal->update(['entry_confirmed_at'=>now(),'status'=>'active']);
        $events->record($deal,$request->user()->id,'entry_payment_confirmed');
        $events->notify($deal,$deal->buyer_id,'entry_payment_confirmed','Entrada confirmada','O vendedor confirmou o recebimento. A negociação agora está em acompanhamento das parcelas.',['deal_id'=>$deal->id]);
        return response()->json(['message'=>'Entrada confirmada. Negociação ativada.']);
    }

    public function downloadEntryReceipt(Request $request, Deal $deal)
    {
        abort_unless(in_array($request->user()->id,[$deal->seller_id,$deal->buyer_id],true),403);
        $document=$deal->entry_receipt_document_id ? DB::table('deal_documents')->find($deal->entry_receipt_document_id) : null;
        abort_unless($document && Storage::disk('local')->exists($document->storage_path),404,'Comprovante não disponível.');
        return Storage::disk('local')->download($document->storage_path,$document->original_name);
    }

}
