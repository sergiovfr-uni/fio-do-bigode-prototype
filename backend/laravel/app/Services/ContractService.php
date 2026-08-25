<?php

namespace App\Services;

use App\Models\Deal;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

class ContractService
{
    public function generate(Deal $deal, int $generatedBy): array
    {
        $deal->load(['seller', 'buyer', 'listing', 'offers', 'witnesses']);
        abort_unless(in_array($deal->status, ['witnesses_pending', 'signature_pending'], true), 422, 'A proposta precisa estar aceita antes da geração do dossiê.');
        abort_unless(in_array($deal->witnesses->count(), [0, 2], true), 422, 'O dossiê deve ser gerado sem testemunhas ou com exatamente duas testemunhas.');
        abort_unless($deal->seller->hasContractQualification() && $deal->buyer->hasContractQualification(), 422, 'Comprador e vendedor precisam completar a qualificação contratual antes da geração do dossiê.');

        // As condições comerciais ficam bloqueadas no aceite. A qualificação,
        // entretanto, pode ser concluída até a geração do documento.
        $qualificationFields = [
            'name','identity_document','birth_date','marital_status','occupation','nationality',
            'email','phone','address_line','address_number','address_complement','district','city','state','postal_code',
        ];
        $snapshot = $deal->terms_snapshot ?? [];
        foreach (['seller', 'buyer'] as $role) {
            $party = $deal->{$role};
            $snapshot[$role] = array_merge(
                $snapshot[$role] ?? [],
                Arr::only($party->attributesToArray(), $qualificationFields),
                ['cpf'=>$party->getRawOriginal('cpf')]
            );
        }
        $deal->update(['terms_snapshot'=>$snapshot]);
        $deal->setAttribute('terms_snapshot', $snapshot);

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

    public function generateElectronicVersion(Deal $deal, int $generatedBy, string $role): array
    {
        $deal->load(['seller', 'buyer', 'listing', 'offers', 'witnesses']);
        $signatures = DB::table('deal_electronic_signatures')
            ->where('deal_id', $deal->id)
            ->whereNotNull('signed_at')
            ->orderBy('signed_at')
            ->get();
        abort_unless($signatures->contains('role', $role), 422, 'A evidência da assinatura não foi encontrada.');

        $html = $this->render($deal, $signatures);
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $pdf = $dompdf->output();
        $sha256 = hash('sha256', $pdf);
        $type = $role === 'seller' ? 'seller_signed_contract' : 'fully_signed_contract';
        $path = 'deals/'.$deal->public_id.'/contracts/'.$type.'-'.$sha256.'.pdf';
        Storage::disk('local')->put($path, $pdf);

        $signers = $signatures->map(fn ($signature) => [
            'user_id'=>$signature->user_id,
            'role'=>$signature->role,
            'signed_at'=>$signature->signed_at,
            'challenge_id'=>$signature->challenge_id,
        ])->values()->all();
        $documentId = DB::table('deal_documents')->insertGetId([
            'deal_id'=>$deal->id,
            'uploaded_by'=>$generatedBy,
            'type'=>$type,
            'storage_path'=>$path,
            'original_name'=>'dossie-assinado-'.$deal->public_id.'.pdf',
            'mime_type'=>'application/pdf',
            'sha256'=>$sha256,
            'signed'=>true,
            'validation_status'=>'valid',
            'signature_provider'=>'fio-do-bigode-electronic',
            'signer_identifiers'=>json_encode($signers, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'validation_report'=>json_encode(['method'=>'email_otp_plus_drawn_signature','integrity'=>'sha256','signers'=>$signers], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'validated_at'=>now(),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        return ['document_id'=>$documentId, 'path'=>$path, 'sha256'=>$sha256];
    }

    private function render(Deal $deal, $electronicSignatures = null): string
    {
        $electronicSignatures ??= collect();
        $installments = DB::table('installments')->where('deal_id', $deal->id)->orderBy('number')->get();
        $e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
        $cpf = fn ($value) => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', preg_replace('/\D+/', '', (string) $value));
        $cep = fn ($value) => preg_replace('/(\d{5})(\d{3})/', '$1-$2', preg_replace('/\D+/', '', (string) $value));
        $snapshot = $deal->terms_snapshot ?? [];
        $sellerData = $snapshot['seller'] ?? $deal->seller->attributesToArray();
        $buyerData = $snapshot['buyer'] ?? $deal->buyer->attributesToArray();
        $sellerData['cpf'] = $sellerData['cpf'] ?? $deal->seller->getRawOriginal('cpf');
        $buyerData['cpf'] = $buyerData['cpf'] ?? $deal->buyer->getRawOriginal('cpf');

        $qualification = function (array $party) use ($e, $cpf, $cep) {
            $address = ($party['address_line'] ?? '').', '.($party['address_number'] ?? '')
                .(!empty($party['address_complement']) ? ', '.$party['address_complement'] : '')
                .', '.($party['district'] ?? '').', '.($party['city'] ?? '').'/'.($party['state'] ?? '')
                .', CEP '.$cep($party['postal_code'] ?? '');

            return $e($party['name'] ?? '').', '.$e($party['nationality'] ?? '').', '
                .$e($party['marital_status'] ?? '').', '.$e($party['occupation'] ?? '')
                .', documento de identidade '.$e($party['identity_document'] ?? '')
                .', CPF '.$e($cpf($party['cpf'] ?? '')).', residente e domiciliado(a) em '
                .$e($address).', e-mail '.$e($party['email'] ?? '').', telefone '.$e($party['phone'] ?? '');
        };

        $item = $snapshot['title'] ?? $deal->listing?->title ?? $deal->title ?? 'Bem ou serviço descrito na negociação';
        $witnesses = $deal->witnesses->values();
        $withWitnesses = $witnesses->count() === 2;
        $signatureRequirement = $withWitnesses
            ? 'comprador, vendedor e pelas duas testemunhas identificadas abaixo'
            : 'comprador e vendedor';
        $witnessNotice = $withWitnesses
            ? ''
            : '<p><b>Opção sem testemunhas:</b> as partes optaram pela formalização eletrônica sem testemunhas. A dispensa prevista no art. 784, § 4º, do Código de Processo Civil depende de a integridade do título eletrônico ser conferida por provedor de assinatura.</p>';
        $witnessSignatures = $withWitnesses
            ? '<div class="signature">'.$e($witnesses[0]->name).'<br>CPF '.$e($cpf($witnesses[0]->getRawOriginal('cpf'))).'<br>TESTEMUNHA 1</div><div class="signature">'.$e($witnesses[1]->name).'<br>CPF '.$e($cpf($witnesses[1]->getRawOriginal('cpf'))).'<br>TESTEMUNHA 2</div>'
            : '';
        $electronicBlock = function (string $role, array $party, string $label) use ($electronicSignatures, $e, $cpf) {
            $signature = $electronicSignatures->firstWhere('role', $role);
            if (!$signature || !$signature->signature_image_path || !Storage::disk('local')->exists($signature->signature_image_path)) {
                return '<div class="signature">'.$e($party['name'] ?? '').'<br>CPF '.$e($cpf($party['cpf'] ?? '')).'<br>'.$label.'</div>';
            }
            $image = base64_encode(Storage::disk('local')->get($signature->signature_image_path));
            $signedAt = date('d/m/Y H:i:s', strtotime($signature->signed_at));
            return '<div class="signature signed"><img src="data:image/png;base64,'.$image.'" alt="Assinatura"><br><b>'.$e($party['name'] ?? '').'</b><br>CPF '.$e($cpf($party['cpf'] ?? '')).'<br>'.$label.'<br><span class="small">Assinado eletronicamente em '.$e($signedAt).'<br>Autenticação '.$e($signature->challenge_id).'</span></div>';
        };
        $partySignatures = $electronicBlock('seller', $sellerData, 'VENDEDOR/CREDOR')
            .$electronicBlock('buyer', $buyerData, 'COMPRADOR/DEVEDOR');
        $evidenceRows = $electronicSignatures->map(function ($signature) use ($e) {
            $evidence = json_decode($signature->evidence ?: '{}', true);
            return '<tr><td>'.$e(strtoupper($signature->role)).'</td><td>'.$e($signature->challenge_id).'</td><td>'.$e(date('d/m/Y H:i:s', strtotime($signature->signed_at))).'</td><td>'.$e($signature->ip_address).'</td><td class="hash">'.$e($signature->source_document_sha256).'<br>'.$e($signature->signature_image_sha256).'<br>Selo: '.$e($signature->evidence_seal).'</td><td>'.$e($evidence['method'] ?? '').'</td></tr>';
        })->implode('');
        $evidenceAppendix = $electronicSignatures->isEmpty() ? '' : '<div class="page-break"></div><h1>CERTIFICADO DE EVIDÊNCIAS DA ASSINATURA ELETRÔNICA</h1><p>As partes aceitaram expressamente o método de assinatura eletrônica do Fio do Bigode. Cada manifestação foi autenticada por sessão identificada e código de uso único enviado ao e-mail cadastrado. Os hashes abaixo vinculam as evidências à versão do documento apresentada ao signatário e ao desenho registrado.</p><table><thead><tr><th>Papel</th><th>Autenticação</th><th>Data/hora</th><th>IP</th><th>Hashes SHA-256<br>documento / assinatura</th><th>Método</th></tr></thead><tbody>'.$evidenceRows.'</tbody></table><p class="small">Qualquer alteração posterior do conteúdo produz hash diferente e rompe a correspondência com este certificado. A imagem manuscrita é a representação visual da manifestação; a autoria e a integridade são sustentadas pelo conjunto das evidências técnicas registradas.</p>';
        $schedule = $installments->map(fn ($row) => '<tr><td>'.$row->number.'</td><td>'.date('d/m/Y', strtotime($row->due_date)).'</td><td>'.$money($row->amount).'</td></tr>')->implode('');
        $notes = $installments->map(function ($row) use ($deal, $e, $money) {
            return '<div class="page-break"></div><h1>NOTA PROMISSÓRIA Nº '.$row->number.'/'.$deal->installments.'</h1><p>Vencimento: <b>'.date('d/m/Y', strtotime($row->due_date)).'</b></p><p>Valor: <b>'.$money($row->amount).'</b></p><p>Ao vencimento, o COMPRADOR/DEVEDOR pagará ao VENDEDOR/CREDOR o valor acima, referente à negociação '.$e($deal->public_id).', observadas as condições do Acordo e da Confissão de Dívida integrantes deste mesmo dossiê.</p>';
        })->implode('');

        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
            @page{margin:2cm}body{font-family:"DejaVu Sans",sans-serif;font-size:11px;line-height:1.5;color:#111}h1{text-align:center;font-size:16px;margin:0 0 22px}h2{font-size:13px;margin-top:20px}table{width:100%;border-collapse:collapse;margin:12px 0}td,th{border:1px solid #999;padding:6px;text-align:left}.page-break{page-break-before:always}.signatures{margin-top:50px}.signature{display:inline-block;width:47%;margin:28px 1%;text-align:center;border-top:1px solid #222;padding-top:5px;vertical-align:top}.signature.signed{border-top:0}.signature img{display:block;max-width:180px;max-height:70px;margin:0 auto 4px}.small{font-size:9px;color:#555}.hash{font-family:monospace;font-size:7px;word-break:break-all}
        </style></head><body>
        <h1>ACORDO DE NEGOCIAÇÃO, CONFISSÃO DE DÍVIDA E NOTAS PROMISSÓRIAS</h1>
        <p><b>Identificação:</b> '.$e($deal->public_id).'</p>
        <p><b>VENDEDOR/CREDOR:</b> '.$qualification($sellerData).'.</p>
        <p><b>COMPRADOR/DEVEDOR:</b> '.$qualification($buyerData).'.</p>
        <h2>1. Objeto e valor</h2><p>As partes ajustam a negociação de <b>'.$e($item).'</b>, pelo valor total de <b>'.$money($deal->total_amount).'</b>, com entrada de <b>'.$money($deal->down_payment).'</b> e saldo em '.$deal->installments.' parcela(s), à taxa de '.$e(number_format((float) $deal->monthly_interest, 2, ',', '.')).'% ao mês.</p>
        <h2>2. Confissão de dívida</h2><p>O COMPRADOR/DEVEDOR reconhece como líquida, certa e exigível a obrigação decorrente desta negociação, comprometendo-se a pagar os valores nos vencimentos abaixo.</p>
        <table><thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th></tr></thead><tbody>'.$schedule.'</tbody></table>
        <h2>3. Documentos e assinatura</h2><p>Este dossiê reúne o acordo, a confissão de dívida e as notas promissórias. O arquivo final será assinado eletronicamente por '.$signatureRequirement.'. As partes admitem expressamente como válido o método oferecido pelo Fio do Bigode, composto por autenticação da conta, código de uso único enviado ao e-mail cadastrado, aceite de ciência, representação manuscrita e registro de integridade por hash SHA-256.</p>'.$witnessNotice.'
        <h2>4. Papel da plataforma</h2><p>O Fio do Bigode atua como ferramenta tecnológica de registro, organização e acompanhamento, não garantindo solvência, pagamento, propriedade, estado do bem ou adimplemento. As partes são responsáveis pelas informações e devem revisar o conteúdo antes da assinatura.</p>
        <div class="signatures">'.$partySignatures.$witnessSignatures.'</div>
        <p class="small">Gerado em '.now()->format('d/m/Y H:i:s').'.</p>'.$notes.$evidenceAppendix.'</body></html>';
    }
}
