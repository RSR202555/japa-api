<?php

use App\Services\SubscriptionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Schedule;

// Expira assinaturas vencidas — rodar a cada hora
Schedule::call(function () {
    app(SubscriptionService::class)->expireOverdue();
})->hourly()->name('expire-subscriptions')->withoutOverlapping();

// Limpeza de tokens expirados do Sanctum — rodar diariamente
Schedule::command('sanctum:prune-expired --hours=24')->daily();
