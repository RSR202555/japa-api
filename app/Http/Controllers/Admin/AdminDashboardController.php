<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $now = now();

        return response()->json([
            'students' => [
                'total'        => User::role('aluno')->count(),
                'active'       => User::role('aluno')->where('is_active', true)->count(),
                'new_this_month' => User::role('aluno')
                    ->whereMonth('created_at', $now->month)
                    ->whereYear('created_at', $now->year)
                    ->count(),
            ],
            'subscriptions' => [
                'active'   => Subscription::where('status', 'active')->where('expires_at', '>', $now)->count(),
                'expired'  => Subscription::where('status', 'expired')->count(),
                'pending'  => Subscription::where('status', 'pending')->count(),
                'cancelled' => Subscription::where('status', 'cancelled')->count(),
            ],
            'revenue' => [
                'this_month' => Transaction::where('status', 'paid')
                    ->whereMonth('paid_at', $now->month)
                    ->whereYear('paid_at', $now->year)
                    ->sum('amount'),
                'last_month' => Transaction::where('status', 'paid')
                    ->whereMonth('paid_at', $now->subMonth()->month)
                    ->whereYear('paid_at', $now->year)
                    ->sum('amount'),
                'total' => Transaction::where('status', 'paid')->sum('amount'),
            ],
            'recent_transactions' => Transaction::with(['user', 'subscription.plan'])
                ->where('status', 'paid')
                ->latest('paid_at')
                ->take(10)
                ->get(),
        ]);
    }
}
