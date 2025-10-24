<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Extract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ListAllLotteries extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lottery:list-all {--format=table : Formato de salida (table, json, csv)}';

    /**
     * The console command description.
     */
    protected $description = 'Lista todas las loterías configuradas dinámicamente desde la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🔍 Listando todas las loterías configuradas dinámicamente...");
        $this->info("📅 Fecha: " . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        try {
            // Obtener todas las ciudades con sus extractos
            $cities = City::with('extract')
                ->orderBy('name')
                ->orderBy('extract_id')
                ->get();

            if ($cities->isEmpty()) {
                $this->warn("⚠️  No se encontraron loterías configuradas.");
                return;
            }

            $format = $this->option('format');
            
            switch ($format) {
                case 'json':
                    $this->outputJson($cities);
                    break;
                case 'csv':
                    $this->outputCsv($cities);
                    break;
                default:
                    $this->outputTable($cities);
                    break;
            }

            $this->newLine();
            $this->info("✅ Total de loterías encontradas: " . $cities->count());
            
            // Agrupar por ciudad para mostrar resumen
            $citiesByCity = $cities->groupBy('name');
            $this->newLine();
            $this->info("📊 Resumen por ciudad:");
            foreach ($citiesByCity as $cityName => $cityData) {
                $schedules = $cityData->pluck('extract.time')->sort()->values()->toArray();
                $this->line("  • {$cityName}: " . implode(', ', $schedules) . " (" . count($schedules) . " turnos)");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("ListAllLotteries - Error: " . $e->getMessage());
        }
    }

    /**
     * Output en formato tabla
     */
    private function outputTable($cities)
    {
        $headers = ['ID', 'Ciudad', 'Código', 'Horario', 'Extracto'];
        $rows = [];

        foreach ($cities as $city) {
            $rows[] = [
                $city->id,
                $city->name,
                $city->code,
                $city->time,
                $city->extract->name
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Output en formato JSON
     */
    private function outputJson($cities)
    {
        $data = $cities->map(function($city) {
            return [
                'id' => $city->id,
                'city_name' => $city->name,
                'city_code' => $city->code,
                'time' => $city->time,
                'extract_name' => $city->extract->name,
                'full_lottery_code' => $city->code
            ];
        });

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Output en formato CSV
     */
    private function outputCsv($cities)
    {
        $this->line("ID,Ciudad,Código,Horario,Extracto");
        
        foreach ($cities as $city) {
            $this->line("{$city->id},{$city->name},{$city->code},{$city->time},{$city->extract->name}");
        }
    }
}
