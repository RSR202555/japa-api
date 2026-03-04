<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Anamnesis\StoreAnamnesisRequest;
use App\Models\Anamnesis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnamnesisController extends Controller
{
    /**
     * Retorna a anamnese do aluno autenticado.
     * Acessível somente após assinatura ativa.
     */
    public function show(Request $request): JsonResponse
    {
        $anamnesis = Anamnesis::where('user_id', $request->user()->id)->first();

        if (! $anamnesis) {
            return response()->json([
                'message'   => 'Anamnese não preenchida.',
                'completed' => false,
            ]);
        }

        return response()->json([
            'completed' => true,
            'anamnesis' => $anamnesis,
        ]);
    }

    /**
     * Cria ou atualiza a anamnese (apenas o próprio usuário).
     */
    public function upsert(StoreAnamnesisRequest $request): JsonResponse
    {
        $data = [
            ...$request->validated(),
            'user_id'      => $request->user()->id,
            'completed_at' => now(),
        ];

        $anamnesis = Anamnesis::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return response()->json([
            'message'   => 'Anamnese salva com sucesso.',
            'anamnesis' => $anamnesis,
        ], 200);
    }
}
