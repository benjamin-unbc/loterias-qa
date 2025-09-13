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

        $records = PlaysSentModel::whereDate('date', $today)
            ->where('statusPlay', 'A')
            ->get();

        if ($records->isEmpty()) {
            $this->info("No se encontraron registros con status 'A' para hoy.");
        }

        foreach ($records as $record) {
            $timePlay = $record->timePlay;
            $recordTime = Carbon::parse($timePlay, $timezone)
                ->setDate($today->year, $today->month, $today->day);

            $this->info("Procesando ticket {$record->ticket} con timePlay {$timePlay} (recordTime: " . $recordTime->toDateTimeString() . ")");

            if ($now->greaterThan($recordTime)) {
                $record->statusPlay = 'I';
                $record->save();
                $this->info("Ticket {$record->ticket} actualizado a status 'I'.");
            } else {
                $this->info("Ticket {$record->ticket} no se actualiza porque timePlay (" . $recordTime->toDateTimeString() . ") aún no ha pasado.");
            }
        }

        $this->info("=== Fin de la actualización ===");
        return 0;
    }
}
