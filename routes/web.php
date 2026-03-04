<?php

use Illuminate\Support\Facades\Route;

// Backend serve apenas API — rota web apenas para health check
Route::get('/', fn () => response()->json([
    'service' => 'Japa Treinador API',
    'status'  => 'running',
    'version' => '1.0.0',
]));

Route::get('/health', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));
