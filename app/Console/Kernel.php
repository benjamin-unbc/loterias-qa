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
        $schedule->command('playssent:update-status')
                 ->everyMinute()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping();
                 
        $schedule->command('fetch:plays-sent')
                 ->everyMinute()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping();
        
        // Actualización automática cada 2 minutos (no cada 30 segundos)
        $schedule->command('lottery:auto-update')
                 ->everyTwoMinutes()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping()
                 ->runInBackground();
                 
        // Sistema de pagos cada 5 minutos
        $schedule->command('lottery:auto-payment')
                 ->everyFiveMinutes()
                 ->between('10:00', '23:59')
                 ->withoutOverlapping();
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