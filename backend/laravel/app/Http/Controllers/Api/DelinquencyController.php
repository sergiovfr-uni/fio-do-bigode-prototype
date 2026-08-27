<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Installment;
use App\Models\InstallmentDelinquencyAction;
use App\Services\DealEventService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DelinquencyController extends Controller
{
    public function index(Request $request, Deal $deal, Installment $installment)
    {
        $this->authorizeInstallment($request, $deal, $installment);

        return $installment->delinquencyActions()->with('actor:id,name')->get();
    }

    public function requestReschedule(Request $request, Deal $deal, Installment $installment, DealEventService $events)
    {
        $this->authorizeInstallment($request, $deal, $installment);
        abort_unless((int) $request->user()->id === (int) $deal->buyer_id, 403, 'Somente o comprador pode propor uma nova data.');
        $this->assertOverdue($deal, $installment);
        abort_if($installment->delinquencyActions()->where('type', 'reschedule_requested')->where('status', 'pending')->exists(), 422, 'Já existe uma proposta de nova data aguardando resposta.');

        $data = $request->validate([
            'proposed_due_date' => ['required', 'date', 'after:today', 'before_or_equal:'.today()->addYear()->format('Y-m-d')],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $action = $installment->delinquencyActions()->create([
            'deal_id' => $deal->id,
            'actor_id' => $request->user()->id,
            'type' => 'reschedule_requested',
            'status' => 'pending',
            'payload' => [
                'original_due_date' => $installment->due_date->format('Y-m-d'),
                'proposed_due_date' => $data['proposed_due_date'],
                'message' => $data['message'] ?? null,
            ],
        ]);

        $events->record($deal, $request->user()->id, 'installment_reschedule_requested', ['installment_id' => $installment->id, 'action_id' => $action->id] + $action->payload);
        $events->notify($deal, $deal->seller_id, 'installment_reschedule_requested', 'Nova data proposta', 'O comprador propôs uma nova data para a parcela '.$installment->number.'.', ['deal_id' => $deal->id, 'installment_id' => $installment->id]);

        return response()->json(['message' => 'Proposta enviada ao vendedor.', 'action' => $action], 201);
    }

    public function respondReschedule(Request $request, Deal $deal, Installment $installment, InstallmentDelinquencyAction $action, DealEventService $events)
    {
        $this->authorizeInstallment($request, $deal, $installment);
        abort_unless((int) $request->user()->id === (int) $deal->seller_id, 403, 'Somente o vendedor pode responder à proposta.');
        abort_unless((int) $action->installment_id === (int) $installment->id && $action->type === 'reschedule_requested' && $action->status === 'pending', 422, 'Esta proposta não está mais aguardando resposta.');

        $data = $request->validate([
            'decision' => ['required', 'in:accepted,rejected'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($deal, $installment, $action, $data): void {
            $payload = $action->payload ?? [];
            $payload['seller_message'] = $data['message'] ?? null;
            $payload['responded_at'] = now()->toIso8601String();
            $action->update(['status' => $data['decision'], 'payload' => $payload]);
            if ($data['decision'] === 'accepted') {
                $installment->update(['due_date' => $payload['proposed_due_date']]);
                $hasOtherOverdue = Installment::where('deal_id', $deal->id)
                    ->where('id', '!=', $installment->id)
                    ->whereIn('status', ['pending', 'receipt_submitted'])
                    ->whereDate('due_date', '<', today())
                    ->exists();
                if (!$hasOtherOverdue && $deal->status === 'overdue') $deal->update(['status' => 'active']);
            }
        });

        $accepted = $data['decision'] === 'accepted';
        $events->record($deal, $request->user()->id, 'installment_reschedule_'.$data['decision'], ['installment_id' => $installment->id, 'action_id' => $action->id]);
        $events->notify($deal, $deal->buyer_id, 'installment_reschedule_'.$data['decision'], $accepted ? 'Nova data aceita' : 'Nova data recusada', $accepted ? 'O vendedor aceitou a nova data da parcela '.$installment->number.'.' : 'O vendedor não aceitou a nova data da parcela '.$installment->number.'.', ['deal_id' => $deal->id, 'installment_id' => $installment->id]);

        return response()->json(['message' => $accepted ? 'Nova data confirmada.' : 'Proposta recusada.', 'action' => $action->fresh()]);
    }

    public function issueFormalNotice(Request $request, Deal $deal, Installment $installment, DealEventService $events)
    {
        $this->authorizeInstallment($request, $deal, $installment);
        abort_unless((int) $request->user()->id === (int) $deal->seller_id, 403, 'Somente o vendedor pode emitir o aviso.');
        $this->assertOverdue($deal, $installment);
        abort_if($installment->delinquencyActions()->where('type', 'formal_notice_issued')->exists(), 422, 'O aviso formal desta parcela já foi emitido.');
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        $deal->loadMissing(['seller', 'buyer']);
        $pdf = $this->formalNoticePdf($deal, $installment, $data['message'] ?? null);
        $sha256 = hash('sha256', $pdf);
        $path = 'deals/'.$deal->public_id.'/notices/'.$sha256.'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $documentId = DB::table('deal_documents')->insertGetId([
            'deal_id' => $deal->id, 'uploaded_by' => $request->user()->id, 'type' => 'formal_notice',
            'storage_path' => $path, 'original_name' => 'aviso-formal-parcela-'.$installment->number.'.pdf',
            'mime_type' => 'application/pdf', 'sha256' => $sha256, 'signed' => false, 'content_blob' => $pdf,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $action = $installment->delinquencyActions()->create([
            'deal_id' => $deal->id, 'actor_id' => $request->user()->id, 'type' => 'formal_notice_issued',
            'payload' => ['due_date' => $installment->due_date->format('Y-m-d'), 'message' => $data['message'] ?? null],
            'document_id' => $documentId,
        ]);

        $events->record($deal, $request->user()->id, 'formal_overdue_notice_issued', ['installment_id' => $installment->id, 'document_id' => $documentId]);
        $events->notify($deal, $deal->buyer_id, 'formal_overdue_notice_issued', 'Aviso formal de atraso', 'O vendedor emitiu um aviso formal referente à parcela '.$installment->number.'.', ['deal_id' => $deal->id, 'installment_id' => $installment->id, 'document_id' => $documentId]);

        return response()->json(['message' => 'Aviso formal emitido e registrado na negociação.', 'action' => $action, 'document_id' => $documentId], 201);
    }

    public function requestLegalSupport(Request $request, Deal $deal, Installment $installment, DealEventService $events)
    {
        $this->authorizeInstallment($request, $deal, $installment);
        abort_unless((int) $request->user()->id === (int) $deal->seller_id, 403, 'Somente o vendedor pode solicitar apoio jurídico.');
        $this->assertOverdue($deal, $installment);
        $data = $request->validate([
            'consent' => ['accepted'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        abort_if($installment->delinquencyActions()->where('type', 'legal_support_requested')->whereIn('status', ['pending', 'in_review'])->exists(), 422, 'Já existe uma solicitação de apoio em andamento.');

        $action = $installment->delinquencyActions()->create([
            'deal_id' => $deal->id, 'actor_id' => $request->user()->id, 'type' => 'legal_support_requested',
            'status' => 'pending',
            'payload' => ['message' => $data['message'] ?? null, 'consented_at' => now()->toIso8601String()],
        ]);
        $events->record($deal, $request->user()->id, 'legal_support_requested', ['installment_id' => $installment->id, 'action_id' => $action->id]);
        $events->notify($deal, $deal->seller_id, 'legal_support_requested', 'Solicitação registrada', 'Seu pedido de apoio jurídico foi registrado e aguarda encaminhamento a um parceiro.', ['deal_id' => $deal->id, 'installment_id' => $installment->id]);
        $events->notify($deal, $deal->buyer_id, 'legal_support_requested', 'Atraso em regularização', 'O vendedor solicitou apoio para tratar o atraso da parcela '.$installment->number.'. Você ainda pode enviar o comprovante ou propor uma solução.', ['deal_id' => $deal->id, 'installment_id' => $installment->id]);

        return response()->json(['message' => 'Pedido registrado. Nenhum dado será enviado a um parceiro antes do encaminhamento operacional.', 'action' => $action], 201);
    }

    private function authorizeInstallment(Request $request, Deal $deal, Installment $installment): void
    {
        abort_unless(in_array((int) $request->user()->id, [(int) $deal->seller_id, (int) $deal->buyer_id], true), 403);
        abort_unless((int) $installment->deal_id === (int) $deal->id, 404);
    }

    private function assertOverdue(Deal $deal, Installment $installment): void
    {
        abort_unless(in_array($deal->status, ['active', 'overdue'], true), 422, 'A negociação não está em acompanhamento financeiro.');
        abort_unless(in_array($installment->status, ['pending', 'receipt_submitted'], true) && $installment->due_date->isBefore(today()), 422, 'Esta parcela não está vencida.');
    }

    private function formalNoticePdf(Deal $deal, Installment $installment, ?string $message): string
    {
        $e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $days = $installment->due_date->diffInDays(today());
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>@page{margin:2cm}body{font-family:"DejaVu Sans",sans-serif;color:#111;line-height:1.55;font-size:12px}h1{text-align:center;font-size:18px}.box{border:1px solid #aaa;padding:14px;margin:18px 0}.small{font-size:9px;color:#555}</style></head><body>'
            .'<h1>AVISO FORMAL DE ATRASO</h1><p><b>Negociação:</b> '.$e($deal->public_id).' — '.$e($deal->title).'</p>'
            .'<div class="box"><p><b>Credor/vendedor:</b> '.$e($deal->seller->name).'</p><p><b>Devedor/comprador:</b> '.$e($deal->buyer->name).'</p><p><b>Parcela:</b> '.$installment->number.'</p><p><b>Vencimento:</b> '.$installment->due_date->format('d/m/Y').'</p><p><b>Valor:</b> R$ '.number_format((float) $installment->amount, 2, ',', '.').'</p><p><b>Dias em atraso na emissão:</b> '.$days.'</p></div>'
            .'<p>Por meio deste registro, o credor informa que a parcela acima permanece sem confirmação de pagamento e solicita sua regularização, o envio do comprovante ou uma proposta de nova data pela plataforma.</p>'
            .($message ? '<p><b>Mensagem do vendedor:</b> '.$e($message).'</p>' : '')
            .'<p class="small">Gerado em '.now()->format('d/m/Y H:i:s').'. Este documento registra uma comunicação realizada pela plataforma. Não substitui orientação jurídica, protesto, notificação cartorial ou medida judicial.</p></body></html>';
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        return $dompdf->output();
    }
}
