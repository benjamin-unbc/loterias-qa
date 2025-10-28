<?php

namespace App\Services;

use App\Models\Result;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ResultManager
{
    /**
     * Inserta un resultado de forma segura, evitando duplicados
     * 
     * @param array $resultData Datos del resultado
     * @return Result|null El resultado creado o null si ya existía
     */
    public static function createResultSafely(array $resultData): ?Result
    {
        // No crear resultados con premio cero o negativo
        if (!isset($resultData['aciert']) || (float) $resultData['aciert'] <= 0) {
            Log::info("ResultManager - Resultado descartado por premio <= 0: Ticket {$resultData['ticket']} - Lotería {$resultData['lottery']}");
            return null;
        }

        try {
            // Verificar si ya existe un resultado idéntico
            $existingResult = Result::where('ticket', $resultData['ticket'])
                ->where('lottery', $resultData['lottery'])
                ->where('number', $resultData['number'])
                ->where('position', $resultData['position'])
                ->where('date', $resultData['date'])
                ->first();

            if ($existingResult) {
                Log::info("ResultManager - Resultado duplicado evitado: Ticket {$resultData['ticket']} - Lotería {$resultData['lottery']} - Número {$resultData['number']} - Posición {$resultData['position']}");
                return null;
            }

            // Usar transacción para evitar condiciones de carrera
            return DB::transaction(function () use ($resultData) {
                // Verificar nuevamente dentro de la transacción
                $existingResult = Result::where('ticket', $resultData['ticket'])
                    ->where('lottery', $resultData['lottery'])
                    ->where('number', $resultData['number'])
                    ->where('position', $resultData['position'])
                    ->where('date', $resultData['date'])
                    ->lockForUpdate()
                    ->first();

                if ($existingResult) {
                    Log::info("ResultManager - Resultado duplicado evitado en transacción: Ticket {$resultData['ticket']}");
                    return null;
                }

                // Crear el resultado
                $result = Result::create($resultData);
                Log::info("ResultManager - Resultado creado exitosamente: ID {$result->id} - Ticket {$resultData['ticket']} - Premio: \${$resultData['aciert']}");
                
                return $result;
            });

        } catch (\Illuminate\Database\QueryException $e) {
            // Si es un error de clave duplicada, loggear y continuar
            if ($e->getCode() == 23000) { // MySQL duplicate entry error
                Log::warning("ResultManager - Error de clave duplicada evitado: Ticket {$resultData['ticket']} - {$e->getMessage()}");
                return null;
            }
            
            // Re-lanzar otros errores
            Log::error("ResultManager - Error inesperado: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error("ResultManager - Error general: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Inserta múltiples resultados de forma segura
     * 
     * @param array $resultsData Array de datos de resultados
     * @return int Número de resultados creados exitosamente
     */
    public static function createMultipleResultsSafely(array $resultsData): int
    {
        $createdCount = 0;
        
        foreach ($resultsData as $resultData) {
            $result = self::createResultSafely($resultData);
            if ($result) {
                $createdCount++;
            }
        }
        
        Log::info("ResultManager - Procesados " . count($resultsData) . " resultados, creados {$createdCount}");
        return $createdCount;
    }

    /**
     * Limpia resultados duplicados existentes (mantiene el de mayor premio)
     * 
     * @param string $date Fecha para limpiar duplicados
     * @return int Número de duplicados eliminados
     */
    public static function cleanDuplicateResults(string $date): int
    {
        try {
            $duplicatesRemoved = 0;
            
            // Buscar grupos de resultados duplicados
            $duplicateGroups = Result::where('date', $date)
                ->select('ticket', 'lottery', 'number', 'position', 'date')
                ->groupBy('ticket', 'lottery', 'number', 'position', 'date')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicateGroups as $group) {
                // Obtener todos los resultados duplicados para este grupo
                $duplicates = Result::where('ticket', $group->ticket)
                    ->where('lottery', $group->lottery)
                    ->where('number', $group->number)
                    ->where('position', $group->position)
                    ->where('date', $group->date)
                    ->orderBy('aciert', 'desc') // Mantener el de mayor premio
                    ->get();

                // Eliminar todos excepto el primero (mayor premio)
                $toDelete = $duplicates->skip(1);
                foreach ($toDelete as $duplicate) {
                    Log::info("ResultManager - Eliminando duplicado: ID {$duplicate->id} - Ticket {$duplicate->ticket} - Premio: \${$duplicate->aciert}");
                    $duplicate->delete();
                    $duplicatesRemoved++;
                }
            }

            if ($duplicatesRemoved > 0) {
                Log::info("ResultManager - Limpieza completada: {$duplicatesRemoved} duplicados eliminados para la fecha {$date}");
            }

            return $duplicatesRemoved;

        } catch (\Exception $e) {
            Log::error("ResultManager - Error limpiando duplicados: " . $e->getMessage());
            return 0;
        }
    }
}
