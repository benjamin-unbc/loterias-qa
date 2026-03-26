<?php

namespace App\Services;

use App\Models\Number;
use App\Models\City;
use Illuminate\Support\Facades\Log;

class LotteryCompletenessService
{
    /**
     * Verifica si una lotería específica tiene sus 20 números completos
     * 
     * @param string $lotteryCode Código de la lotería (ej: NAC1015, SFE1015)
     * @param string $date Fecha a verificar
     * @return bool True si tiene los 20 números completos
     */
    public static function isLotteryComplete(string $lotteryCode, string $date): bool
    {
        try {
            // Buscar la ciudad por código de lotería
            $city = City::where('code', $lotteryCode)->first();
            
            if (!$city) {
                Log::warning("LotteryCompletenessService - No se encontró ciudad con código: {$lotteryCode}");
                return false;
            }

            // Contar números ganadores para esta lotería en esta fecha
            $numbersCount = Number::whereHas('city', function($query) use ($lotteryCode) {
                $query->where('code', $lotteryCode);
            })
            ->whereDate('date', $date)
            ->count();

            $isComplete = $numbersCount >= 20;
            
            Log::info("LotteryCompletenessService - Lotería {$lotteryCode} en {$date}: {$numbersCount}/20 números - " . ($isComplete ? 'COMPLETA' : 'INCOMPLETA'));
            
            return $isComplete;
            
        } catch (\Exception $e) {
            Log::error("LotteryCompletenessService - Error verificando completitud: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todas las loterías completas para una fecha específica
     * 
     * @param string $date Fecha a verificar
     * @return array Array de códigos de loterías completas
     */
    public static function getCompleteLotteries(string $date): array
    {
        try {
            $completeLotteries = [];
            
            // Obtener todas las ciudades (loterías)
            $cities = City::all();
            
            foreach ($cities as $city) {
                if (self::isLotteryComplete($city->code, $date)) {
                    $completeLotteries[] = $city->code;
                }
            }
            
            Log::info("LotteryCompletenessService - Loterías completas para {$date}: " . implode(', ', $completeLotteries));
            
            return $completeLotteries;
            
        } catch (\Exception $e) {
            Log::error("LotteryCompletenessService - Error obteniendo loterías completas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si una lotería específica tiene sus 20 números completos y los devuelve
     * 
     * @param string $lotteryCode Código de la lotería
     * @param string $date Fecha a verificar
     * @return array|null Array con los números si está completa, null si no
     */
    public static function getCompleteLotteryNumbers(string $lotteryCode, string $date): ?array
    {
        try {
            if (!self::isLotteryComplete($lotteryCode, $date)) {
                return null;
            }

            // Obtener los 20 números ganadores ordenados por posición
            $numbers = Number::with(['city', 'extract'])
                ->whereHas('city', function($query) use ($lotteryCode) {
                    $query->where('code', $lotteryCode);
                })
                ->whereDate('date', $date)
                ->orderBy('index')
                ->get();

            if ($numbers->count() < 20) {
                Log::warning("LotteryCompletenessService - Lotería {$lotteryCode} tiene menos de 20 números: {$numbers->count()}");
                return null;
            }

            Log::info("LotteryCompletenessService - Lotería {$lotteryCode} completa con {$numbers->count()} números");
            
            return $numbers->toArray();
            
        } catch (\Exception $e) {
            Log::error("LotteryCompletenessService - Error obteniendo números completos: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica si una lotería específica tiene sus 20 números completos y los devuelve como Collection
     * 
     * @param string $lotteryCode Código de la lotería
     * @param string $date Fecha a verificar
     * @return \Illuminate\Database\Eloquent\Collection|null Collection con los números si está completa, null si no
     */
    public static function getCompleteLotteryNumbersCollection(string $lotteryCode, string $date)
    {
        try {
            if (!self::isLotteryComplete($lotteryCode, $date)) {
                return null;
            }

            // Obtener los 20 números ganadores ordenados por posición
            $numbers = Number::with(['city', 'extract'])
                ->whereHas('city', function($query) use ($lotteryCode) {
                    $query->where('code', $lotteryCode);
                })
                ->whereDate('date', $date)
                ->orderBy('index')
                ->get();

            if ($numbers->count() < 20) {
                Log::warning("LotteryCompletenessService - Lotería {$lotteryCode} tiene menos de 20 números: {$numbers->count()}");
                return null;
            }

            Log::info("LotteryCompletenessService - Lotería {$lotteryCode} completa con {$numbers->count()} números");
            
            return $numbers;
            
        } catch (\Exception $e) {
            Log::error("LotteryCompletenessService - Error obteniendo números completos: " . $e->getMessage());
            return null;
        }
    }
}
