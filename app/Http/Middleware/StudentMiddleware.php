<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if (! $user->isStudent() && ! $user->isAdmin()) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Conta desativada.'], 403);
        }

        return $next($request);
    }
}
