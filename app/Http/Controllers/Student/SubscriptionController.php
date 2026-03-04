<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\InfinityPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(private InfinityPayService $infinityPay) {}

    /**
     * Lista planos disponíveis.
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->get();
        return response()->json($plans);
    }

    /**
     * Inicia o processo de assinatura.
     * Gera link de pagamento na InfinityPay.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        if (! $plan->is_active) {
            return response()->json(['message' => 'Plano indisponível.'], 422);
        }

        $user = $request->user();

        // Verifica se já tem assinatura ativa
        if ($user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Você já possui uma assinatura ativa.',
            ], 409);
        }

        try {
            $charge = $this->infinityPay->createCharge($user, $plan);

            return response()->json([
                'message'     => 'Cobrança criada com sucesso.',
                'charge_id'   => $charge['charge_id'],
                'payment_url' => $charge['payment_url'],
                'expires_at'  => $charge['expires_at'],
            ]);
        } catch (\RuntimeException $e) {
            Log::error('Erro ao criar assinatura', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao processar pagamento. Tente novamente.'], 500);
        }
    }

    /**
     * Status da assinatura atual.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user()->load('subscription.plan');

        return response()->json([
            'has_subscription' => ! is_null($user->subscription),
            'is_active'        => $user->hasActiveSubscription(),
            'subscription'     => $user->subscription ? [
                'status'     => $user->subscription->status,
                'plan'       => $user->subscription->plan?->name,
                'expires_at' => $user->subscription->expires_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
