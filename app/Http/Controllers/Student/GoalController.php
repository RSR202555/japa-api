<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Goal\StoreGoalRequest;
use App\Http\Requests\Goal\UpdateGoalRequest;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    /**
     * Lista metas do aluno autenticado (nunca retorna de outros usuários).
     */
    public function index(Request $request): JsonResponse
    {
        $goals = Goal::where('user_id', $request->user()->id)
            ->when($request->query('active'), fn ($q) => $q->where('is_active', true))
            ->when($request->query('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->latest()
            ->paginate(15);

        return response()->json($goals);
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = Goal::create([
            ...$request->validated(),
            'user_id'       => $request->user()->id, // owner sempre = usuário autenticado
            'current_value' => $request->current_value ?? 0,
            'is_active'     => true,
        ]);

        return response()->json([
            'message' => 'Meta criada com sucesso.',
            'goal'    => $goal,
        ], 201);
    }

    public function show(Request $request, Goal $goal): JsonResponse
    {
        // Policy verifica ownership
        $this->authorize('view', $goal);

        return response()->json($goal);
    }

    public function update(UpdateGoalRequest $request, Goal $goal): JsonResponse
    {
        $this->authorize('update', $goal);

        $data = $request->validated();

        // Verifica se meta foi atingida
        if (
            isset($data['current_value']) &&
            $data['current_value'] >= $goal->target_value &&
            ! $goal->achieved_at
        ) {
            $data['achieved_at'] = now();
        }

        $goal->update($data);

        return response()->json([
            'message' => 'Meta atualizada com sucesso.',
            'goal'    => $goal->fresh(),
        ]);
    }

    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return response()->json(['message' => 'Meta removida com sucesso.']);
    }
}
