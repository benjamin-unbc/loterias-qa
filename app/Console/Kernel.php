<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Solo durante horarios de lotería (10:00-23:59)
        // ✅ OPTIMIZADO: Ejecutar en segundo plano para no bloquear peticiones HTTP
        $schedule->command('playssent:update-status')
                 ->everyMinute()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        // ✅ OPTIMIZADO: Ejecutar en segundo plano y reducir frecuencia a cada 2 minutos
        // El procesamiento de resultados no necesita ejecutarse cada minuto
        $schedule->command('fetch:plays-sent')
                 ->everyTwoMinutes()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping()
                 ->runInBackground();
        
        // Actualización automática cada 5 minutos (optimizado para mejor rendimiento)
        $schedule->command('lottery:auto-update')
                 ->everyFiveMinutes()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        // Sistema de pagos cada 5 minutos
        // ✅ OPTIMIZADO: Ejecutar en segundo plano
        $schedule->command('lottery:auto-payment')
                 ->everyFiveMinutes()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        // ✅ NUEVO: Extracción automática de números cada hora con validación
        $schedule->command('lottery:auto-extract')
                 ->hourly()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}