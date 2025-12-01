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
            // ✅ CORREGIDO: Verificar si ya existe un resultado idéntico incluyendo numR, posR y apu_id
            // Esto permite que apuestas con diferentes redoblonas o diferentes APUs se inserten como resultados separados
            
            // Primera verificación: buscar resultado exacto con mismo apu_id
            $query = Result::where('ticket', $resultData['ticket'])
                ->where('lottery', $resultData['lottery'])
                ->where('number', $resultData['number'])
                ->where('position', $resultData['position'])
                ->where('date', $resultData['date']);
            
            // Incluir numR y posR en la verificación de duplicados
            if (isset($resultData['numR']) && $resultData['numR'] !== null) {
                $query->where('numR', $resultData['numR']);
            } else {
                $query->whereNull('numR');
            }
            
            if (isset($resultData['posR']) && $resultData['posR'] !== null) {
                $query->where('posR', $resultData['posR']);
            } else {
                $query->whereNull('posR');
            }
            
            // ✅ NUEVO: Incluir apu_id en la verificación de duplicados
            // Esto permite múltiples resultados del mismo número cuando provienen de diferentes APUs
            if (isset($resultData['apu_id']) && $resultData['apu_id'] !== null) {
                $query->where('apu_id', $resultData['apu_id']);
            } else {
                $query->whereNull('apu_id');
            }
            
            $existingResult = $query->first();

            // ✅ MEJORADO: Si estamos insertando con apu_id, también verificar si existe un resultado sin apu_id
            // que debería ser actualizado en lugar de crear un duplicado
            if (!$existingResult && isset($resultData['apu_id']) && $resultData['apu_id'] !== null) {
                $queryWithoutApuId = Result::where('ticket', $resultData['ticket'])
                    ->where('lottery', $resultData['lottery'])
                    ->where('number', $resultData['number'])
                    ->where('position', $resultData['position'])
                    ->where('date', $resultData['date'])
                    ->whereNull('apu_id');
                
                // Incluir numR y posR
                if (isset($resultData['numR']) && $resultData['numR'] !== null) {
                    $queryWithoutApuId->where('numR', $resultData['numR']);
                } else {
                    $queryWithoutApuId->whereNull('numR');
                }
                
                if (isset($resultData['posR']) && $resultData['posR'] !== null) {
                    $queryWithoutApuId->where('posR', $resultData['posR']);
                } else {
                    $queryWithoutApuId->whereNull('posR');
                }
                
                $existingResultWithoutApuId = $queryWithoutApuId->first();
                
                if ($existingResultWithoutApuId) {
                    // Actualizar el resultado existente con el apu_id en lugar de crear un duplicado
                    $existingResultWithoutApuId->apu_id = $resultData['apu_id'];
                    $existingResultWithoutApuId->save();
                    Log::info("ResultManager - Resultado existente actualizado con apu_id: ID {$existingResultWithoutApuId->id} - Ticket {$resultData['ticket']} - APU ID: {$resultData['apu_id']}");
                    return $existingResultWithoutApuId;
                }
            }

            if ($existingResult) {
                Log::info("ResultManager - Resultado duplicado evitado: Ticket {$resultData['ticket']} - Lotería {$resultData['lottery']} - Número {$resultData['number']} - Posición {$resultData['position']} - NumR: " . ($resultData['numR'] ?? 'null') . " - PosR: " . ($resultData['posR'] ?? 'null') . " - APU ID: " . ($resultData['apu_id'] ?? 'null'));
                return null;
            }

            // Usar transacción para evitar condiciones de carrera
            return DB::transaction(function () use ($resultData) {
                // ✅ CORREGIDO: Verificar nuevamente dentro de la transacción incluyendo numR, posR y apu_id
                $query = Result::where('ticket', $resultData['ticket'])
                    ->where('lottery', $resultData['lottery'])
                    ->where('number', $resultData['number'])
                    ->where('position', $resultData['position'])
                    ->where('date', $resultData['date']);
                
                // Incluir numR y posR en la verificación de duplicados
                if (isset($resultData['numR']) && $resultData['numR'] !== null) {
                    $query->where('numR', $resultData['numR']);
                } else {
                    $query->whereNull('numR');
                }
                
                if (isset($resultData['posR']) && $resultData['posR'] !== null) {
                    $query->where('posR', $resultData['posR']);
                } else {
                    $query->whereNull('posR');
                }
                
                // ✅ NUEVO: Incluir apu_id en la verificación de duplicados
                if (isset($resultData['apu_id']) && $resultData['apu_id'] !== null) {
                    $query->where('apu_id', $resultData['apu_id']);
                } else {
                    $query->whereNull('apu_id');
                }
                
                $existingResult = $query->lockForUpdate()->first();

                // ✅ MEJORADO: Si estamos insertando con apu_id, también verificar si existe un resultado sin apu_id
                if (!$existingResult && isset($resultData['apu_id']) && $resultData['apu_id'] !== null) {
                    $queryWithoutApuId = Result::where('ticket', $resultData['ticket'])
                        ->where('lottery', $resultData['lottery'])
                        ->where('number', $resultData['number'])
                        ->where('position', $resultData['position'])
                        ->where('date', $resultData['date'])
                        ->whereNull('apu_id')
                        ->lockForUpdate();
                    
                    // Incluir numR y posR
                    if (isset($resultData['numR']) && $resultData['numR'] !== null) {
                        $queryWithoutApuId->where('numR', $resultData['numR']);
                    } else {
                        $queryWithoutApuId->whereNull('numR');
                    }
                    
                    if (isset($resultData['posR']) && $resultData['posR'] !== null) {
                        $queryWithoutApuId->where('posR', $resultData['posR']);
                    } else {
                        $queryWithoutApuId->whereNull('posR');
                    }
                    
                    $existingResultWithoutApuId = $queryWithoutApuId->first();
                    
                    if ($existingResultWithoutApuId) {
                        // Actualizar el resultado existente con el apu_id en lugar de crear un duplicado
                        $existingResultWithoutApuId->apu_id = $resultData['apu_id'];
                        $existingResultWithoutApuId->save();
                        Log::info("ResultManager - Resultado existente actualizado con apu_id en transacción: ID {$existingResultWithoutApuId->id} - Ticket {$resultData['ticket']} - APU ID: {$resultData['apu_id']}");
                        return $existingResultWithoutApuId;
                    }
                }

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
