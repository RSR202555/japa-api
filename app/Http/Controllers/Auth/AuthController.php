<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registro de novo usuário.
     * Rate limit: 3 tentativas por IP por hora.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $key = 'register:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, env('RATE_LIMIT_REGISTER', 3))) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Muitos cadastros. Tente novamente em {$seconds} segundos.",
            ], 429);
        }

        RateLimiter::hit($key, 3600);

        $user = User::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => Hash::make($request->password, ['rounds' => 12]),
            'phone'         => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'is_active'     => true,
        ]);

        // Atribui role de aluno por padrão
        $user->assignRole('aluno');

        Log::channel('security')->info('Novo usuário registrado', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'ip'      => $request->ip(),
        ]);

        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addMinutes(config('sanctum.expiration', 60))
        );

        return response()->json([
            'message' => 'Conta criada com sucesso.',
            'user'    => new UserResource($user),
            'token'   => $token->plainTextToken,
        ], 201);
    }

    /**
     * Login com proteção contra brute force.
     * Rate limit: 5 tentativas por e-mail/IP por 15 min.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:' . $request->ip() . ':' . $request->email;
        $maxAttempts = env('RATE_LIMIT_LOGIN', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            Log::channel('security')->warning('Brute force detectado no login', [
                'email' => $request->email,
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'message' => "Conta temporariamente bloqueada. Tente em {$seconds} segundos.",
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 900); // bloqueia por 15 minutos após falha

            Log::channel('security')->warning('Tentativa de login falhou', [
                'email' => $request->email,
                'ip'    => $request->ip(),
            ]);

            // Mensagem genérica — não revela se e-mail existe
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Conta desativada. Entre em contato com o suporte.'], 403);
        }

        // Login bem-sucedido: limpar tentativas
        RateLimiter::clear($key);

        // Revogar tokens antigos (opcional — segurança)
        $user->tokens()->where('name', 'auth_token')->delete();

        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addMinutes(config('sanctum.expiration', 60))
        );

        Log::channel('security')->info('Login realizado', [
            'user_id' => $user->id,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'user'    => new UserResource($user->load(['subscription.plan'])),
            'token'   => $token->plainTextToken,
        ]);
    }

    /**
     * Logout — revoga o token atual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        Log::channel('security')->info('Logout realizado', [
            'user_id' => $request->user()->id,
            'ip'      => $request->ip(),
        ]);

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    /**
     * Retorna dados do usuário autenticado.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load(['subscription.plan'])),
        ]);
    }

    /**
     * Ativação de conta após pagamento confirmado.
     * O usuário define sua senha usando o token enviado por e-mail.
     */
    public function activateAccount(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $pending = PendingRegistration::where('activation_token', $request->token)
            ->where('status', 'paid')
            ->first();

        if (! $pending) {
            return response()->json([
                'message' => 'Link de ativação inválido ou já utilizado.',
            ], 422);
        }

        if ($pending->isActivationExpired()) {
            $pending->update(['status' => 'expired']);
            return response()->json([
                'message' => 'Link de ativação expirado. Entre em contato com o suporte.',
            ], 422);
        }

        // Verificação extra: garantir que não criamos conta duplicada
        if (User::where('email', $pending->email)->exists()) {
            $pending->update(['status' => 'activated']);
            return response()->json([
                'message' => 'Conta já ativada. Faça login para acessar.',
                'redirect' => '/login',
            ], 409);
        }

        $user = DB::transaction(function () use ($pending, $request) {
            $user = User::create([
                'name'      => $pending->name,
                'email'     => $pending->email,
                'phone'     => $pending->phone,
                'password'  => Hash::make($request->password, ['rounds' => 12]),
                'is_active' => true,
            ]);

            $user->assignRole('aluno');

            // Marcar o pending como ativado
            $pending->update(['status' => 'activated']);

            return $user;
        });

        Log::channel('security')->info('Conta ativada via token', [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'pending_id' => $pending->id,
            'ip'         => $request->ip(),
        ]);

        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addMinutes(config('sanctum.expiration', 60))
        );

        return response()->json([
            'message' => 'Conta ativada com sucesso! Bem-vindo(a) ao Japa Treinador.',
            'user'    => new UserResource($user),
            'token'   => $token->plainTextToken,
        ], 201);
    }

    /**
     * Renova o token de acesso.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addMinutes(config('sanctum.expiration', 60))
        );

        return response()->json([
            'token' => $token->plainTextToken,
        ]);
    }
}
