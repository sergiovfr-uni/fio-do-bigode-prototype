<?php

namespace App\Services;

use App\Models\Deal;
use Carbon\Carbon;

class InstallmentService
{
    public function generate(Deal $deal, ?Carbon $firstDueDate = null): void
    {
        if ($deal->installments()->exists()) return;

        $firstDueDate ??= $deal->first_due_date
            ? Carbon::parse($deal->first_due_date)->startOfDay()
            : now()->addMonthNoOverflow()->startOfDay();

        $principal = max(0, (float) $deal->total_amount - (float) $deal->down_payment);
        $count = max(1, (int) $deal->installments);
        $rate = max(0, (float) $deal->monthly_interest) / 100;

        if ($principal === 0.0) {
            $payment = 0.0;
        } elseif ($rate > 0) {
            $factor = pow(1 + $rate, $count);
            $payment = round($principal * (($rate * $factor) / ($factor - 1)), 2);
        } else {
            $payment = round($principal / $count, 2);
        }

        $financedTotal = round($payment * $count, 2);

        for ($number = 1; $number <= $count; $number++) {
            $amount = $number === $count
                ? round($financedTotal - ($payment * ($count - 1)), 2)
                : $payment;

            $deal->installments()->create([
                'number' => $number,
                'due_date' => $firstDueDate->copy()->addMonthsNoOverflow($number - 1),
                'amount' => max(0, $amount),
                'status' => 'pending',
            ]);
        }
    }
}
