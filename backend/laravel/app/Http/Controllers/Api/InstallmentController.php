<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Installment;
use App\Models\WalletAccount;
use App\Services\DealEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstallmentController extends Controller
{
    public function index(Request $request, Deal $deal)
    {
        abort_unless(in_array((int) $request->user()->id, [(int) $deal->seller_id, (int) $deal->buyer_id], true), 403);

        return response()->json($deal->installments()->get());
    }

    public function storeReceipt(Request $request, Deal $deal, Installment $installment, DealEventService $events)
    {
        abort_unless((int) $installment->deal_id === (int) $deal->id, 404);
        abort_unless((int) $request->user()->id === (int) $deal->buyer_id, 403, 'Somente o comprador pode enviar o comprovante.');
        abort_unless(in_array($deal->status, ['active','overdue'], true), 422, 'A negociação ainda não está na fase de pagamentos.');
        abort_unless($installment->status !== 'paid', 422, 'Esta parcela já está quitada.');

        $data = $request->validate([
            'file_name'=>['required','string','max:255'],
            'file_base64'=>['required','string'],
        ]);
        $encoded = preg_replace('/^data:(application\/pdf|image\/(jpeg|png|webp));base64,/', '', $data['file_base64']);
        $binary = base64_decode($encoded, true);
        abort_unless($binary !== false, 422, 'Arquivo inválido.');
        abort_if(strlen($binary) > 10 * 1024 * 1024, 422, 'O comprovante deve ter no máximo 10 MB.');

        $sha256 = hash('sha256', $binary);
        $path = 'deals/'.$deal->public_id.'/installments/'.$installment->number.'/'.$sha256;
        Storage::disk('local')->put($path, $binary);

        DB::transaction(function () use ($deal, $installment, $request, $data, $path, $sha256, $binary): void {
            if ($installment->receipt_document_id) {
                DB::table('deal_documents')->where('id', $installment->receipt_document_id)->delete();
            }
            $documentId = DB::table('deal_documents')->insertGetId([
                'deal_id'=>$deal->id,
                'uploaded_by'=>$request->user()->id,
                'type'=>'installment_receipt',
                'storage_path'=>$path,
                'original_name'=>basename($data['file_name']),
                'mime_type'=>'application/octet-stream',
                'sha256'=>$sha256,
                'signed'=>false,
                'content_blob'=>$binary,
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);
            $installment->update([
                'status'=>'receipt_submitted',
                'receipt_document_id'=>$documentId,
                'receipt_uploaded_at'=>now(),
            ]);
        });

        $events->record($deal, $request->user()->id, 'installment_receipt_uploaded', ['installment_id'=>$installment->id,'number'=>$installment->number]);
        $events->notify($deal, $deal->seller_id, 'installment_receipt_received', 'Comprovante de parcela recebido', 'O comprador enviou o comprovante da parcela '.$installment->number.'.', ['deal_id'=>$deal->id,'installment_id'=>$installment->id]);

        return response()->json(['message'=>'Comprovante enviado ao vendedor.','installment'=>$installment->fresh()], 201);
    }

    public function downloadReceipt(Request $request, Deal $deal, Installment $installment)
    {
        abort_unless((int) $installment->deal_id === (int) $deal->id, 404);
        abort_unless(in_array((int) $request->user()->id, [(int) $deal->seller_id, (int) $deal->buyer_id], true), 403);
        $document = $installment->receipt_document_id ? DB::table('deal_documents')->find($installment->receipt_document_id) : null;
        abort_unless($document, 404, 'Comprovante não disponível.');
        if (Storage::disk('local')->exists($document->storage_path)) {
            return Storage::disk('local')->download($document->storage_path, $document->original_name);
        }
        abort_unless($document->content_blob,410,'Este comprovante antigo não está mais disponível.');
        return response()->streamDownload(fn () => print($document->content_blob),$document->original_name);
    }

    public function markPaid(Request $request, Deal $deal, Installment $installment, DealEventService $events)
    {
        abort_unless((int) $installment->deal_id === (int) $deal->id, 404);
        abort_unless((int) $request->user()->id === (int) $deal->seller_id, 403, 'Somente o vendedor pode confirmar o recebimento da parcela.');
        abort_unless(in_array($deal->status, ['active','overdue'], true), 422, 'A negociação ainda não está na fase de pagamentos.');
        abort_unless($installment->receipt_document_id, 422, 'O comprador ainda não enviou o comprovante desta parcela.');

        $data = $request->validate([
            'external_payment_id'=>['nullable','string','max:120'],
            'description'=>['nullable','string','max:255'],
        ]);

        DB::transaction(function () use ($deal, $installment, $data) {
            if ($installment->status === 'paid') return;

            $installment->update([
                'status'=>'paid',
                'paid_at'=>now(),
                'external_payment_id'=>$data['external_payment_id'] ?? null,
            ]);

            $sellerWallet = WalletAccount::firstOrCreate(
                ['user_id'=>$deal->seller_id],
                ['provider'=>'mock','status'=>'active','available_balance'=>0]
            );

            $sellerWallet->transactions()->create([
                'deal_id'=>$deal->id,
                'installment_id'=>$installment->id,
                'type'=>'installment',
                'direction'=>'credit',
                'amount'=>$installment->amount,
                'status'=>'posted',
                'external_id'=>$data['external_payment_id'] ?? null,
                'description'=>$data['description'] ?? ('Parcela '.$installment->number),
                'occurred_at'=>now(),
            ]);

            $sellerWallet->increment('available_balance', (float) $installment->amount);
        });

        $events->record($deal, $request->user()->id, 'installment_confirmed', ['installment_id'=>$installment->id,'number'=>$installment->number]);
        $events->notify($deal, $deal->buyer_id, 'installment_confirmed', 'Pagamento confirmado', 'O vendedor confirmou o recebimento da parcela '.$installment->number.'.', ['deal_id'=>$deal->id,'installment_id'=>$installment->id]);

        if (!$deal->installments()->where('status', '!=', 'paid')->exists()) {
            $deal->update(['status'=>'paid_off','paid_off_at'=>now()]);
            $events->record($deal, $request->user()->id, 'deal_paid_off');
            $events->notify($deal, $deal->buyer_id, 'deal_paid_off', 'Negociação quitada — avalie a outra parte', 'Todas as parcelas foram confirmadas. Faça sua avaliação em bigodinhos para concluir o termo de quitação.', ['deal_id'=>$deal->id]);
            $events->notify($deal, $deal->seller_id, 'deal_rating_required', 'Avalie o comprador', 'A negociação foi quitada. Faça sua avaliação em bigodinhos para concluir o termo de quitação.', ['deal_id'=>$deal->id]);
        }

        return response()->json($installment->fresh());
    }
}
