<?php

use App\Services\InstallmentReminderService;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(InstallmentReminderService::class)->dispatch())
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();
