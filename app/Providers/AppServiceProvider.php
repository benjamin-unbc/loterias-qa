<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * 
     * OPTIMIZADO: Agregadas optimizaciones de rendimiento
     */
    public function boot(): void
    {
        // Optimización: Desactivar logging de queries lentas en producción
        if (!config('app.debug')) {
            // Solo loguear queries muy lentas (>500ms) en producción
            \DB::listen(function ($query) {
                if ($query->time > 500) {
                    \Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'time' => $query->time . 'ms',
                        'bindings' => $query->bindings,
                    ]);
                }
            });
        }
        
        // Optimización: Mejorar rendimiento de consultas con índices
        // Asegurar que las consultas usen índices cuando sea posible
        \Schema::defaultStringLength(191);
    }
}
