<?php
namespace App\Services;
use App\Models\Deal;
use Illuminate\Support\Facades\Storage;
class ContractService {
 public function generate(Deal $deal): array {
  $deal->load(['seller','buyer','listing','offers']);
  abort_unless(in_array($deal->status,['accepted','contract_pending','signature_pending']),422,'A proposta precisa estar aceita antes da geração do contrato.');
  $body=$this->render($deal);$path='deals/'.$deal->public_id.'/contracts/contract-'.$deal->public_id.'.html';Storage::disk('local')->put($path,$body);
  return ['path'=>$path,'sha256'=>hash('sha256',$body),'content'=>$body];
 }
 private function render(Deal $d): string {
  $title='INSTRUMENTO PARTICULAR DE NEGOCIAÇÃO E CONFISSÃO DE DÍVIDA';$item=$d->listing?->title??'Bem/serviço descrito na negociação';$value=number_format((float)$d->total_amount,2,',','.');$entry=number_format((float)$d->down_payment,2,',','.');
  return "<!doctype html><html lang='pt-BR'><meta charset='utf-8'><body><h1>{$title}</h1><p>Negociação: {$d->public_id}</p><p>VENDEDOR/CREDOR: {$d->seller->name}.</p><p>COMPRADOR/DEVEDOR: {$d->buyer->name}.</p><p>Objeto: {$item}.</p><p>Valor total: R$ {$value}. Entrada: R$ {$entry}. Parcelas: {$d->installments}. Juros mensais: {$d->monthly_interest}%.</p><p>As partes declaram que as informações prestadas são de sua responsabilidade e que a plataforma Fio do Bigode atua como ferramenta tecnológica de registro, organização e acompanhamento da negociação, não garantindo solvência, pagamento, propriedade, estado do bem ou adimplemento das partes.</p><p>O documento deverá ser revisado pelas partes antes da assinatura. Após o aceite, será exportado para assinatura externa e o PDF assinado por todas as partes deverá ser importado para a negociação.</p><p>Gerado em ".now()->format('d/m/Y H:i:s').".</p></body></html>";
 }
}