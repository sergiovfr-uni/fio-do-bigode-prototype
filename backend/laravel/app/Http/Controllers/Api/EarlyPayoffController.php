<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Services\DealEventService;
use App\Services\DischargeTermService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EarlyPayoffController extends Controller
{
    private const OPEN = ['requested', 'countered', 'accepted', 'receipt_submitted'];

    private function participant(Request $request, Deal $deal): bool
    {
        $id = (int) $request->user()->id;
        abort_unless(in_array($id, [(int) $deal->buyer_id, (int) $deal->seller_id], true), 403);
        return $id === (int) $deal->buyer_id;
    }

    private function inPayment(Deal $deal): bool
    {
        return $deal->formalized_at !== null && in_array($deal->status, ['active', 'overdue'], true);
    }

    private function schedule(Deal $deal, bool $lock = false): array
    {
        $query = $deal->installments()->where('status', '!=', 'paid');
        if ($lock) $query->lockForUpdate();
        return $query->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'cents' => (int) str_replace('.', '', (string) $row->amount),
            'due' => $row->due_date->format('Y-m-d'),
            'status' => $row->status,
            'receipt' => $row->receipt_document_id ? (int) $row->receipt_document_id : null,
        ])->all();
    }

    private function latest(Deal $deal): ?object
    {
        return DB::table('early_payoffs')->where('deal_id', $deal->id)->latest('id')->first();
    }

    private function responseFor(Deal $deal, bool $payer)
    {
        $schedule = $this->inPayment($deal) ? $this->schedule($deal) : [];
        $payoff = $this->latest($deal);
        if ($payoff) unset($payoff->schedule);
        return response()->json([
            'payer' => $payer,
            'can_request' => $payer && $schedule && !in_array($payoff?->status, self::OPEN, true)
                && !array_filter($schedule, fn ($row) => $row['status'] !== 'pending' || $row['receipt']),
            'balance_cents' => array_sum(array_column($schedule, 'cents')),
            'payoff' => $payoff,
        ]);
    }

    public function show(Request $request, Deal $deal)
    {
        return $this->responseFor($deal, $this->participant($request, $deal));
    }

    public function store(Request $request, Deal $deal, DealEventService $events)
    {
        abort_unless($this->participant($request, $deal), 403, 'Somente o pagador pode solicitar a quitação.');
        $data = $request->validate(['amount_cents' => ['required', 'integer', 'min:1', 'max:99999999999999']]);
        $amount = (int) $data['amount_cents'];

        DB::transaction(function () use ($deal, $amount, $events): void {
            $locked = Deal::whereKey($deal->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->inPayment($locked), 422, 'O acordo precisa estar formalizado e em pagamento.');
            abort_if(in_array($this->latest($locked)?->status, self::OPEN, true), 409, 'Já existe uma solicitação em andamento.');
            $schedule = $this->schedule($locked, true);
            $balance = array_sum(array_column($schedule, 'cents'));
            abort_if(!$balance || $amount > $balance, 422, 'Informe um valor entre R$ 0,01 e o saldo em aberto.');
            abort_if(array_filter($schedule, fn ($row) => $row['status'] !== 'pending' || $row['receipt']),
                409, 'Conclua a conferência dos comprovantes de parcelas antes de solicitar a quitação.');
            $id = DB::table('early_payoffs')->insertGetId([
                'deal_id'=>$locked->id, 'status'=>'requested', 'balance_cents'=>$balance,
                'proposed_cents'=>$amount, 'schedule'=>json_encode($schedule, JSON_THROW_ON_ERROR),
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            $events->record($locked, $locked->buyer_id, 'early_payoff_requested', [
                'payoff_id'=>$id, 'balance_cents'=>$balance, 'proposed_cents'=>$amount,
            ]);
            $events->notify($locked, $locked->seller_id, 'early_payoff_requested', 'Solicitação de quitação antecipada',
                'O pagador enviou uma proposta para quitar o saldo do acordo.', ['deal_id'=>$locked->id]);
        }, 3);

        return $this->responseFor($deal->fresh(), true);
    }

    public function respond(Request $request, Deal $deal, int $payoffId, DealEventService $events, DischargeTermService $terms)
    {
        $payer = $this->participant($request, $deal);
        $data = $request->validate([
            'action'=>['required','in:accept,full_amount,decline,cancel,submit_receipt,return_receipt,confirm_receipt'],
            'file_name'=>['required_if:action,submit_receipt','nullable','string','max:255'],
            'file_base64'=>['required_if:action,submit_receipt','nullable','string','max:13981100'],
        ]);
        $action = $data['action'];
        $receipt = $action === 'submit_receipt' ? $this->readReceipt($data) : null;

        DB::transaction(function () use ($deal, $payoffId, $payer, $action, $receipt, $events, $terms, $request): void {
            $locked = Deal::whereKey($deal->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->participant($request, $locked) === $payer, 403);
            $payoff = $this->latest($locked);
            abort_unless($payoff && (int) $payoff->id === $payoffId, 409, 'A solicitação mudou. Atualize a tela.');
            $state = $payoff->status;
            if ($state === 'confirmed' && !$payer && $action === 'confirm_receipt') return;
            abort_unless($this->inPayment($locked), 422, 'O acordo já saiu da fase de pagamento.');
            $agreed = $payoff->agreed_cents;
            $changes = [];
            $next = match ($action) {
                'accept' => (!$payer && $state === 'requested') || ($payer && $state === 'countered') ? 'accepted' : null,
                'full_amount' => !$payer && $state === 'requested' && $payoff->proposed_cents < $payoff->balance_cents ? 'countered' : null,
                'decline' => (!$payer && $state === 'requested') || ($payer && $state === 'countered') ? 'declined' : null,
                'cancel' => $payer && in_array($state, ['requested', 'countered', 'accepted'], true) && !$payoff->receipt_id ? 'cancelled' : null,
                'submit_receipt' => $payer && $state === 'accepted' ? 'receipt_submitted' : null,
                'return_receipt' => !$payer && $state === 'receipt_submitted' ? 'accepted' : null,
                'confirm_receipt' => !$payer && $state === 'receipt_submitted' ? 'confirmed' : null,
            };
            abort_unless($next, 403, 'Esta ação não está disponível para esta parte ou nesta etapa.');
            $schedule = [];
            if (!in_array($action, ['decline', 'cancel', 'return_receipt'], true)) {
                $schedule = $this->schedule($locked, true);
                abort_unless($schedule == json_decode($payoff->schedule, true, 512, JSON_THROW_ON_ERROR),
                    409, 'O saldo ou as parcelas mudaram. Cancele a solicitação e abra outra com o saldo atualizado.');
            }
            if ($action === 'accept') {
                $changes['agreed_cents'] = (int) ($payer ? $payoff->balance_cents : $payoff->proposed_cents);
            }
            if ($receipt) $changes['receipt_id'] = $this->saveReceipt($locked, $payoffId, $receipt);
            if ($action === 'confirm_receipt') {
                abort_unless($payoff->receipt_id && $agreed, 422, 'Envie o comprovante antes de confirmar a quitação.');
                $now = now();
                $locked->installments()->whereIn('id', array_column($schedule, 'id'))->update([
                    'status'=>'paid', 'paid_at'=>$now, 'external_payment_id'=>'early-payoff-'.$payoffId,
                ]);
                $locked->update(['status'=>'paid_off','paid_off_at'=>$now]);
                $term = $terms->generate($locked, (int) $locked->seller_id, [
                    'balance_cents'=>(int) $payoff->balance_cents, 'paid_cents'=>(int) $agreed,
                    'schedule_ids'=>array_column($schedule, 'id'),
                ]);
                $changes['term_id'] = $term['document_id'];
            }

            DB::table('early_payoffs')->where('id', $payoffId)->update([
                ...$changes, 'status'=>$next, 'updated_at'=>now(),
            ]);
            $events->record($locked, (int) $request->user()->id, 'early_payoff_'.$action, [
                'payoff_id'=>$payoffId, 'status'=>$next, 'agreed_cents'=>$changes['agreed_cents'] ?? $agreed,
                'discount_cents'=>$next === 'confirmed' ? (int) $payoff->balance_cents - (int) $agreed : null,
                ...array_intersect_key($changes, ['receipt_id'=>true, 'term_id'=>true]),
            ]);
            $events->notify($locked, $payer ? $locked->seller_id : $locked->buyer_id, 'early_payoff_'.$action,
                $next === 'confirmed' ? 'Acordo quitado' : 'Atualização na quitação antecipada',
                $next === 'confirmed' ? 'O recebimento foi confirmado e o termo de quitação está disponível.'
                    : 'Abra a negociação para acompanhar a solicitação de quitação.', ['deal_id'=>$locked->id]);
        }, 3);

        return $this->responseFor($deal->fresh(), $payer);
    }

    private function readReceipt(array $data): array
    {
        $encoded = preg_replace('/^data:[^;,]+;base64,/', '', $data['file_base64']);
        $binary = base64_decode($encoded, true);
        abort_if(!$binary || strlen($binary) > 10 * 1024 * 1024, 422, 'Arquivo inválido ou maior que 10 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $extensions = ['application/pdf'=>'pdf', 'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        abort_unless(isset($extensions[$mime]), 422, 'Use PDF, JPEG, PNG ou WebP.');
        return [$binary, $mime, $extensions[$mime]];
    }

    private function saveReceipt(Deal $deal, int $payoffId, array $receipt): int
    {
        [$binary, $mime, $extension] = $receipt;
        $hash = hash('sha256', $binary);
        return DB::table('deal_documents')->insertGetId([
            'deal_id'=>$deal->id, 'uploaded_by'=>$deal->buyer_id, 'type'=>'early_payoff_receipt',
            'storage_path'=>'deals/'.$deal->public_id.'/payoff/'.$payoffId.'/'.$hash,
            'original_name'=>'comprovante-quitacao.'.$extension, 'mime_type'=>$mime,
            'sha256'=>$hash, 'signed'=>false, 'content_blob'=>$binary, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }
}
