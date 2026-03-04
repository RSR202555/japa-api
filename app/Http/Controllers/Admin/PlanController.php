<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::withCount('subscriptions')->latest()->get();
        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:100'],
            'slug'                  => ['required', 'string', 'max:100', 'unique:plans,slug'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'price'                 => ['required', 'numeric', 'min:0'],
            'duration_days'         => ['required', 'integer', 'min:1'],
            'features'              => ['nullable', 'array'],
            'features.*'            => ['string', 'max:150'],
            'is_active'             => ['boolean'],
            'infinitypay_plan_id'   => ['nullable', 'string', 'max:100'],
        ]);

        $data['name']        = strip_tags(trim($data['name']));
        $data['description'] = strip_tags(trim($data['description'] ?? ''));

        $plan = Plan::create($data);

        return response()->json([
            'message' => 'Plano criado com sucesso.',
            'plan'    => $plan,
        ], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'max:100'],
            'description'           => ['sometimes', 'nullable', 'string', 'max:1000'],
            'price'                 => ['sometimes', 'numeric', 'min:0'],
            'duration_days'         => ['sometimes', 'integer', 'min:1'],
            'features'              => ['sometimes', 'nullable', 'array'],
            'features.*'            => ['string', 'max:150'],
            'is_active'             => ['sometimes', 'boolean'],
            'infinitypay_plan_id'   => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $plan->update($data);

        return response()->json([
            'message' => 'Plano atualizado.',
            'plan'    => $plan->fresh(),
        ]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir um plano com assinaturas ativas.',
            ], 409);
        }

        $plan->delete();

        return response()->json(['message' => 'Plano removido.']);
    }
}
