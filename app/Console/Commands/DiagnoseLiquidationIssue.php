<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApusModel;
use App\Models\PlaysSentModel;
use App\Models\User;
use Carbon\Carbon;

class DiagnoseLiquidationIssue extends Command
{
    protected $signature = 'diagnose:liquidation {user_id} {date}';
    protected $description = 'Diagnostica problemas con liquidaciones para un usuario y fecha específica';

    public function handle()
    {
        $userId = $this->argument('user_id');
        $dateStr = $this->argument('date');
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("Usuario con ID {$userId} no encontrado");
            return 1;
        }
        
        $this->info("Diagnosticando liquidación para usuario: {$user->email} (ID: {$userId})");
        $this->info("Fecha: {$dateStr}");
        $this->newLine();
        
        // Buscar todas las apuestas del usuario para esa fecha
        $apus = ApusModel::whereDate('created_at', $dateStr)
            ->where('user_id', $userId)
            ->get();
        
        $this->info("Total de apuestas encontradas: " . $apus->count());
        $this->newLine();
        
        if ($apus->isEmpty()) {
            $this->warn("No se encontraron apuestas para esta fecha.");
            $this->info("Buscando apuestas en fechas cercanas...");
            
            // Buscar en fechas cercanas
            $date = Carbon::parse($dateStr);
            for ($i = -3; $i <= 3; $i++) {
                $checkDate = $date->copy()->addDays($i);
                $count = ApusModel::whereDate('created_at', $checkDate->format('Y-m-d'))
                    ->where('user_id', $userId)
                    ->count();
                if ($count > 0) {
                    $this->info("  {$checkDate->format('Y-m-d')}: {$count} apuestas");
                }
            }
            return 0;
        }
        
        // Agrupar por timeApu
        $groupedByTime = $apus->groupBy('timeApu');
        
        $this->info("Apuestas agrupadas por timeApu:");
        foreach ($groupedByTime as $time => $apusGroup) {
            $total = $apusGroup->sum('import');
            $count = $apusGroup->count();
            $this->line("  timeApu: '{$time}' - {$count} apuestas - Total: " . number_format($total, 2));
        }
        
        $this->newLine();
        
        // Verificar tickets y status
        $tickets = $apus->pluck('ticket')->unique();
        $this->info("Tickets encontrados: " . $tickets->count());
        
        foreach ($tickets as $ticket) {
            $playsSent = PlaysSentModel::where('ticket', $ticket)->first();
            if ($playsSent) {
                $this->line("  Ticket: {$ticket} - Status: {$playsSent->status} - Fecha: {$playsSent->date}");
            }
        }
        
        $this->newLine();
        
        // Verificar apuestas con timeApu vacío o incorrecto
        $emptyTimeApu = $apus->filter(function($apu) {
            return empty($apu->timeApu) || !in_array($apu->timeApu, ['10:15', '12:00', '15:00', '18:00', '21:00']);
        });
        
        if ($emptyTimeApu->isNotEmpty()) {
            $this->warn("Apuestas con timeApu vacío o incorrecto: " . $emptyTimeApu->count());
            foreach ($emptyTimeApu->take(10) as $apu) {
                $this->line("  Ticket: {$apu->ticket} - Lottery: {$apu->lottery} - timeApu: '{$apu->timeApu}' - Import: {$apu->import}");
            }
        }
        
        $this->newLine();
        
        // Calcular totales por horario (como en liquidaciones)
        $previaTotalApus = (float) $apus->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus = (float) $apus->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) $apus->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus = (float) $apus->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus = (float) $apus->where('timeApu', '21:00')->sum('import');
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        $this->info("Totales calculados:");
        $this->line("  PREVIA (10:15): " . number_format($previaTotalApus, 2));
        $this->line("  MAÑANA (12:00): " . number_format($mananaTotalApus, 2));
        $this->line("  MATUTINA (15:00): " . number_format($matutinaTotalApus, 2));
        $this->line("  TARDE (18:00): " . number_format($tardeTotalApus, 2));
        $this->line("  NOCHE (21:00): " . number_format($nocheTotalApus, 2));
        $this->line("  TOTAL: " . number_format($totalApus, 2));
        
        return 0;
    }
}

