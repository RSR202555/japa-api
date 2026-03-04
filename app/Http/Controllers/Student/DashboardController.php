<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Goal;
use App\Models\Meal;
use App\Models\ProgressPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->load(['subscription.plan', 'anamnesis']);

        $today = today();

        return response()->json([
            'user'              => new UserResource($user),
            'subscription'      => [
                'status'     => $user->subscription?->status,
                'plan'       => $user->subscription?->plan?->name,
                'expires_at' => $user->subscription?->expires_at?->toIso8601String(),
                'is_active'  => $user->hasActiveSubscription(),
            ],
            'anamnesis_done'    => ! is_null($user->anamnesis?->completed_at),
            'stats'             => [
                'active_goals'      => Goal::where('user_id', $user->id)->where('is_active', true)->count(),
                'achieved_goals'    => Goal::where('user_id', $user->id)->whereNotNull('achieved_at')->count(),
                'meals_today'       => Meal::where('user_id', $user->id)->whereDate('logged_at', $today)->count(),
                'calories_today'    => Meal::where('user_id', $user->id)->whereDate('logged_at', $today)->sum('total_calories'),
                'total_photos'      => ProgressPhoto::where('user_id', $user->id)->count(),
                'last_photo_date'   => ProgressPhoto::where('user_id', $user->id)->latest('taken_at')->value('taken_at'),
            ],
        ]);
    }
}
