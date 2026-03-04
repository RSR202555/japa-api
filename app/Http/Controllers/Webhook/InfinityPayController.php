<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InfinityPayController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    /**
     * Endpoint de Webhook da Infinity Pay.
     * NUNCA protegido por auth — usa verificação de assinatura HMAC.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verificar assinatura HMAC do webhook
        if (! $this->verifySignature($request)) {
            Log::channel('security')->warning('Webhook InfinityPay: assinatura inválida', [
                'ip'      => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }

        // 2. Verificar IP de origem (whitelist InfinityPay)
        if (! $this->isAllowedIp($request->ip())) {
            Log::channel('webhooks')->warning('Webhook de IP não autorizado', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'IP não autorizado.'], 403);
        }

        $payload = $request->all();

        // 3. Log completo do webhook para auditoria
        Log::channel('webhooks')->info('Webhook InfinityPay recebido', [
            'event'  => $payload['event'] ?? 'unknown',
            'charge' => $payload['charge_id'] ?? null,
            'ip'     => $request->ip(),
        ]);

        // 4. Processar evento
        try {
            $this->processEvent($payload);
        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('Erro ao processar webhook', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Erro interno.'], 500);
        }

        return response()->json(['message' => 'ok'], 200);
    }

    private function verifySignature(Request $request): bool
    {
        $secret    = config('services.infinitypay.webhook_secret');
        $signature = $request->header('X-InfinityPay-Signature') ?? '';
        $payload   = $request->getContent();

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        // Comparação segura para prevenir timing attacks
        return hash_equals($expected, $signature);
    }

    /**
     * Whitelist de IPs autorizados da Infinity Pay.
     * Atualizar conforme documentação oficial da gateway.
     */
    private function isAllowedIp(string $ip): bool
    {
        // Em desenvolvimento, permitir localhost
        if (app()->isLocal()) {
            return true;
        }

        $allowedIps = explode(',', env('INFINITYPAY_ALLOWED_IPS', ''));

        if (empty(array_filter($allowedIps))) {
            return true; // se não configurado, permite (configurar em produção)
        }

        return in_array($ip, $allowedIps, true);
    }

    private function processEvent(array $payload): void
    {
        $event = $payload['event'] ?? '';

        match ($event) {
            'charge.paid'       => $this->handleChargePaid($payload),
            'charge.failed'     => $this->handleChargeFailed($payload),
            'charge.refunded'   => $this->handleChargeRefunded($payload),
            'subscription.cancelled' => $this->handleSubscriptionCancelled($payload),
            default => Log::channel('webhooks')->info("Evento não tratado: {$event}"),
        };
    }

    private function handleChargePaid(array $payload): void
    {
        $chargeId = $payload['charge_id'] ?? null;
        $amount   = $payload['amount'] ?? 0;
        $type     = $payload['metadata']['type'] ?? 'renewal';

        // Fluxo pay-first: pagamento de novo cadastro
        if ($type === 'new_registration') {
            $this->handleNewRegistrationPaid($payload, $chargeId, $amount);
            return;
        }

        // Fluxo padrão: renovação de assinatura de usuário existente
        $userId = $payload['metadata']['user_id'] ?? null;
        $planId = $payload['metadata']['plan_id'] ?? null;

        if (! $userId || ! $planId) {
            Log::channel('webhooks')->error('Metadata insuficiente no webhook paid', $payload);
            return;
        }

        $user = User::find($userId);
        if (! $user) {
            Log::channel('webhooks')->error("Usuário {$userId} não encontrado");
            return;
        }

        // Ativar/renovar assinatura
        $subscription = $this->subscriptionService->activate($user, $planId, $chargeId);

        // Registrar transação
        Transaction::create([
            'user_id'                    => $user->id,
            'subscription_id'            => $subscription->id,
            'infinitypay_transaction_id' => $payload['transaction_id'] ?? null,
            'infinitypay_charge_id'      => $chargeId,
            'amount'                     => $amount / 100,
            'status'                     => 'paid',
            'payment_method'             => $payload['payment_method'] ?? 'unknown',
            'paid_at'                    => now(),
            'metadata'                   => [
                'plan_id' => $planId,
                'event'   => 'charge.paid',
            ],
            'raw_payload' => json_encode($payload),
        ]);

        Log::channel('transactions')->info('Pagamento confirmado', [
            'user_id'      => $user->id,
            'charge_id'    => $chargeId,
            'amount'       => $amount / 100,
            'subscription' => $subscription->id,
        ]);
    }

    /**
     * Processa pagamento de novo cadastro (fluxo pay-first).
     * Marca o pending_registration como pago e gera token de ativação.
     */
    private function handleNewRegistrationPaid(array $payload, ?string $chargeId, int $amount): void
    {
        $pendingId = $payload['metadata']['pending_registration_id'] ?? null;

        if (! $pendingId) {
            Log::channel('webhooks')->error('pending_registration_id ausente no metadata', $payload);
            return;
        }

        $pending = PendingRegistration::find($pendingId);

        if (! $pending) {
            Log::channel('webhooks')->error("PendingRegistration {$pendingId} não encontrado");
            return;
        }

        if ($pending->status !== 'pending') {
            Log::channel('webhooks')->info("PendingRegistration {$pendingId} já processado (status: {$pending->status})");
            return;
        }

        // Gerar token de ativação único (expira em 48h)
        $activationToken = Str::random(64);

        $pending->update([
            'status'           => 'paid',
            'infinitypay_charge_id' => $chargeId,
            'activation_token' => $activationToken,
            'expires_at'       => now()->addHours(48),
        ]);

        Log::channel('transactions')->info('Novo cadastro pago — aguardando ativação', [
            'pending_id' => $pending->id,
            'email'      => $pending->email,
            'charge_id'  => $chargeId,
            'amount'     => $amount / 100,
        ]);

        // Enviar e-mail com link de ativação
        try {
            \Illuminate\Support\Facades\Mail::to($pending->email)
                ->send(new \App\Mail\AccountActivationMail($pending, $activationToken));
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar e-mail de ativação', [
                'pending_id' => $pending->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function handleChargeFailed(array $payload): void
    {
        $userId = $payload['metadata']['user_id'] ?? null;

        if ($userId) {
            Transaction::create([
                'user_id'               => $userId,
                'infinitypay_charge_id' => $payload['charge_id'] ?? null,
                'amount'                => ($payload['amount'] ?? 0) / 100,
                'status'                => 'failed',
                'payment_method'        => $payload['payment_method'] ?? 'unknown',
                'metadata'              => ['event' => 'charge.failed'],
                'raw_payload'           => json_encode($payload),
            ]);
        }

        Log::channel('transactions')->warning('Pagamento falhou', [
            'user_id'   => $userId,
            'charge_id' => $payload['charge_id'] ?? null,
        ]);
    }

    private function handleChargeRefunded(array $payload): void
    {
        $chargeId = $payload['charge_id'] ?? null;

        $transaction = Transaction::where('infinitypay_charge_id', $chargeId)->first();
        if ($transaction) {
            $transaction->update(['status' => 'refunded']);
        }

        Log::channel('transactions')->info('Reembolso processado', ['charge_id' => $chargeId]);
    }

    private function handleSubscriptionCancelled(array $payload): void
    {
        $subscriptionId = $payload['subscription_id'] ?? null;

        $subscription = Subscription::where('infinitypay_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $subscription->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Log::channel('transactions')->info('Assinatura cancelada', [
                'subscription_id' => $subscription->id,
                'user_id'         => $subscription->user_id,
            ]);
        }
    }
}
