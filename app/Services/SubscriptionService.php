<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Ativa ou renova a assinatura após confirmação de pagamento.
     * Executado somente via webhook verificado — nunca via request do frontend.
     */
    public function activate(User $user, int $planId, string $chargeId): Subscription
    {
        return DB::transaction(function () use ($user, $planId, $chargeId) {
            $plan = Plan::findOrFail($planId);

            // Cancela assinaturas anteriores ativas (evita duplicatas)
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $subscription = Subscription::create([
                'user_id'                => $user->id,
                'plan_id'                => $plan->id,
                'status'                 => 'active',
                'starts_at'              => now(),
                'expires_at'             => now()->addDays($plan->duration_days),
                'last_payment_at'        => now(),
                'next_payment_at'        => now()->addDays($plan->duration_days),
                'infinitypay_charge_id'  => $chargeId,
            ]);

            Log::info('Assinatura ativada', [
                'user_id'         => $user->id,
                'plan'            => $plan->name,
                'subscription_id' => $subscription->id,
                'expires_at'      => $subscription->expires_at,
            ]);

            return $subscription;
        });
    }

    /**
     * Cancela a assinatura de um usuário.
     */
    public function cancel(Subscription $subscription, string $reason = ''): void
    {
        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        Log::info('Assinatura cancelada', [
            'subscription_id' => $subscription->id,
            'user_id'         => $subscription->user_id,
            'reason'          => $reason,
        ]);
    }

    /**
     * Verifica e expira assinaturas vencidas (para rodar via scheduler).
     */
    public function expireOverdue(): int
    {
        $count = Subscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        if ($count > 0) {
            Log::info("Assinaturas expiradas: {$count}");
        }

        return $count;
    }
}
