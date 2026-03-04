<?php

namespace App\Providers;

use App\Models\Goal;
use App\Models\Meal;
use App\Models\ProgressPhoto;
use App\Policies\GoalPolicy;
use App\Policies\MealPolicy;
use App\Policies\ProgressPhotoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Na Vercel, o filesystem é read-only exceto /tmp
        if (env('VERCEL')) {
            $this->configureVercelStorage();
        }
    }

    public function boot(): void
    {
        Gate::policy(Goal::class, GoalPolicy::class);
        Gate::policy(Meal::class, MealPolicy::class);
        Gate::policy(ProgressPhoto::class, ProgressPhotoPolicy::class);
    }

    /**
     * Redireciona storage para /tmp na Vercel (serverless).
     * Cria os diretórios necessários no primeiro request.
     */
    private function configureVercelStorage(): void
    {
        $storagePath = '/tmp/japa-storage';

        $dirs = [
            $storagePath . '/app/public',
            $storagePath . '/framework/cache/data',
            $storagePath . '/framework/sessions',
            $storagePath . '/framework/views',
            $storagePath . '/logs',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $this->app->useStoragePath($storagePath);
    }
}
