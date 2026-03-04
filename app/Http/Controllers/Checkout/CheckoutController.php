<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\Plan;
use App\Models\User;
use App\Services\InfinityPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(private InfinityPayService $infinityPay) {}

    /**
     * Inicia o checkout para novos usuários (fluxo pay-first).
     *
     * Cria um registro pendente e retorna a URL de pagamento da InfinityPay.
     * O usuário só terá sua conta criada após a confirmação do pagamento via webhook.
     */
    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        // Bloquear se já tem conta ativa
        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'message' => 'Já existe uma conta com este e-mail. Faça login para acessar.',
                'redirect' => '/login',
            ], 409);
        }

        $plan = Plan::findOrFail($data['plan_id']);

        if (! $plan->is_active) {
            return response()->json(['message' => 'Plano indisponível.'], 422);
        }

        // Se já existe um pending não pago, reusa o link de pagamento existente
        $existing = PendingRegistration::where('email', $data['email'])
            ->where('status', 'pending')
            ->where('plan_id', $plan->id)
            ->latest()
            ->first();

        if ($existing && $existing->payment_url) {
            return response()->json([
                'message'     => 'Você já iniciou o processo. Use o link abaixo para pagar.',
                'payment_url' => $existing->payment_url,
            ]);
        }

        // Criar novo registro pendente
        $pending = PendingRegistration::create([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'phone'   => $data['phone'] ?? null,
            'plan_id' => $plan->id,
            'status'  => 'pending',
        ]);

        try {
            $charge = $this->infinityPay->createChargeForPendingRegistration($pending, $plan);

            $pending->update([
                'infinitypay_charge_id' => $charge['charge_id'],
                'payment_url'           => $charge['payment_url'],
            ]);

            Log::channel('transactions')->info('Checkout iniciado', [
                'pending_id' => $pending->id,
                'email'      => $pending->email,
                'plan'       => $plan->name,
                'charge_id'  => $charge['charge_id'],
            ]);

            return response()->json([
                'message'     => 'Checkout criado com sucesso.',
                'payment_url' => $charge['payment_url'],
            ]);
        } catch (\RuntimeException $e) {
            // Limpar registro em caso de falha na gateway
            $pending->delete();

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
