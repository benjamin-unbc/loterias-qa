<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GlobalQuinielasConfiguration;
use App\Models\City;
use Carbon\Carbon;

class GlobalQuinielasConfigurationSeeder extends Seeder
{
    /**
     * Ejecutar los seeds de la base de datos.
     *
     * @return void
     */
    public function run()
    {
        // Limpiar configuración existente
        GlobalQuinielasConfiguration::truncate();

        // Loterías que deben estar preseleccionadas por defecto
        $defaultSelectedLotteries = [
            'CIUDAD',      // NAC
            'CHACO',       // CHA
            'PROVINCIA',   // PRO
            'MENDOZA',     // MZA
            'CORRIENTES',  // CTE
            'SANTA FE',    // SFE
            'CORDOBA',     // COR
            'ENTRE RIOS',  // RIO
            'MONTEVIDEO'   // ORO
        ];

        $now = Carbon::now();

        // Obtener todas las ciudades con sus extractos (horarios)
        $cities = City::with('extract')
            ->orderBy('extract_id')
            ->orderBy('name')
            ->get();

        $citiesGrouped = $cities->groupBy('name');

        // Crear configuración para cada ciudad
        foreach ($citiesGrouped as $cityName => $cityData) {
            $schedules = $cityData->pluck('time')->unique()->sort()->values()->toArray();
            
            // Si la ciudad está en la lista de loterías por defecto, seleccionar todos sus horarios
            $selectedSchedules = in_array($cityName, $defaultSelectedLotteries) ? $schedules : [];

            GlobalQuinielasConfiguration::create([
                'city_name' => $cityName,
                'selected_schedules' => $selectedSchedules,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command->info('Configuración global de quinielas creada exitosamente.');
        $this->command->info('Loterías por defecto: ' . implode(', ', $defaultSelectedLotteries));
    }
}
