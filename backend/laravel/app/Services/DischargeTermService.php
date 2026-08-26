<?php

namespace App\Services;

use App\Models\Deal;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DischargeTermService
{
    public function generate(Deal $deal, int $generatedBy): array
    {
        $deal->load(['seller', 'buyer', 'listing', 'installments']);
        abort_unless($deal->status === 'paid_off', 422, 'A negociação ainda não está quitada.');
        $ratings = DB::table('deal_ratings')->where('deal_id', $deal->id)->get()->keyBy('rater_id');
        abort_unless($ratings->count() === 2, 422, 'Comprador e vendedor precisam concluir a avaliação.');

        $e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $sellerRating = $ratings->get($deal->buyer_id);
        $buyerRating = $ratings->get($deal->seller_id);
        $rows = $deal->installments->map(fn ($item) => '<tr><td>'.$item->number.'ª</td><td>'.date('d/m/Y', strtotime($item->due_date)).'</td><td>'.$money($item->amount).'</td><td>'.($item->paid_at ? date('d/m/Y H:i', strtotime($item->paid_at)) : '-').'</td></tr>')->implode('');
        $paidOffAt = $deal->paid_off_at ? $deal->paid_off_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i');
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>@page{margin:2cm}body{font-family:"DejaVu Sans",sans-serif;font-size:11px;line-height:1.55;color:#111}h1{text-align:center;font-size:17px}table{width:100%;border-collapse:collapse;margin:18px 0}td,th{border:1px solid #999;padding:7px}.rating{padding:10px;border:1px solid #d6b85c;margin:8px 0}.small{font-size:9px;color:#555}</style></head><body>'
            .'<h1>TERMO DE QUITAÇÃO DA NEGOCIAÇÃO</h1><p><b>Identificação:</b> '.$e($deal->public_id).'</p>'
            .'<p><b>Vendedor/credor:</b> '.$e($deal->seller->name).'<br><b>Comprador/devedor:</b> '.$e($deal->buyer->name).'</p>'
            .'<p>O vendedor declara, para os devidos fins, que recebeu integralmente o valor de <b>'.$money($deal->total_amount).'</b>, referente à negociação <b>'.$e($deal->title ?: $deal->listing?->title ?: 'registrada na plataforma').'</b>, dando ao comprador plena, geral e irrevogável quitação das obrigações financeiras registradas nesta negociação até '.$e($paidOffAt).'.</p>'
            .'<table><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Pagamento confirmado</th></tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<h2>Avaliação entre as partes</h2><div class="rating"><b>Comprador avaliou o vendedor:</b> '.$sellerRating->rating.' de 5 bigodinhos'.($sellerRating->comment ? '<br>'.$e($sellerRating->comment) : '').'</div>'
            .'<div class="rating"><b>Vendedor avaliou o comprador:</b> '.$buyerRating->rating.' de 5 bigodinhos'.($buyerRating->comment ? '<br>'.$e($buyerRating->comment) : '').'</div>'
            .'<p class="small">Documento gerado eletronicamente pelo Fio do Bigode. Integridade vinculada ao SHA-256 e à trilha de pagamentos e avaliações da negociação.</p></body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $pdf = $dompdf->output();
        $sha256 = hash('sha256', $pdf);
        $path = 'deals/'.$deal->public_id.'/discharge/termo-'.$sha256.'.pdf';
        Storage::disk('local')->put($path, $pdf);
        DB::table('deal_documents')->where('deal_id', $deal->id)->where('type', 'discharge_term')->delete();
        $id = DB::table('deal_documents')->insertGetId([
            'deal_id'=>$deal->id, 'uploaded_by'=>$generatedBy, 'type'=>'discharge_term',
            'storage_path'=>$path, 'original_name'=>'termo-quitacao-'.$deal->public_id.'.pdf',
            'mime_type'=>'application/pdf', 'sha256'=>$sha256, 'signed'=>false, 'content_blob'=>$pdf,
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        return ['document_id'=>$id, 'sha256'=>$sha256];
    }
}
