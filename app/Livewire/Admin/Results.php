<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\PlaysSentModel;
use App\Models\Result; // Usar el modelo correcto 'Result'
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
            $this->date = date('Y-m-d');
            $this->resetPage();
            
            // ✅ NUEVO: Eliminar y recalcular resultados con la nueva lógica
            $this->recalculateResults();
            
            // Livewire se encarga de volver a renderizar automáticamente.
        } catch (\Exception $e) {
            Log::error("Results - Error en resetFilter: " . $e->getMessage());
            session()->flash('error', 'Error al reiniciar resultados: ' . $e->getMessage());
        }
    }
    
    /**
     * ✅ NUEVO: Elimina y recalcula todos los resultados para la fecha actual
     */
    private function recalculateResults()
    {
        try {
            $user = auth()->user();
            $date = $this->date;
            
            Log::info("Results - Iniciando recálculo de resultados para fecha: {$date}, usuario: {$user->id}");
            
            // 1. Eliminar todos los resultados existentes para esta fecha y usuario
            $deletedCount = Result::whereDate('date', $date)
                ->where('user_id', $user->id)
                ->delete();
                
            Log::info("Results - Eliminados {$deletedCount} resultados existentes");
            
            // 2. Obtener todas las jugadas enviadas para esta fecha y usuario
            $playsSent = PlaysSentModel::whereDate('date', $date)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'I')
                ->get();
                
            Log::info("Results - Encontradas {$playsSent->count()} jugadas para recalcular");
            
            if ($playsSent->isEmpty()) {
                Log::info("Results - No hay jugadas para recalcular");
                return;
            }
            
            // 3. Obtener todos los números ganadores para esta fecha
            $winningNumbers = \App\Models\Number::whereDate('date', $date)
                ->with('city')
                ->get();
                
            if ($winningNumbers->isEmpty()) {
                Log::warning("Results - No hay números ganadores para la fecha {$date}");
                return;
            }
            
            // 4. Agrupar números ganadores por código de lotería
            $groupedWinningNumbers = [];
            foreach ($winningNumbers as $wn) {
                if ($wn->city) {
                    $lotteryKey = $wn->city->code;
                    if (!isset($groupedWinningNumbers[$lotteryKey])) {
                        $groupedWinningNumbers[$lotteryKey] = [];
                    }
                    $groupedWinningNumbers[$lotteryKey][$wn->index] = $wn->value;
                }
            }
            
            Log::info("Results - Números ganadores agrupados por lotería: " . count($groupedWinningNumbers) . " loterías");
            
            // 5. Recalcular resultados para cada jugada
            $resultsInserted = 0;
            $totalPrize = 0;
            
            foreach ($playsSent as $play) {
                // Obtener las loterías de esta jugada
                $playLotteries = explode(',', $play->lottery);
                $playLotteries = array_map('trim', $playLotteries);
                
                foreach ($playLotteries as $lotteryCode) {
                    if (!isset($groupedWinningNumbers[$lotteryCode])) {
                        continue; // No hay números ganadores para esta lotería
                    }
                    
                    $lotteryNumbers = $groupedWinningNumbers[$lotteryCode];
                    
                    // Verificar si la jugada es ganadora para esta lotería
                    $isWinner = $this->isWinningPlayForLottery($play, $lotteryNumbers, $lotteryCode);
                    
                    if ($isWinner['isWinner']) {
                        // Calcular premio
                        $prize = $this->calculatePrizeForPlay($play, $isWinner['winningNumber'], $isWinner['winningPosition'], $lotteryCode);
                        
                        if ($prize > 0) {
                            // Insertar resultado
                            Result::create([
                                'ticket' => $play->ticket,
                                'lottery' => $lotteryCode,
                                'number' => $play->number,
                                'position' => $play->position,
                                'numR' => $play->numberR ?? '',
                                'posR' => $play->positionR ?? '',
                                'import' => $play->amount,
                                'aciert' => $prize,
                                'user_id' => $user->id,
                                'date' => $date,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            
                            $resultsInserted++;
                            $totalPrize += $prize;
                            
                            Log::info("Results - Resultado insertado: Ticket {$play->ticket}, Lotería {$lotteryCode}, Premio: \${$prize}");
                        }
                    }
                }
            }
            
            Log::info("Results - Recalculo completado: {$resultsInserted} resultados insertados, Total premios: \${$totalPrize}");
            
            session()->flash('success', "✅ Resultados recalculados exitosamente. {$resultsInserted} resultados insertados.");
            
        } catch (\Exception $e) {
            Log::error("Results - Error en recalculateResults: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * ✅ NUEVO: Verifica si una jugada es ganadora para una lotería específica
     */
    private function isWinningPlayForLottery($play, $lotteryNumbers, $lotteryCode)
    {
        $playNumber = str_replace('*', '', $play->number);
        $playLength = strlen($playNumber);
        
        if ($playLength <= 0 || $playLength > 4) {
            return ['isWinner' => false];
        }
        
        // Buscar coincidencia en todas las posiciones de la lotería
        foreach ($lotteryNumbers as $position => $winningNumber) {
            $winningNumberStr = str_pad($winningNumber, 4, '0', STR_PAD_LEFT);
            $winningSuffix = substr($winningNumberStr, -$playLength);
            
            if ($playNumber === $winningSuffix) {
                // Verificar que la posición sea correcta según las reglas de quiniela
                if ($this->isPositionCorrect($play->position, $position)) {
                    return [
                        'isWinner' => true,
                        'winningNumber' => $winningNumber,
                        'winningPosition' => $position
                    ];
                }
            }
        }
        
        return ['isWinner' => false];
    }
    
    /**
     * ✅ NUEVO: Verifica si la posición apostada es correcta según las reglas de quiniela
     */
    private function isPositionCorrect($playedPosition, $winningPosition)
    {
        // Reglas de quiniela:
        // - Posición 1 (Quiniela): Solo gana si sale en posición 1
        // - Posición 5: Gana si sale en posiciones 2-5
        // - Posición 10: Gana si sale en posiciones 6-10  
        // - Posición 20: Gana si sale en posiciones 11-20
        
        switch ($playedPosition) {
            case 1:
                // Quiniela: solo gana si sale en posición 1
                return $winningPosition == 1;
                
            case 5:
                // A los 5: gana si sale en posiciones 2-5
                return $winningPosition >= 2 && $winningPosition <= 5;
                
            case 10:
                // A los 10: gana si sale en posiciones 6-10
                return $winningPosition >= 6 && $winningPosition <= 10;
                
            case 20:
                // A los 20: gana si sale en posiciones 11-20
                return $winningPosition >= 11 && $winningPosition <= 20;
                
            default:
                // Para otras posiciones, verificar coincidencia exacta
                return $playedPosition == $winningPosition;
        }
    }
    
    /**
     * ✅ NUEVO: Calcula el premio para una jugada ganadora
     */
    private function calculatePrizeForPlay($play, $winningNumber, $winningPosition, $lotteryCode)
    {
        try {
            // Obtener configuraciones de premios
            $quinielaPayouts = \App\Models\QuinielaModel::first();
            $prizesPayouts = \App\Models\PrizesModel::first();
            $figureOnePayouts = \App\Models\FigureOneModel::first();
            $figureTwoPayouts = \App\Models\FigureTwoModel::first();
            
            if (!$quinielaPayouts || !$prizesPayouts || !$figureOnePayouts || !$figureTwoPayouts) {
                Log::warning("Results - Faltan configuraciones de premios");
                return 0;
            }
            
            $playNumber = str_replace('*', '', $play->number);
            $playedDigits = strlen($playNumber);
            $prize = 0;
            
            // Calcular premio según la posición apostada
            if ($play->position == 1) {
                // Posición 1 (Quiniela): usar tabla de quiniela
                $multiplier = 0;
                if ($playedDigits == 1) $multiplier = (float)($quinielaPayouts->cobra_1_cifra ?? 0);
                elseif ($playedDigits == 2) $multiplier = (float)($quinielaPayouts->cobra_2_cifra ?? 0);
                elseif ($playedDigits == 3) $multiplier = (float)($quinielaPayouts->cobra_3_cifra ?? 0);
                elseif ($playedDigits == 4) $multiplier = (float)($quinielaPayouts->cobra_4_cifra ?? 0);
                
                $prize = $play->amount * $multiplier;
            } else {
                // Otras posiciones: usar tabla de premios según dígitos
                $multiplier = 0;
                if ($playedDigits == 1 || $playedDigits == 2) {
                    // Usar tabla de premios (A los 5, 10, 20)
                    $multiplier = $this->getPositionMultiplier($winningPosition, $prizesPayouts);
                } elseif ($playedDigits == 3) {
                    // Usar tabla de figura 1
                    $multiplier = $this->getPositionMultiplier($winningPosition, $figureOnePayouts);
                } elseif ($playedDigits == 4) {
                    // Usar tabla de figura 2
                    $multiplier = $this->getPositionMultiplier($winningPosition, $figureTwoPayouts);
                }
                
                $prize = $play->amount * $multiplier;
            }
            
            return $prize;
            
        } catch (\Exception $e) {
            Log::error("Results - Error calculando premio: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * ✅ NUEVO: Obtiene el multiplicador de premio según la posición
     */
    private function getPositionMultiplier($position, $payoutTable)
    {
        if ($position >= 1 && $position <= 5) {
            return (float)($payoutTable->pos_5 ?? 0);
        } elseif ($position >= 6 && $position <= 10) {
            return (float)($payoutTable->pos_10 ?? 0);
        } elseif ($position >= 11 && $position <= 20) {
            return (float)($payoutTable->pos_20 ?? 0);
        }
        
        return 0;
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
}