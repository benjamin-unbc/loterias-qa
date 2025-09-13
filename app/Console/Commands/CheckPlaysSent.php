<?php

namespace App\Console\Commands;

use App\Models\ApusModel;
use App\Models\PlaysSentModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log; // Importar el facade Log

class CheckPlaysSent extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'playssent:check';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Revisa cada 3 minutos los registros de playssent y busca tickets en la tabla apus';

    /**
     * Ejecuta el comando.
     *
     * @return int
     */
    public function handle()
    {
        Log::info("Ejecutando comando playssent:check a las " . now());

        // Obtener todos los registros de playssent (puedes aplicar filtros si es necesario)
        $playssentRecords = PlaysSentModel::all();

        foreach ($playssentRecords as $playssent) {
            // Suponiendo que el campo que almacena el ticket se llama "ticket"
            $ticket = $playssent->ticket;

            // Buscar en la tabla apus los registros que tengan el mismo ticket
            $apusRecords = ApusModel::where('ticket', $ticket)->get();

            // Procesa cada registro encontrado según tu lógica de negocio
            foreach ($apusRecords as $apus) {
                // Por ejemplo, puedes imprimir un log o realizar alguna actualización:
                $this->info("Procesado ticket {$ticket} en apus (ID: {$apus->id})");
                // Aquí puedes incluir la lógica necesaria para cada registro
            }
        }

        return 0;
    }
}
