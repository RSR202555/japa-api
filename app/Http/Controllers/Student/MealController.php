<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meal\StoreMealRequest;
use App\Models\Meal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $meals = Meal::where('user_id', $request->user()->id)
            ->when($request->query('date'), fn ($q) => $q->whereDate('logged_at', $request->query('date')))
            ->when($request->query('meal_time'), fn ($q) => $q->where('meal_time', $request->query('meal_time')))
            ->latest('logged_at')
            ->paginate(20);

        return response()->json($meals);
    }

    public function store(StoreMealRequest $request): JsonResponse
    {
        $meal = Meal::create([
            ...$request->validated(),
            'user_id'   => $request->user()->id,
            'logged_at' => $request->logged_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Refeição registrada com sucesso.',
            'meal'    => $meal,
        ], 201);
    }

    public function show(Request $request, Meal $meal): JsonResponse
    {
        $this->authorize('view', $meal);

        return response()->json($meal);
    }

    public function destroy(Request $request, Meal $meal): JsonResponse
    {
        $this->authorize('delete', $meal);

        $meal->delete();

        return response()->json(['message' => 'Registro removido.']);
    }

    /**
     * Resumo nutricional do dia.
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->query('date', today()->toDateString());

        $meals = Meal::where('user_id', $request->user()->id)
            ->whereDate('logged_at', $date)
            ->get();

        return response()->json([
            'date'           => $date,
            'total_meals'    => $meals->count(),
            'total_calories' => $meals->sum('total_calories'),
            'total_protein'  => $meals->sum('total_protein'),
            'total_carbs'    => $meals->sum('total_carbs'),
            'total_fat'      => $meals->sum('total_fat'),
        ]);
    }
}
