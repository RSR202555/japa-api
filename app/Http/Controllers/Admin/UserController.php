<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'email'                  => ['required', 'email', 'unique:users,email'],
            'password'               => ['required', 'string', 'min:8'],
            'phone'                  => ['nullable', 'string', 'max:30'],
            'plan_id'                => ['nullable', 'integer', 'exists:plans,id'],
            'subscription_status'    => ['nullable', 'in:active,pending'],
            'subscription_starts_at' => ['nullable', 'date'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => $data['password'],
                'phone'    => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $user->assignRole('aluno');

            if (!empty($data['plan_id'])) {
                $plan      = Plan::findOrFail($data['plan_id']);
                $startsAt  = Carbon::parse($data['subscription_starts_at'] ?? now());
                $expiresAt = $startsAt->copy()->addDays($plan->duration_days);

                Subscription::create([
                    'user_id'    => $user->id,
                    'plan_id'    => $plan->id,
                    'status'     => $data['subscription_status'] ?? 'active',
                    'starts_at'  => $startsAt,
                    'expires_at' => $expiresAt,
                ]);
            }

            return $user;
        });

        return response()->json(
            new UserResource($user->load('subscription.plan')),
            201
        );
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::role('aluno')
            ->when($request->query('search'), function ($q) use ($request) {
                $search = '%' . addcslashes($request->query('search'), '%_') . '%';
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                      ->orWhere('email', 'like', $search);
                });
            })
            ->when($request->query('active'), fn ($q) => $q->where('is_active', $request->query('active') === 'true'))
            ->with(['subscription.plan'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(
            new UserResource($user->load(['subscription.plan', 'anamnesis', 'goals']))
        );
    }

    public function toggleActive(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Não é possível desativar um administrador.'], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message'   => $user->is_active ? 'Usuário ativado.' : 'Usuário desativado.',
            'is_active' => $user->is_active,
        ]);
    }

    public function adminChat(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $message = \App\Models\ChatMessage::create([
            'sender_id'   => $request->user()->id,
            'receiver_id' => $user->id,
            'content'     => strip_tags(trim($request->content)),
        ]);

        return response()->json([
            'message' => 'Mensagem enviada.',
            'data'    => $message,
        ], 201);
    }
}
