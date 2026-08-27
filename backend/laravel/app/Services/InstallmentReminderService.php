<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InstallmentReminderService
{
    public function dispatch(): int
    {
        $sent = 0;
        $today = today();
        DB::table('deals')
            ->where('status', 'active')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('installments')
                ->whereColumn('installments.deal_id', 'deals.id')
                ->where('installments.status', 'pending')
                ->whereDate('installments.due_date', '<', $today))
            ->update(['status' => 'overdue', 'updated_at' => now()]);
        $installments = DB::table('installments')
            ->join('deals', 'deals.id', '=', 'installments.deal_id')
            ->whereIn('deals.status', ['active', 'overdue'])
            ->whereIn('installments.status', ['pending', 'receipt_submitted'])
            ->whereBetween('installments.due_date', [$today->copy()->subDays(7), $today->copy()->addDay()])
            ->select('installments.*', 'deals.seller_id', 'deals.buyer_id', 'deals.title')
            ->get();

        foreach ($installments as $installment) {
            $days = (int) $today->diffInDays(\Carbon\Carbon::parse($installment->due_date)->startOfDay(), false);
            $kind = match ($days) { 1 => 'tomorrow', 0 => 'today', -1 => 'overdue', -3 => 'overdue_3', -7 => 'overdue_7', default => null };
            if (!$kind) continue;
            [$title, $buyerMessage, $sellerMessage] = match ($kind) {
                'tomorrow' => ['Parcela vence amanhã', 'A parcela '.$installment->number.' vence amanhã.', 'A parcela '.$installment->number.' do comprador vence amanhã.'],
                'today' => ['Parcela vence hoje', 'A parcela '.$installment->number.' vence hoje.', 'A parcela '.$installment->number.' do comprador vence hoje.'],
                'overdue' => ['Parcela vencida', 'A parcela '.$installment->number.' venceu ontem e segue sem confirmação.', 'A parcela '.$installment->number.' venceu ontem e segue sem confirmação.'],
                'overdue_3' => ['Regularize a parcela', 'A parcela '.$installment->number.' está vencida. Envie o comprovante ou proponha uma nova data.', 'A parcela '.$installment->number.' segue vencida. Você já pode acompanhar a regularização.'],
                'overdue_7' => ['Atraso persistente', 'A parcela '.$installment->number.' continua vencida. Regularize o pagamento ou proponha uma solução.', 'A parcela '.$installment->number.' continua vencida. Você pode emitir um aviso formal ou solicitar apoio.'],
            };
            foreach ([[$installment->buyer_id, $buyerMessage], [$installment->seller_id, $sellerMessage]] as [$userId, $message]) {
                $key = today()->format('Ymd').':'.$installment->id.':'.$kind.':'.$userId;
                $sent += DB::table('notifications')->insertOrIgnore([
                    'user_id'=>$userId,
                    'deal_id'=>$installment->deal_id,
                    'type'=>'installment_due_'.$kind,
                    'reminder_key'=>$key,
                    'title'=>$title,
                    'message'=>$message,
                    'data'=>json_encode(['deal_id'=>$installment->deal_id,'installment_id'=>$installment->id,'number'=>$installment->number]),
                    'created_at'=>now(),
                    'updated_at'=>now(),
                ]);
            }
        }
        return $sent;
    }
}
