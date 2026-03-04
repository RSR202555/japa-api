<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Student\AnamnesisController;
use App\Http\Controllers\Student\ChatController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\GoalController;
use App\Http\Controllers\Student\MealController;
use App\Http\Controllers\Student\ProgressPhotoController;
use App\Http\Controllers\Upload\ImageUploadController;
use App\Http\Controllers\Webhook\InfinityPayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Japa Treinador
|--------------------------------------------------------------------------
| Todas as rotas são prefixadas com /api (definido em bootstrap/app.php)
*/

// ==================== ROTAS PÚBLICAS (sem auth) ====================
// Checkout pay-first
Route::post('checkout', [CheckoutController::class, 'initiate'])
    ->middleware('throttle:10,1');

// Planos — acessível publicamente para a landing/checkout
Route::get('plans', [PlanController::class, 'index'])
    ->middleware('throttle:60,1');

// ==================== AUTENTICAÇÃO ====================
Route::prefix('auth')->group(function () {
    // Ativação de conta após pagamento confirmado (token enviado por e-mail)
    Route::post('activate-account', [AuthController::class, 'activateAccount'])
        ->middleware('throttle:10,1');

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:' . env('RATE_LIMIT_LOGIN', 5) . ',15');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Cadastro direto: somente admin (uso interno/manual)
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('register', [AuthController::class, 'register']);
    });
});

// ==================== WEBHOOK (SEM AUTH — verificação via assinatura) ====================
Route::post('webhook/infinitypay', [InfinityPayController::class, 'handle'])
    ->name('webhook.infinitypay')
    ->middleware('throttle:60,1'); // limita volume de chamadas

// ==================== ROTAS AUTENTICADAS ====================
Route::middleware(['auth:sanctum', 'throttle:' . env('RATE_LIMIT_API', 60) . ',1'])->group(function () {

    // --- Upload de Imagens ---
    Route::post('upload/avatar', [ImageUploadController::class, 'avatar']);

    // ==================== ALUNO ====================
    Route::middleware(['student', 'subscription'])->group(function () {
        // Dashboard
        Route::get('student/dashboard', [DashboardController::class, 'index']);

        // Anamnese
        Route::prefix('student/anamnesis')->group(function () {
            Route::get('/', [AnamnesisController::class, 'show']);
            Route::post('/', [AnamnesisController::class, 'upsert']);
        });

        // Metas
        Route::apiResource('student/goals', GoalController::class);

        // Refeições
        Route::prefix('student/meals')->group(function () {
            Route::get('/', [MealController::class, 'index']);
            Route::post('/', [MealController::class, 'store']);
            Route::get('/summary/daily', [MealController::class, 'dailySummary']);
            Route::get('/{meal}', [MealController::class, 'show']);
            Route::delete('/{meal}', [MealController::class, 'destroy']);
        });

        // Fotos de Evolução
        Route::prefix('student/progress-photos')->group(function () {
            Route::get('/', [ProgressPhotoController::class, 'index']);
            Route::post('/', [ProgressPhotoController::class, 'store']);
            Route::delete('/{progressPhoto}', [ProgressPhotoController::class, 'destroy']);
        });

        // Chat
        Route::prefix('student/chat')->group(function () {
            Route::get('/', [ChatController::class, 'index']);
            Route::post('/send', [ChatController::class, 'send']);
            Route::get('/unread-count', [ChatController::class, 'unreadCount']);
        });
    });

    // ==================== ADMIN ====================
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        // Usuários
        Route::post('users', [UserController::class, 'store']);
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);
        Route::post('users/{user}/chat', [UserController::class, 'adminChat']);

        // Planos
        Route::apiResource('plans', PlanController::class);
    });

});
