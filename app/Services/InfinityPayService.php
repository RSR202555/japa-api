<?php

namespace App\Services;

use App\Models\PendingRegistration;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfinityPayService
{
    private string $apiUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->apiUrl    = config('services.infinitypay.api_url', 'https://api.infinitepay.io/v3');
        $this->secretKey = config('services.infinitypay.secret_key');
    }

    /**
     * Gera link de pagamento / cobrança para um plano.
     * Inclui metadata com user_id e plan_id para identificação no webhook.
     */
    public function createCharge(User $user, Plan $plan): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secretKey}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post("{$this->apiUrl}/charges", [
            'amount'      => (int) ($plan->price * 100), // centavos
            'currency'    => 'BRL',
            'description' => "Japa Treinador - {$plan->name}",
            'customer'    => [
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'metadata'    => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
            'webhook_url' => route('webhook.infinitypay'),
        ]);

        if (! $response->successful()) {
            Log::error('Erro ao criar cobrança InfinityPay', [
                'user_id'  => $user->id,
                'plan_id'  => $plan->id,
                'response' => $response->json(),
            ]);

            throw new \RuntimeException('Falha ao criar cobrança. Tente novamente.');
        }

        $data = $response->json();

        Log::channel('transactions')->info('Cobrança criada', [
            'user_id'   => $user->id,
            'plan_id'   => $plan->id,
            'charge_id' => $data['id'] ?? null,
        ]);

        return [
            'charge_id'   => $data['id'],
            'payment_url' => $data['payment_url'] ?? $data['checkout_url'] ?? null,
            'status'      => $data['status'],
            'expires_at'  => $data['expires_at'] ?? null,
        ];
    }

    /**
     * Gera link de pagamento para novo cadastro (fluxo pay-first).
     * Metadata inclui type='new_registration' e pending_registration_id.
     */
    public function createChargeForPendingRegistration(PendingRegistration $pending, Plan $plan): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secretKey}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ])->post("{$this->apiUrl}/charges", [
            'amount'      => (int) ($plan->price * 100),
            'currency'    => 'BRL',
            'description' => "Japa Treinador - {$plan->name}",
            'customer'    => [
                'name'  => $pending->name,
                'email' => $pending->email,
                'phone' => $pending->phone,
            ],
            'metadata'    => [
                'type'                    => 'new_registration',
                'pending_registration_id' => $pending->id,
                'plan_id'                 => $plan->id,
            ],
            'webhook_url' => route('webhook.infinitypay'),
        ]);

        if (! $response->successful()) {
            Log::error('Erro ao criar cobrança (novo cadastro)', [
                'pending_id' => $pending->id,
                'plan_id'    => $plan->id,
                'response'   => $response->json(),
            ]);

            throw new \RuntimeException('Falha ao criar cobrança. Tente novamente.');
        }

        $data = $response->json();

        Log::channel('transactions')->info('Cobrança (novo cadastro) criada', [
            'pending_id' => $pending->id,
            'plan_id'    => $plan->id,
            'charge_id'  => $data['id'] ?? null,
        ]);

        return [
            'charge_id'   => $data['id'],
            'payment_url' => $data['payment_url'] ?? $data['checkout_url'] ?? null,
            'status'      => $data['status'],
            'expires_at'  => $data['expires_at'] ?? null,
        ];
    }

    /**
     * Consulta status de uma cobrança.
     */
    public function getCharge(string $chargeId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secretKey}",
            'Accept'        => 'application/json',
        ])->get("{$this->apiUrl}/charges/{$chargeId}");

        if (! $response->successful()) {
            throw new \RuntimeException("Falha ao consultar cobrança {$chargeId}.");
        }

        return $response->json();
    }

    /**
     * Cancela uma cobrança pendente.
     */
    public function cancelCharge(string $chargeId): bool
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secretKey}",
            'Accept'        => 'application/json',
        ])->delete("{$this->apiUrl}/charges/{$chargeId}");

        return $response->successful();
    }
}
