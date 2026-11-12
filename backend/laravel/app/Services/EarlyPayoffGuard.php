<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Support\Facades\DB;

class EarlyPayoffGuard
{
    // Call inside the transaction, after acquiring the deal lock.
    public static function ensureScheduleEditable(Deal $deal): void
    {
        abort_if(DB::table('early_payoffs')->where('deal_id', $deal->id)
            ->whereIn('status', ['accepted', 'receipt_submitted'])->exists(),
            409, 'Há uma quitação antecipada aceita. Abra a quitação para acompanhar o pagamento.');
    }
}
