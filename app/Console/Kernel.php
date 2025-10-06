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
        $schedule->command('playssent:update-status')->everyMinute();
        $schedule->command('fetch:plays-sent')->everyMinute();
        
        // Actualización automática de números ganadores cada 30 segundos (24/7)
        $schedule->command('lottery:auto-update')
                 ->everyThirtySeconds()
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