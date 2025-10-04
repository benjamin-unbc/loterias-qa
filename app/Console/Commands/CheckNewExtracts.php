<?php

namespace App\Console\Commands;

use App\Models\Number;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckNewExtracts extends Command
{
    protected $signature = 'extracts:check-new {--minutes=5 : Verificar extractos de los últimos X minutos}';
    protected $description = 'Verifica si hay nuevos extractos (números) insertados recientemente';

    public function handle()
    {
        $minutes = (int) $this->option('minutes');
        $since = Carbon::now()->subMinutes($minutes);
        
        $this->info("🔍 Verificando nuevos extractos de los últimos {$minutes} minutos...");
        $this->line("📅 Desde: {$since->format('H:i:s')}");
        $this->line('');
        
        // Buscar números insertados en los últimos X minutos
        $newNumbers = Number::where('created_at', '>=', $since)
            ->with(['city', 'extract'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        if ($newNumbers->count() > 0) {
            $this->info("✅ Se encontraron {$newNumbers->count()} nuevos extractos:");
            $this->line('');
            
            // Agrupar por ciudad y turno
            $grouped = $newNumbers->groupBy(function($number) {
                return $number->city->name . ' - ' . $number->extract->name;
            });
            
            foreach ($grouped as $group => $numbers) {
                $firstNumber = $numbers->first();
                $count = $numbers->count();
                $time = $firstNumber->created_at->format('H:i:s');
                
                $this->line("🎯 {$group}: {$count} números (último: {$time})");
                
                // Mostrar algunos números de ejemplo
                $sampleNumbers = $numbers->take(3)->pluck('value')->toArray();
                $this->line("   📊 Ejemplos: " . implode(', ', $sampleNumbers));
            }
            
            $this->line('');
            $this->info("📈 Total de nuevos números: {$newNumbers->count()}");
            
        } else {
            $this->warn("⚠️  No se encontraron nuevos extractos en los últimos {$minutes} minutos");
            $this->line("   El sistema no ha insertado números nuevos recientemente");
        }
        
        // Mostrar estadísticas generales
        $todayTotal = Number::whereDate('created_at', today())->count();
        $lastInsertion = Number::latest()->first();
        
        $this->line('');
        $this->info("📊 Estadísticas del día:");
        $this->line("  📋 Total números hoy: {$todayTotal}");
        
        if ($lastInsertion) {
            $this->line("  🕐 Última inserción: {$lastInsertion->created_at->format('H:i:s')}");
            $this->line("  📍 Ciudad: {$lastInsertion->city->name}");
            $this->line("  🎲 Turno: {$lastInsertion->extract->name}");
        }
    }
}
