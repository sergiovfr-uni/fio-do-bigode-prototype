<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    public function generate(Request $request, Deal $deal, ContractService $service)
    {
        abort_unless((int) $request->user()->id === (int) $deal->seller_id, 403, 'Somente o vendedor pode gerar o documento.');
        abort_unless($deal->status === 'witnesses_pending', 422, 'O documento só pode ser gerado após o aceite das condições.');

        $generated = $service->generate($deal, $request->user()->id);
        $deal->update(['status'=>'signature_pending']);

        return response()->json([
            'deal_id'=>$deal->public_id,
            'status'=>'signature_pending',
            'document'=>['type'=>'unsigned_contract','sha256'=>$generated['sha256']],
            'message'=>'Documento gerado. O vendedor deve assinar primeiro.',
        ]);
    }

    public function download(Request $request, Deal $deal)
    {
        abort_unless(in_array((int) $request->user()->id, [(int) $deal->seller_id, (int) $deal->buyer_id], true), 403);

        $documentId = (int) $request->user()->id === (int) $deal->buyer_id && $deal->seller_signed_document_id
            ? $deal->seller_signed_document_id
            : null;

        $document = $documentId
            ? DB::table('deal_documents')->find($documentId)
            : DB::table('deal_documents')->where('deal_id',$deal->id)->where('type','unsigned_contract')->latest()->first();

        abort_unless($document && Storage::disk('local')->exists($document->storage_path), 404, 'Documento ainda não disponível para esta parte.');

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_name,
            ['Content-Type'=>'application/pdf']
        );
    }
}
