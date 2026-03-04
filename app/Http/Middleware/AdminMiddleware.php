<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if (! $user->isAdmin()) {
            Log::channel('security')->warning('Tentativa de acesso admin negada', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'ip'      => $request->ip(),
                'url'     => $request->fullUrl(),
            ]);

            return response()->json(['message' => 'Acesso restrito a administradores.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Conta desativada.'], 403);
        }

        return $next($request);
    }
}
