<?php

namespace App\Services;

use App\Models\Deal;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    public function generate(Deal $deal, int $generatedBy): array
    {
        $deal->load(['seller', 'buyer', 'listing', 'offers', 'witnesses']);
        abort_unless(in_array($deal->status, ['witnesses_pending', 'signature_pending'], true), 422, 'A proposta precisa estar aceita antes da geração do dossiê.');
        abort_unless($deal->witnesses->count() === 2, 422, 'Cadastre exatamente duas testemunhas antes de gerar o dossiê.');

        $html = $this->render($deal);
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $pdf = $dompdf->output();
        $sha256 = hash('sha256', $pdf);
        $path = 'deals/'.$deal->public_id.'/contracts/dossie-'.$sha256.'.pdf';
        Storage::disk('local')->put($path, $pdf);

        DB::table('deal_documents')->where('deal_id', $deal->id)->where('type', 'unsigned_contract')->delete();
        DB::table('deal_documents')->insert([
            'deal_id' => $deal->id, 'uploaded_by' => $generatedBy, 'type' => 'unsigned_contract',
            'storage_path' => $path, 'original_name' => 'dossie-negociacao-'.$deal->public_id.'.pdf',
            'mime_type' => 'application/pdf', 'sha256' => $sha256, 'signed' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return ['path' => $path, 'sha256' => $sha256];
    }

    private function render(Deal $deal): string
    {
        $installments = DB::table('installments')->where('deal_id', $deal->id)->orderBy('number')->get();
        $e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $cpf = fn ($value) => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', preg_replace('/\D+/', '', (string) $value));
        $item = $deal->listing?->title ?? $deal->title ?? 'Bem ou serviço descrito na negociação';
        $witnesses = $deal->witnesses->values();
        $schedule = $installments->map(fn ($row) => '<tr><td>'.$row->number.'</td><td>'.date('d/m/Y', strtotime($row->due_date)).'</td><td>'.$money($row->amount).'</td></tr>')->implode('');
        $notes = $installments->map(function ($row) use ($deal, $e, $money) {
            return '<div class="page-break"></div><h1>NOTA PROMISSÓRIA Nº '.$row->number.'/'.$deal->installments.'</h1><p>Vencimento: <b>'.date('d/m/Y', strtotime($row->due_date)).'</b></p><p>Valor: <b>'.$money($row->amount).'</b></p><p>Ao vencimento, o COMPRADOR/DEVEDOR pagará ao VENDEDOR/CREDOR o valor acima, referente à negociação '.$e($deal->public_id).', observadas as condições do Acordo e da Confissão de Dívida integrantes deste mesmo dossiê.</p>';
        })->implode('');

        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
            @page{margin:2cm}body{font-family:"DejaVu Sans",sans-serif;font-size:11px;line-height:1.5;color:#111}h1{text-align:center;font-size:16px;margin:0 0 22px}h2{font-size:13px;margin-top:20px}table{width:100%;border-collapse:collapse;margin:12px 0}td,th{border:1px solid #999;padding:6px;text-align:left}.page-break{page-break-before:always}.signatures{margin-top:50px}.signature{display:inline-block;width:47%;margin:28px 1%;text-align:center;border-top:1px solid #222;padding-top:5px;vertical-align:top}.small{font-size:9px;color:#555}
        </style></head><body>
        <h1>ACORDO DE NEGOCIAÇÃO, CONFISSÃO DE DÍVIDA E NOTAS PROMISSÓRIAS</h1>
        <p><b>Identificação:</b> '.$e($deal->public_id).'</p>
        <p><b>VENDEDOR/CREDOR:</b> '.$e($deal->seller->name).', CPF '.$e($cpf($deal->seller->cpf)).'.</p>
        <p><b>COMPRADOR/DEVEDOR:</b> '.$e($deal->buyer->name).', CPF '.$e($cpf($deal->buyer->cpf)).'.</p>
        <h2>1. Objeto e valor</h2><p>As partes ajustam a negociação de <b>'.$e($item).'</b>, pelo valor total de <b>'.$money($deal->total_amount).'</b>, com entrada de <b>'.$money($deal->down_payment).'</b> e saldo em '.$deal->installments.' parcela(s), à taxa de '.$e(number_format((float) $deal->monthly_interest, 2, ',', '.')).'% ao mês.</p>
        <h2>2. Confissão de dívida</h2><p>O COMPRADOR/DEVEDOR reconhece como líquida, certa e exigível a obrigação decorrente desta negociação, comprometendo-se a pagar os valores nos vencimentos abaixo.</p>
        <table><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th></tr></thead><tbody>'.$schedule.'</tbody></table>
        <h2>3. Documentos e assinatura</h2><p>Este dossiê reúne o acordo, a confissão de dívida e as notas promissórias. O arquivo final deverá ser assinado digitalmente pelo comprador, vendedor e pelas duas testemunhas. A plataforma somente ativará a negociação após validação criptográfica da integridade do PDF e das quatro identidades.</p>
        <h2>4. Papel da plataforma</h2><p>O Fio do Bigode atua como ferramenta tecnológica de registro, organização e acompanhamento, não garantindo solvência, pagamento, propriedade, estado do bem ou adimplemento. As partes são responsáveis pelas informações e devem revisar o conteúdo antes da assinatura.</p>
        <div class="signatures"><div class="signature">'.$e($deal->seller->name).'<br>CPF '.$e($cpf($deal->seller->cpf)).'<br>VENDEDOR/CREDOR</div><div class="signature">'.$e($deal->buyer->name).'<br>CPF '.$e($cpf($deal->buyer->cpf)).'<br>COMPRADOR/DEVEDOR</div><div class="signature">'.$e($witnesses[0]->name).'<br>CPF '.$e($cpf($witnesses[0]->getRawOriginal('cpf'))).'<br>TESTEMUNHA 1</div><div class="signature">'.$e($witnesses[1]->name).'<br>CPF '.$e($cpf($witnesses[1]->getRawOriginal('cpf'))).'<br>TESTEMUNHA 2</div></div>
        <p class="small">Gerado em '.now()->format('d/m/Y H:i:s').'.</p>'.$notes.'</body></html>';
    }
}
