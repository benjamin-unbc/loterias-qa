<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\PlaysSentModel;
use App\Models\Result; // Usar el modelo correcto 'Result'
use App\Models\ApusModel;
use App\Models\Number;
use App\Models\City;
use App\Services\WinningNumbersService;
use App\Services\ResultManager;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log; // Necesario para Log::info

class Results extends Component
{
    use WithPagination;

    #[Layout('layouts.app')]
    public int $cant = 55;

    public ?string $date = null;

    public function render()
    {
        if (!$this->date) {
            $this->date = date('Y-m-d');
        }
        Log::info("Results - Renderizando para fecha: {$this->date}");

        $user = auth()->user();

        // --- CÁLCULO DE TOTALES ---

        // 1. Total Recaudado (Debe ser igual al de Jugadas Enviadas)
        // Se calcula desde PlaysSentModel sumando el 'amount' total de la jugada.
        $playsSentQuery = PlaysSentModel::query()->whereDate('date', $this->date)->where('status', '!=', 'I');
        // Todos los usuarios solo ven sus propios resultados
        $playsSentQuery->where('user_id', $user->id);
        $totalImporte = (float) $playsSentQuery->sum('amount');

        // 2. Total Aciertos (Suma de los premios a pagar)
        // Se calcula desde la tabla de resultados (Result) sumando la columna 'aciert'.
        $resultsQueryForTotals = Result::query()->whereDate('date', $this->date);
        // Todos los usuarios solo ven sus propios resultados
        $resultsQueryForTotals->where('user_id', $user->id);
        $allResults = $resultsQueryForTotals->get();
        
        // Filtrar resultados según la configuración de quinielas
        $filteredResults = $this->filterResultsByQuinielasConfig($allResults);
        $totalAciertos = (float) $filteredResults->sum('aciert');

        // --- CONSULTA PARA LA TABLA PAGINADA ---
        // ✅ MODIFICADO: Mostrar resultados separados por lotería (sin agrupar)
        $resultsQuery = Result::query()
            ->select(
                'ticket',
                'lottery', // ✅ Mostrar cada lotería por separado
                'number',
                'position',
                'numR',
                'posR',
                'import',
                'user_id',
                'date',
                'aciert' // ✅ Mostrar el premio individual de cada lotería
            )
            ->whereDate('date', $this->date);

        // Todos los usuarios solo ven sus propios resultados
        $resultsQuery->where('user_id', $user->id);
        $allResultsPaginated = $resultsQuery->orderBy('ticket')->orderBy('lottery')->get();
        
        // Filtrar resultados paginados según la configuración de quinielas
        $filteredResultsPaginated = $this->filterResultsByQuinielasConfig($allResultsPaginated);
        
        // Crear una paginación manual con los resultados filtrados
        $results = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredResultsPaginated->forPage(\Illuminate\Pagination\Paginator::resolveCurrentPage(), $this->cant),
            $filteredResultsPaginated->count(),
            $this->cant,
            \Illuminate\Pagination\Paginator::resolveCurrentPage(),
            ['path' => request()->url()]
        );
        // --- LOGS PARA DEPURACIÓN ---
        Log::info("Results - Total Recaudado (PlaysSent) para {$this->date}: " . $totalImporte);
        Log::info("Results - Total Aciertos (Results) para {$this->date}: " . $totalAciertos);
        Log::info("Results - Resultados paginados: " . $results->count() . " de un total de " . $results->total());
        if ($results->isNotEmpty()) {
            Log::info("Results - Primer resultado de ejemplo: ", $results->first()->toArray());
        }

        return view('livewire.admin.results', [
            'results'       => $results,
            'totalImporte'  => $totalImporte,
            'totalAciertos' => $totalAciertos,
        ]);
    }

    public function search()
    {
        $this->resetPage();
        // Livewire se encarga de volver a renderizar automáticamente.
    }

    public function resetFilter()
    {
        try {
            $today = date('Y-m-d');
            $user = auth()->user();
            
            // 1. Eliminar todos los resultados del día actual para este usuario
            $deletedCount = Result::where('date', $today)
                ->where('user_id', $user->id)
                ->delete();
                
            Log::info("Results - resetFilter: Eliminados {$deletedCount} resultados para {$today}");
            
            // 2. Mostrar notificación de inicio
            $this->dispatch('notify', message: "🔄 Reiniciando resultados... Eliminando y recalculando...", type: 'info');
            
            // 3. Resetear fecha y paginación PRIMERO
            $this->date = $today;
            $this->resetPage();
            
            // 4. Ejecutar el proceso de recálculo de forma asíncrona
            $this->dispatch('executeResetProcess', date: $today, userId: $user->id);
            
        } catch (\Exception $e) {
            Log::error("Results - Error en resetFilter: " . $e->getMessage());
            $this->dispatch('notify', message: 'Error al reiniciar: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Ejecuta el proceso de recálculo de forma asíncrona
     */
    public function executeResetProcess($date, $userId)
    {
        try {
            Log::info("Results - Iniciando proceso de recálculo para fecha: {$date}");
            
            // 1. Extraer números ganadores desde la web
            $this->extractAndProcessWinningNumbers($date);
            
            // 2. Re-calcular resultados para todas las jugadas del día
            $this->recalculateResultsForDate($date, $userId);
            
            // 3. Mostrar notificación de éxito
            $this->dispatch('notify', message: "✅ Resultados reiniciados y recalculados correctamente", type: 'success');
            
            // 4. Forzar re-renderizado
            $this->render();
            
        } catch (\Exception $e) {
            Log::error("Results - Error en executeResetProcess: " . $e->getMessage());
            $this->dispatch('notify', message: 'Error al recalcular: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Filtra resultados según la configuración de quinielas
     * Solo muestra loterías que están configuradas en GlobalQuinielasConfiguration
     */
    private function filterResultsByQuinielasConfig($results)
    {
        // Obtener configuración de quinielas
        $savedPreferences = \App\Models\GlobalQuinielasConfiguration::all()
            ->keyBy('city_name')
            ->map(function($config) {
                return $config->selected_schedules;
            });

        // Mapeo de códigos de sistema a nombres de ciudad
        $systemCodeToCity = [
            'NAC' => 'CIUDAD', // Cambiado de 'BUENOS AIRES' a 'CIUDAD'
            'CHA' => 'CHACO', 
            'PRO' => 'PROVINCIA', // Cambiado de 'ENTRE RIOS' a 'PROVINCIA'
            'MZA' => 'MENDOZA',
            'CTE' => 'CORRIENTES',
            'SFE' => 'SANTA FE',
            'COR' => 'CORDOBA',
            'RIO' => 'ENTRE RIOS', // Cambiado de 'LA RIOJA' a 'ENTRE RIOS'
            'ORO' => 'MONTEVIDEO',
            'NQN' => 'NEUQUEN',
            'MIS' => 'MISIONES',
            'JUJ' => 'JUJUY',
            'Salt' => 'SALTA',
            'Rio' => 'Río Negro',
            'Tucu' => 'Tucuman',
            'San' => 'Santiago'
        ];

        return $results->filter(function($result) use ($savedPreferences, $systemCodeToCity) {
            // Si el campo lottery es un número (cantidad de loterías), permitir el resultado
            if (is_numeric($result->lottery)) {
                return true; // Permitir resultados con formato de número de loterías
            }
            
            // Si es un string con códigos de lotería, aplicar el filtro original
            $lotteryCodes = explode(',', $result->lottery);
            
            foreach ($lotteryCodes as $code) {
                $code = trim($code);
                
                // Extraer prefijo de ciudad del código de sistema (ej: "CHA" de "CHA1800")
                if (preg_match('/^([A-Za-z]+)\d{4}$/', $code, $matches)) {
                    $cityPrefix = $matches[1];
                    $cityName = $systemCodeToCity[$cityPrefix] ?? null;
                    
                    if ($cityName && isset($savedPreferences[$cityName])) {
                        $selectedSchedules = $savedPreferences[$cityName];
                        if (!empty($selectedSchedules)) {
                            return true; // Al menos una lotería está configurada
                        }
                    }
                }
            }
            
            return false; // Ninguna lotería está configurada
        });
    }

    /**
     * Extrae números ganadores desde la web y los procesa
     */
    private function extractAndProcessWinningNumbers($date)
    {
        try {
            $winningNumbersService = new WinningNumbersService();
            $availableCities = $winningNumbersService->getAvailableCities();
            
            $totalInserted = 0;
            $errors = [];
            
            foreach ($availableCities as $cityName) {
                try {
                    Log::info("Results - Extrayendo números para: {$cityName}");
                    $numbers = $winningNumbersService->extractWinningNumbers($cityName);
                    
                    if ($numbers && !isset($numbers['skipped'])) {
                        $cityInserted = $this->insertCityNumbersToDatabase($cityName, $numbers, $date);
                        $totalInserted += $cityInserted;
                        Log::info("Results - Insertados {$cityInserted} números para {$cityName}");
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error en {$cityName}: " . $e->getMessage();
                    Log::error("Results - Error extrayendo números para {$cityName}: " . $e->getMessage());
                }
            }
            
            Log::info("Results - Extracción completada: {$totalInserted} números insertados");
            
        } catch (\Exception $e) {
            Log::error("Results - Error en extractAndProcessWinningNumbers: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Inserta números de una ciudad en la base de datos
     */
    private function insertCityNumbersToDatabase($cityName, $numbers, $date)
    {
        $inserted = 0;
        
        try {
            $city = City::where('name', $cityName)->first();
            if (!$city) {
                Log::warning("Results - Ciudad no encontrada: {$cityName}");
                return 0;
            }

            if (isset($numbers['turns']) && is_array($numbers['turns'])) {
                foreach ($numbers['turns'] as $turn) {
                    if (isset($turn['numbers']) && is_array($turn['numbers'])) {
                        foreach ($turn['numbers'] as $index => $value) {
                            // Verificar si ya existe
                            $existing = Number::where('city_id', $city->id)
                                ->where('extract_id', $turn['extract_id'])
                                ->where('index', $index)
                                ->where('date', $date)
                                ->first();

                            if (!$existing) {
                                Number::create([
                                    'city_id' => $city->id,
                                    'extract_id' => $turn['extract_id'],
                                    'index' => $index,
                                    'value' => $value,
                                    'date' => $date,
                                ]);
                                $inserted++;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Results - Error insertando números para {$cityName}: " . $e->getMessage());
        }

        return $inserted;
    }

    /**
     * Recalcula todos los resultados para una fecha específica
     */
    private function recalculateResultsForDate($date, $userId)
    {
        try {
            Log::info("Results - Iniciando recálculo de resultados para fecha: {$date}");
            
            // Obtener todas las ciudades con números ganadores
            $cities = City::with(['numbers' => function ($query) use ($date) {
                $query->where('date', $date);
            }])->get();

            $totalResultsInserted = 0;
            $totalPrize = 0;

            foreach ($cities as $city) {
                if ($city->numbers->isNotEmpty()) {
                    $result = $this->processLotteryResults($city, $date, $userId);
                    $totalResultsInserted += $result['resultsInserted'];
                    $totalPrize += $result['totalPrize'];
                }
            }

            Log::info("Results - Recálculo completado: {$totalResultsInserted} resultados insertados, Total premios: $" . number_format($totalPrize, 2));

        } catch (\Exception $e) {
            Log::error("Results - Error en recalculateResultsForDate: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesa los resultados para una lotería específica
     */
    private function processLotteryResults($city, $date, $userId)
    {
        try {
            // Obtener el código de lotería completo
            $lotteryCode = $this->getLotteryCodeFromCity($city);
            if (!$lotteryCode) {
                return ['resultsInserted' => 0, 'totalPrize' => 0];
            }

            // Verificar si la lotería está completa (20 números)
            if (!$this->isLotteryComplete($lotteryCode, $date)) {
                Log::info("Results - Lotería {$lotteryCode} no está completa aún");
                return ['resultsInserted' => 0, 'totalPrize' => 0];
            }

            // Obtener números completos de la lotería
            $completeNumbers = $this->getCompleteLotteryNumbers($lotteryCode, $date);
            if (!$completeNumbers) {
                return ['resultsInserted' => 0, 'totalPrize' => 0];
            }

            // Buscar jugadas que puedan ser ganadoras
            $matchingPlays = ApusModel::whereDate('created_at', $date)
                ->where('user_id', $userId)
                ->where('lottery', 'LIKE', "%{$lotteryCode}%")
                ->get();

            if ($matchingPlays->isEmpty()) {
                return ['resultsInserted' => 0, 'totalPrize' => 0];
            }

            $resultsInserted = 0;
            $totalPrize = 0;

            foreach ($matchingPlays as $play) {
                if ($this->isWinningPlayForLotteryComplete($play, $completeNumbers, $lotteryCode)) {
                    $prize = $this->calculatePrizeForLotteryComplete($play, $completeNumbers, $lotteryCode);
                    
                    if ($prize > 0) {
                        $resultData = [
                            'user_id' => $play->user_id,
                            'ticket' => $play->ticket,
                            'lottery' => $lotteryCode,
                            'number' => $play->number,
                            'position' => $play->position,
                            'numR' => $play->numberR,
                            'posR' => $play->positionR,
                            'XA' => 'X',
                            'import' => $play->import,
                            'aciert' => $prize,
                            'date' => $date,
                            'time' => $completeNumbers->first()->extract->time,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $result = ResultManager::createResultSafely($resultData);
                        if ($result) {
                            $resultsInserted++;
                            $totalPrize += $prize;
                        }
                    }
                }
            }

            return ['resultsInserted' => $resultsInserted, 'totalPrize' => $totalPrize];

        } catch (\Exception $e) {
            Log::error("Results - Error procesando lotería {$city->name}: " . $e->getMessage());
            return ['resultsInserted' => 0, 'totalPrize' => 0];
        }
    }

    /**
     * Obtiene el código de lotería completo desde una ciudad
     */
    private function getLotteryCodeFromCity($city)
    {
        // Mapeo de códigos de ciudad a códigos de lotería
        $cityCodeMap = [
            'NAC' => 'NAC',
            'CHA' => 'CHA', 
            'PRO' => 'PRO',
            'MZA' => 'MZA',
            'CTE' => 'CTE',
            'SFE' => 'SFE',
            'COR' => 'COR',
            'RIO' => 'RIO',
            'ORO' => 'ORO',
            'NQN' => 'NQN',
            'MIS' => 'MIS',
            'JUJ' => 'JUJ',
            'Salt' => 'Salt',
            'Rio' => 'Rio',
            'Tucu' => 'Tucu',
            'San' => 'San'
        ];

        $cityCode = $city->code;
        $baseCode = $cityCodeMap[$cityCode] ?? null;
        
        if (!$baseCode) {
            return null;
        }

        // Obtener el extract_id del primer número para formar el código completo
        $firstNumber = $city->numbers->first();
        if ($firstNumber && $firstNumber->extract_id) {
            return $baseCode . $firstNumber->extract_id;
        }

        return $baseCode;
    }

    /**
     * Verifica si una lotería está completa (20 números)
     */
    private function isLotteryComplete($lotteryCode, $date)
    {
        $count = Number::whereHas('city', function ($query) use ($lotteryCode) {
            $query->where('code', 'LIKE', substr($lotteryCode, 0, 3) . '%');
        })->where('date', $date)->count();
        
        return $count >= 20;
    }

    /**
     * Obtiene los números completos de una lotería
     */
    private function getCompleteLotteryNumbers($lotteryCode, $date)
    {
        $cityCode = substr($lotteryCode, 0, 3);
        
        return Number::with('extract')
            ->whereHas('city', function ($query) use ($cityCode) {
                $query->where('code', 'LIKE', $cityCode . '%');
            })
            ->where('date', $date)
            ->orderBy('index')
            ->get();
    }

    /**
     * Verifica si una jugada es ganadora para una lotería completa
     */
    private function isWinningPlayForLotteryComplete($play, $completeNumbers, $lotteryCode)
    {
        // Verificar que la jugada contenga esta lotería específica
        $lotteryCodes = explode(',', $play->lottery);
        if (!in_array($lotteryCode, array_map('trim', $lotteryCodes))) {
            return false;
        }

        // Verificar si es ganadora usando los números completos
        foreach ($completeNumbers as $number) {
            if ($this->isWinningPlay($play, $number->value, $number->index)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcula el premio para una jugada ganadora
     */
    private function calculatePrizeForLotteryComplete($play, $completeNumbers, $lotteryCode)
    {
        // Obtener configuraciones de premios
        $quinielaPayouts = \App\Models\QuinielaModel::first();
        $redoblona1toX = \App\Models\BetCollectionRedoblonaModel::first();
        $redoblona5to20 = \App\Models\BetCollection5To20Model::first();
        $redoblona10to20 = \App\Models\BetCollection10To20Model::first();

        if (!$quinielaPayouts || !$redoblona1toX || !$redoblona5to20 || !$redoblona10to20) {
            return 0;
        }

        $mainPrize = 0;
        $redoblonaPrize = 0;

        // Si hay redoblona, calcular solo premio de redoblona
        if (!empty($play->numberR) && !empty($play->positionR)) {
            $redoblonaPrize = $this->calculateRedoblonaPrize($play, $completeNumbers, $redoblona1toX, $redoblona5to20, $redoblona10to20);
        } else {
            // Calcular premio principal
            $mainPrize = $this->calculateMainPrize($play, $completeNumbers, $quinielaPayouts);
        }

        return $mainPrize + $redoblonaPrize;
    }

    /**
     * Verifica si una jugada es ganadora
     */
    private function isWinningPlay($play, $winningNumber, $winningPosition)
    {
        $playNumber = str_replace('*', '', $play->number);
        $winningNumberStr = str_pad($winningNumber, 4, '0', STR_PAD_LEFT);
        
        $playLength = strlen($playNumber);
        $winningSuffix = substr($winningNumberStr, -$playLength);
        
        // Verificar que los números coincidan
        $numbersMatch = $playNumber === $winningSuffix;
        
        if (!$numbersMatch) {
            return false;
        }
        
        // Verificar que la posición sea correcta
        return $this->isPositionCorrect($play->position, $winningPosition);
    }

    /**
     * Verifica si la posición es correcta según las reglas de quiniela
     */
    private function isPositionCorrect($playPosition, $winningPosition)
    {
        // Mapeo de posiciones de quiniela
        $positionMap = [
            1 => [1, 2, 3, 4, 5],      // Primera: posiciones 1-5
            2 => [6, 7, 8, 9, 10],     // Segunda: posiciones 6-10
            3 => [11, 12, 13, 14, 15], // Tercera: posiciones 11-15
            4 => [16, 17, 18, 19, 20]  // Cuarta: posiciones 16-20
        ];

        return isset($positionMap[$playPosition]) && in_array($winningPosition, $positionMap[$playPosition]);
    }

    /**
     * Calcula el premio principal
     */
    private function calculateMainPrize($play, $completeNumbers, $quinielaPayouts)
    {
        $playLength = strlen(str_replace('*', '', $play->number));
        
        // Obtener el premio según la longitud del número
        $prizeField = "premio_{$playLength}";
        return $quinielaPayouts->$prizeField ?? 0;
    }

    /**
     * Calcula el premio de redoblona
     */
    private function calculateRedoblonaPrize($play, $completeNumbers, $redoblona1toX, $redoblona5to20, $redoblona10to20)
    {
        // Lógica simplificada de redoblona - puedes expandir según tus reglas
        $playLength = strlen(str_replace('*', '', $play->number));
        
        if ($playLength == 1) {
            return $redoblona1toX->premio ?? 0;
        } elseif ($playLength >= 5 && $playLength <= 20) {
            return $redoblona5to20->premio ?? 0;
        } elseif ($playLength >= 10 && $playLength <= 20) {
            return $redoblona10to20->premio ?? 0;
        }
        
        return 0;
    }
}