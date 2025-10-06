<?php

namespace App\Console\Commands;

use App\Models\Number;
use App\Services\WinningNumbersService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExtractionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica el estado del sistema de extracción automática';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando estado del sistema de extracción...');
        $this->line('');
        
        // Verificar fecha actual
        $today = Carbon::today();
        $this->info("📅 Fecha actual: {$today->format('Y-m-d')}");
        
        // Verificar si estamos en horario de funcionamiento
        $currentTime = Carbon::now();
        $isWorkingHour = $this->isWorkingHour($currentTime);
        
        if ($isWorkingHour) {
            $this->info('✅ Estamos en horario de funcionamiento (10:30 - 23:59)');
        } else {
            $this->warn('⚠️  Fuera de horario de funcionamiento');
        }
        
        $this->line('');
        
        // Verificar números extraídos hoy
        $numbersToday = Number::whereDate('created_at', $today)->count();
        $this->info("📊 Números extraídos hoy: {$numbersToday}");
        
        // Verificar por turno (extract_id)
        $extracts = \App\Models\Extract::all();
        $this->line('');
        $this->info('🎲 Números por turno:');
        
        foreach ($extracts as $extract) {
            $count = Number::whereDate('created_at', $today)
                          ->where('extract_id', $extract->id)
                          ->count();
            $status = $count > 0 ? '✅' : '❌';
            $this->line("  {$status} {$extract->name}: {$count} números");
        }
        
        // Verificar por ciudad
        $this->line('');
        $this->info('🏙️  Números por ciudad:');
        
        $cities = \App\Models\City::all();
        
        foreach ($cities as $city) {
            $count = Number::whereDate('created_at', $today)
                          ->where('city_id', $city->id)
                          ->count();
            $status = $count > 0 ? '✅' : '❌';
            $this->line("  {$status} {$city->name}: {$count} números");
        }
        
        // Verificar última extracción
        $lastExtraction = Number::whereDate('created_at', $today)
                               ->orderBy('created_at', 'desc')
                               ->first();
        
        if ($lastExtraction) {
            $this->line('');
            $this->info("🕐 Última extracción: {$lastExtraction->created_at->format('H:i:s')}");
            $this->info("📍 Ciudad: {$lastExtraction->city->name}");
            $this->info("🎲 Turno: {$lastExtraction->extract->name}");
        } else {
            $this->line('');
            $this->warn('⚠️  No se han extraído números hoy');
        }
        
        // Verificar scheduler
        $this->line('');
        $this->info('⚙️  Estado del scheduler:');
        $this->line('  - Comando: lottery:auto-update');
        $this->line('  - Frecuencia: cada 30 segundos');
        $this->line('  - Horario: 10:30 - 23:59');
        $this->line('  - Sin solapamiento: activado');
        $this->line('  - En segundo plano: activado');
        
        // Recomendaciones
        $this->line('');
        $this->info('💡 Recomendaciones:');
        
        if (!$isWorkingHour) {
            $this->line('  - El sistema está fuera de horario, es normal que no extraiga');
        } elseif ($numbersToday == 0) {
            $this->line('  - No hay números extraídos hoy, ejecuta: php artisan lottery:auto-update --force');
        } elseif ($numbersToday < 100) {
            $this->line('  - Pocos números extraídos, verifica la conexión a vivitusuerte.com');
        } else {
            $this->line('  - El sistema está funcionando correctamente');
        }
        
        $this->line('');
        $this->info('🔧 Comandos útiles:');
        $this->line('  - php artisan lottery:auto-update --force (extracción manual)');
        $this->line('  - php artisan schedule:run (ejecutar scheduler manualmente)');
        $this->line('  - php artisan schedule:list (ver tareas programadas)');
    }
    
    /**
     * Verifica si estamos en horario de funcionamiento
     */
    private function isWorkingHour(Carbon $time): bool
    {
        $hour = $time->hour;
        $minute = $time->minute;
        $timeInMinutes = $hour * 60 + $minute;
        
        // Horario: 10:30 (630 minutos) a 23:59 (1439 minutos)
        return $timeInMinutes >= 630 && $timeInMinutes <= 1439;
    }
}
