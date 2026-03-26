<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApusModel;
use Illuminate\Support\Facades\DB;

class FixTimeApuInApus extends Command
{
    protected $signature = 'fix:time-apu {--date=} {--user-id=} {--dry-run}';
    protected $description = 'Corrige el campo timeApu en apuestas que tienen valor vacío, extrayendo el horario del código de lotería';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $dateFilter = $this->option('date');
        $userIdFilter = $this->option('user-id');
        
        $query = ApusModel::where(function($q) {
            $q->whereNull('timeApu')
              ->orWhere('timeApu', '')
              ->orWhere('timeApu', '00:00:00');
        });
        
        if ($dateFilter) {
            $query->whereDate('created_at', $dateFilter);
        }
        
        if ($userIdFilter) {
            $query->where('user_id', $userIdFilter);
        }
        
        $apus = $query->get();
        
        $this->info("Apuestas encontradas con timeApu vacío: " . $apus->count());
        
        if ($apus->isEmpty()) {
            $this->info("No hay apuestas para corregir.");
            return 0;
        }
        
        $updated = 0;
        $errors = 0;
        
        foreach ($apus as $apu) {
            $timeApu = $this->extractTimeFromLotteryCode($apu->lottery);
            
            if ($timeApu) {
                if ($isDryRun) {
                    $this->line("Ticket: {$apu->ticket} - Lottery: {$apu->lottery} - timeApu actual: '{$apu->timeApu}' - Nuevo: '{$timeApu}'");
                } else {
                    try {
                        $apu->timeApu = $timeApu;
                        $apu->save();
                        $updated++;
                    } catch (\Exception $e) {
                        $this->error("Error actualizando apuesta ID {$apu->id}: " . $e->getMessage());
                        $errors++;
                    }
                }
            } else {
                $this->warn("No se pudo extraer el horario del código: {$apu->lottery} (Ticket: {$apu->ticket})");
                $errors++;
            }
        }
        
        if ($isDryRun) {
            $this->info("DRY RUN: Se actualizarían {$apus->count()} apuestas.");
        } else {
            $this->info("Apuestas actualizadas: {$updated}");
            if ($errors > 0) {
                $this->warn("Errores: {$errors}");
            }
        }
        
        return 0;
    }
    
    /**
     * Extrae el horario del código de lotería
     * Ejemplos:
     * - NAC1800 -> 18:00
     * - COR2100 -> 21:00
     * - SFE1500 -> 15:00
     */
    private function extractTimeFromLotteryCode($lotteryCode): ?string
    {
        // Extraer los últimos 4 dígitos del código
        if (preg_match('/(\d{4})$/', $lotteryCode, $matches)) {
            $timeDigits = $matches[1];
            $hour = substr($timeDigits, 0, 2);
            $minute = substr($timeDigits, 2, 2);
            
            // Validar que sea un horario válido
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }
        
        // Si no se puede extraer del código, intentar mapeos especiales
        $specialMappings = [
            'ORO1800' => '18:00', // Montevideo 18:00
            'ORO1500' => '18:00', // Montevideo 15:00 se muestra como 18:00
        ];
        
        if (isset($specialMappings[$lotteryCode])) {
            return $specialMappings[$lotteryCode];
        }
        
        return null;
    }
}

