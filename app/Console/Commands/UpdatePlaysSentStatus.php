<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlaysSentModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdatePlaysSentStatus extends Command
{
    /**
     * El nombre y firma del comando.
     *
     * @var string
     */
    protected $signature = 'playssent:update-status';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Actualiza el estado de plays_sent de A a I si la hora actual es mayor que el campo timePlay';
    public $horarios = ['10:15', '12:00', '15:00', '18:00', '21:00'];

    /**
     * Ejecuta el comando.
     *
     * @return int
     */
    public function handle()
    {
        Log::info("Ejecutando comando playssent:update-status a las " . now());

        $timezone = 'America/Argentina/Buenos_Aires';
        $now   = Carbon::now($timezone);
        $today = Carbon::today($timezone);

        $this->info("=== Inicio de actualización ===");
        $this->info("Hora actual: " . $now->toDateTimeString());
        $this->info("Fecha actual: " . $today->toDateString());

        // ✅ OPTIMIZADO: Usar actualización masiva en lugar de iterar registro por registro
        // Esto reduce significativamente el tiempo de ejecución y bloqueos de BD
        $records = PlaysSentModel::whereDate('date', $today)
            ->where('statusPlay', 'A')
            ->get();

        if ($records->isEmpty()) {
            $this->info("No se encontraron registros con status 'A' para hoy.");
            return 0;
        }

        $updatedCount = 0;
        $ticketIdsToUpdate = [];

        foreach ($records as $record) {
            $timePlay = $record->timePlay;
            if (empty($timePlay)) {
                continue;
            }
            
            // Limpiar el formato si contiene comas (ej: '10:15,12:00' -> '10:15')
            if (strpos($timePlay, ',') !== false) {
                $timePlay = explode(',', $timePlay)[0];
            }
            
            // Validar formato de hora (HH:MM)
            if (!preg_match('/^\d{1,2}:\d{2}$/', trim($timePlay))) {
                Log::warning("Formato de hora inválido en plays_sent ID {$record->id}: '{$record->timePlay}'");
                continue;
            }
            
            try {
                $recordTime = Carbon::parse($timePlay, $timezone)
                    ->setDate($today->year, $today->month, $today->day);

                if ($now->greaterThan($recordTime)) {
                    $ticketIdsToUpdate[] = $record->id;
                }
            } catch (\Exception $e) {
                Log::error("Error al parsear hora en plays_sent ID {$record->id}: '{$record->timePlay}' - " . $e->getMessage());
                continue;
            }
        }

        // ✅ Actualización masiva en una sola consulta
        if (!empty($ticketIdsToUpdate)) {
            $updatedCount = PlaysSentModel::whereIn('id', $ticketIdsToUpdate)
                ->update(['statusPlay' => 'I']);
            $this->info("✅ Actualizados {$updatedCount} tickets a status 'I'.");
        } else {
            $this->info("No hay tickets que necesiten actualización.");
        }

        $this->info("=== Fin de la actualización ===");
        return 0;
    }
}
