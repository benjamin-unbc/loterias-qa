<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GlobalQuinielasConfiguration;
use App\Models\City;

class ApplyDefaultQuinielasConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quinielas:apply-default-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aplica la configuración por defecto de quinielas (NAC, CHA, PRO, MZA, CTE, SFE, COR, RIO, ORO)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Aplicando configuración por defecto de quinielas...');

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

        // Obtener todas las ciudades con sus extractos (horarios)
        $cities = City::with('extract')
            ->orderBy('extract_id')
            ->orderBy('name')
            ->get();

        $citiesGrouped = $cities->groupBy('name');

        $updatedCount = 0;
        $createdCount = 0;

        // Crear o actualizar configuración para cada ciudad
        foreach ($citiesGrouped as $cityName => $cityData) {
            $schedules = $cityData->pluck('time')->unique()->sort()->values()->toArray();
            
            // Si la ciudad está en la lista de loterías por defecto, seleccionar todos sus horarios
            $selectedSchedules = in_array($cityName, $defaultSelectedLotteries) ? $schedules : [];

            $config = GlobalQuinielasConfiguration::updateOrCreate(
                ['city_name' => $cityName],
                ['selected_schedules' => $selectedSchedules]
            );

            if ($config->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $updatedCount++;
            }

            $status = in_array($cityName, $defaultSelectedLotteries) ? '✓ Seleccionada' : '✗ No seleccionada';
            $this->line("  {$cityName}: {$status} (" . count($selectedSchedules) . " horarios)");
        }

        $this->info("\nConfiguración aplicada exitosamente:");
        $this->info("  - Configuraciones creadas: {$createdCount}");
        $this->info("  - Configuraciones actualizadas: {$updatedCount}");
        $this->info("  - Total de ciudades procesadas: " . $citiesGrouped->count());
        
        $this->info("\nLoterías por defecto activas:");
        foreach ($defaultSelectedLotteries as $lottery) {
            $this->line("  - {$lottery}");
        }

        return Command::SUCCESS;
    }
}
