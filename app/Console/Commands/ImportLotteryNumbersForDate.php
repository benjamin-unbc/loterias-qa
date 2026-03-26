<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Number;
use App\Services\WinningNumbersService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportLotteryNumbersForDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lottery:import-date 
                            {date : Fecha objetivo en formato YYYY-MM-DD} 
                            {--city=* : Procesar solo las ciudades especificadas (puede repetirse)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa números ganadores desde vivitusuerte.com para la fecha indicada';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $targetDate = Carbon::parse($this->argument('date'))->toDateString();
        } catch (\Exception $e) {
            $this->error('Formato de fecha inválido. Usa YYYY-MM-DD.');
            return Command::FAILURE;
        }

        $this->info("🗓️  Importando números para la fecha: {$targetDate}");

        $citiesOption = array_filter($this->option('city') ?? []);

        $winningNumbersService = (new WinningNumbersService())->setForcedDate($targetDate);
        $cities = !empty($citiesOption) ? $citiesOption : $winningNumbersService->getAvailableCities();

        if (empty($cities)) {
            $this->warn('No se encontraron ciudades para procesar.');
            return Command::SUCCESS;
        }

        $totalInserted = 0;
        $totalUpdated = 0;
        $errors = [];

        foreach ($cities as $cityName) {
            $this->line("📍 Procesando: {$cityName}");

            try {
                $cityData = $winningNumbersService->extractWinningNumbers($cityName);

                if (!$cityData) {
                    $this->warn("⚠️  No se obtuvieron datos para {$cityName}");
                    continue;
                }

                if (!empty($cityData['skipped'])) {
                    $this->line("⏭️  {$cityName}: Saltado ({$cityData['reason']})");
                    continue;
                }

                if (empty($cityData['turns'])) {
                    $this->warn("⚠️  No se encontraron turnos para {$cityName}");
                    continue;
                }

                foreach ($cityData['turns'] as $turnName => $numbers) {
                    if (empty($numbers)) {
                        continue;
                    }

                    $result = $this->insertCityNumbersToDatabase($cityName, $turnName, $numbers, $targetDate);
                    $totalInserted += $result['inserted'];
                    $totalUpdated += $result['updated'];

                    if ($result['inserted'] > 0 || $result['updated'] > 0) {
                        $this->info("  ✅ {$turnName}: " . count($numbers) . " números procesados");
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Error en {$cityName}: " . $e->getMessage();
                Log::error("ImportLotteryNumbersForDate - Error en {$cityName}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("❌ Error en {$cityName}: " . $e->getMessage());
            }
        }

        $this->info('----------------------------------------');
        $this->info("📊 Números insertados: {$totalInserted}");
        $this->info("🔄 Números actualizados: {$totalUpdated}");

        if (!empty($errors)) {
            $this->error('❌ Errores encontrados:');
            foreach ($errors as $error) {
                $this->error('  - ' . $error);
            }
        }

        $this->info('✅ Importación finalizada.');
        return Command::SUCCESS;
    }

    /**
     * Inserta números de una ciudad en la base de datos (reutiliza la lógica actual)
     */
    private function insertCityNumbersToDatabase($cityName, $turnName, $numbers, $date): array
    {
        $inserted = 0;
        $updated = 0;

        try {
            // Mapear nombres de ciudades a códigos de BD (maneja tanto mayúsculas como formato correcto)
            $cityMapping = [
                'CIUDAD' => 'NAC',
                'Ciudad' => 'NAC',
                'SANTA FE' => 'SFE',
                'Santa Fé' => 'SFE',
                'PROVINCIA' => 'PRO',
                'Provincia' => 'PRO',
                'ENTRE RIOS' => 'RIO',
                'Entre Ríos' => 'RIO',
                'CORDOBA' => 'COR',
                'Córdoba' => 'COR',
                'CORRIENTES' => 'CTE',
                'Corrientes' => 'CTE',
                'CHACO' => 'CHA',
                'Chaco' => 'CHA',
                'NEUQUEN' => 'NQN',
                'Neuquén' => 'NQN',
                'MISIONES' => 'MIS',
                'Misiones' => 'MIS',
                'MENDOZA' => 'MZA',
                'Mendoza' => 'MZA',
                'RÍO NEGRO' => 'Rio',
                'Río Negro' => 'Rio',
                'TUCUMAN' => 'Tucu',
                'Tucuman' => 'Tucu',
                'Tucumán' => 'Tucu',
                'SANTIAGO' => 'San',
                'Santiago' => 'San',
                'JUJUY' => 'JUJ',
                'Jujuy' => 'JUJ',
                'SALTA' => 'Salt',
                'Salta' => 'Salt',
                'MONTEVIDEO' => 'ORO',
                'Montevideo' => 'ORO',
                'SAN LUIS' => 'SLU',
                'San Luis' => 'SLU',
                'CHUBUT' => 'CHU',
                'Chubut' => 'CHU',
                'FORMOSA' => 'FOR',
                'Formosa' => 'FOR',
                'CATAMARCA' => 'CAT',
                'Catamarca' => 'CAT',
                'SAN JUAN' => 'SJU',
                'San Juan' => 'SJU'
            ];

            // Mapear nombres de turnos a extract_id
            $turnMapping = [
                'La Previa' => 1,
                'Primera' => 2,
                'Matutina' => 3,
                'Vespertina' => 4,
                'Nocturna' => 5
            ];

            // Mapeo especial para Montevideo
            if ($cityName === 'Montevideo') {
                $turnMapping['Matutina'] = 4; // Matutina de Montevideo va a Vespertina (extract_id 4)
            }

            $cityCode = $cityMapping[$cityName] ?? null;
            $extractId = $turnMapping[$turnName] ?? null;

            if (!$cityCode || !$extractId) {
                Log::warning("No se encontró mapeo para: {$cityName} - {$turnName}");
                return ['inserted' => 0, 'updated' => 0];
            }

            // Buscar la ciudad en la BD
            $city = City::where('code', 'LIKE', $cityCode . '%')
                        ->where('extract_id', $extractId)
                        ->first();

            if (!$city) {
                Log::warning("No se encontró ciudad en BD: {$cityCode} - extract_id: {$extractId}");
                return ['inserted' => 0, 'updated' => 0];
            }

            // Insertar cada número
            foreach ($numbers as $index => $number) {
                $position = $index + 1; // Las posiciones van de 1 a 20

                // NUEVA LÓGICA: Solo insertar el número 1 (cabeza) cuando estén los 20 números completos
                if ($position === 1) {
                    // Para el número 1, solo lo insertamos si ya tenemos los 20 números
                    $currentCount = Number::where('city_id', $city->id)
                                         ->where('date', $date)
                                         ->count();

                    if ($currentCount < 19) {
                        // Aún no tenemos suficientes números, saltamos el número 1
                        continue;
                    }
                }

                // Verificar si ya existe
                $existingNumber = Number::where('city_id', $city->id)
                                      ->where('index', $position)
                                      ->where('date', $date)
                                      ->first();

                if ($existingNumber) {
                    // Actualizar si el número cambió
                    if ($existingNumber->value !== $number) {
                        $existingNumber->value = $number;
                        $existingNumber->save();
                        $updated++;
                    }
                } else {
                    // Crear nuevo número
                    Number::create([
                        'city_id' => $city->id,
                        'extract_id' => $extractId,
                        'date' => $date,
                        'index' => $position,
                        'value' => $number
                    ]);
                    $inserted++;
                }
            }
        } catch (\Exception $e) {
            Log::error("Error insertando números para {$cityName} - {$turnName}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}

