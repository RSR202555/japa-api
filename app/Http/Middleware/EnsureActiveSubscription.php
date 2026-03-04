<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Garante que o aluno possui assinatura ativa antes de acessar recursos premium.
     * Admins ignoram essa verificação.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        // Admin sempre tem acesso
        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! $user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Assinatura inativa ou expirada.',
                'code'    => 'SUBSCRIPTION_REQUIRED',
            ], 402);
        }

        return $next($request);
    }
}
