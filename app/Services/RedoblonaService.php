<?php

namespace App\Services;

use App\Models\Number;
use App\Models\BetCollectionRedoblonaModel;
use App\Models\BetCollection5To20Model;
use App\Models\BetCollection10To20Model;
use Illuminate\Support\Facades\Log;

class RedoblonaService
{
    private $redoblona1toX;
    private $redoblona5to20;
    private $redoblona10to20;

    public function __construct()
    {
        $this->loadPayoutTables();
    }

    /**
     * Carga las tablas de pagos de redoblona
     */
    private function loadPayoutTables()
    {
        $this->redoblona1toX = BetCollectionRedoblonaModel::first();
        $this->redoblona5to20 = BetCollection5To20Model::first();
        $this->redoblona10to20 = BetCollection10To20Model::first();
    }

    /**
     * Valida si una jugada puede tener redoblona
     * REQUISITO: Solo números de 2 cifras pueden tener redoblona
     */
    public function canHaveRedoblona($playNumber): bool
    {
        $cleanNumber = str_replace('*', '', $playNumber);
        $digitCount = strlen($cleanNumber);
        
        // Solo números de exactamente 2 cifras pueden tener redoblona
        return $digitCount === 2;
    }

    /**
     * Valida si una redoblona es válida
     * REQUISITO: La redoblona también debe ser de 2 cifras
     */
    public function isValidRedoblona($redoblonaNumber): bool
    {
        $cleanNumber = str_replace('*', '', $redoblonaNumber);
        $digitCount = strlen($cleanNumber);
        
        // La redoblona también debe ser de exactamente 2 cifras
        return $digitCount === 2;
    }

    /**
     * Calcula el premio de redoblona según las nuevas especificaciones
     * IMPORTANTE: Cuando hay redoblona, se paga TODO como redoblona, no por tablas separadas
     */
    public function calculateRedoblonaPrize($play, $date, $lotteryCode): float
    {
        // Validar que la jugada principal puede tener redoblona
        if (!$this->canHaveRedoblona($play->number)) {
            Log::warning("RedoblonaService - La jugada principal no es de 2 cifras: {$play->number}");
            return 0;
        }

        // Validar que la redoblona es válida
        if (!$this->isValidRedoblona($play->numberR)) {
            Log::warning("RedoblonaService - La redoblona no es de 2 cifras: {$play->numberR}");
            return 0;
        }

        // Buscar números ganadores en el rango de posiciones de redoblona
        $redoblonaRange = $this->getRedoblonaPositionRange($play->positionR);
        $redoblonaNumbers = Number::with(['city', 'extract'])
            ->whereHas('city', function($query) use ($lotteryCode) {
                $query->where('code', $lotteryCode);
            })
            ->whereBetween('index', [$redoblonaRange['min'], $redoblonaRange['max']])
            ->whereDate('date', $date)
            ->get();

        if ($redoblonaNumbers->isEmpty()) {
            Log::info("RedoblonaService - No se encontraron números ganadores en rango {$redoblonaRange['min']}-{$redoblonaRange['max']} para redoblona");
            return 0;
        }

        // Verificar si la redoblona es ganadora en algún número del rango
        $isRedoblonaWinner = false;
        foreach ($redoblonaNumbers as $redoblonaNumber) {
            if ($this->isRedoblonaWinner($play->numberR, $redoblonaNumber->value)) {
                $isRedoblonaWinner = true;
                Log::info("RedoblonaService - Redoblona ganadora: {$play->numberR} vs {$redoblonaNumber->value} en posición {$redoblonaNumber->index}");
                break;
            }
        }

        if (!$isRedoblonaWinner) {
            Log::info("RedoblonaService - Redoblona no ganadora en rango {$redoblonaRange['min']}-{$redoblonaRange['max']}");
            return 0;
        }

        // IMPORTANTE: Cuando hay redoblona, se paga TODO como redoblona
        // No se paga por las tablas normales (Quiniela, Prizes, FigureOne, FigureTwo)
        $prize = $this->calculatePrizeByMainPosition($play->position, $play->positionR, $play->import);
        
        Log::info("RedoblonaService - Premio TOTAL como redoblona: {$prize} para jugada {$play->number} en posición {$play->position}, redoblona {$play->numberR} en posición {$play->positionR}");
        
        return $prize;
    }

    /**
     * Obtiene el rango de posiciones para la redoblona (igual que las jugadas normales)
     */
    private function getRedoblonaPositionRange($position): array
    {
        // Misma lógica que las jugadas normales
        if ($position == 1) {
            return ['min' => 1, 'max' => 1];
        } elseif ($position >= 2 && $position <= 5) {
            return ['min' => 2, 'max' => 5];
        } elseif ($position >= 6 && $position <= 10) {
            return ['min' => 6, 'max' => 10];
        } elseif ($position >= 11 && $position <= 20) {
            return ['min' => 11, 'max' => 20];
        }
        
        return ['min' => $position, 'max' => $position];
    }

    /**
     * Verifica si la redoblona es ganadora
     */
    private function isRedoblonaWinner($playNumberR, $winningNumber): bool
    {
        $playNumber = str_replace('*', '', $playNumberR);
        $winningNumberStr = str_pad($winningNumber, 4, '0', STR_PAD_LEFT);
        $playLength = strlen($playNumber);
        $winningSuffix = substr($winningNumberStr, -$playLength);

        return $playNumber === $winningSuffix;
    }

    /**
     * Calcula el premio según la posición de la jugada principal y la posición de redoblona
     */
    private function calculatePrizeByMainPosition($mainPosition, $redoblonaPosition, $import): float
    {
        // Determinar el multiplicador según las tablas especificadas
        $multiplier = $this->getMultiplier($mainPosition, $redoblonaPosition);
        
        if ($multiplier === 0) {
            return 0;
        }

        return $import * $multiplier;
    }

    /**
     * Obtiene el multiplicador según las tablas de pago especificadas
     */
    private function getMultiplier($mainPosition, $redoblonaPosition): float
    {
        // Tabla: A los 1 todo a los 5 Cobra, A los 1 todo a los 10 Cobra, A los 1 todo a los 20 Cobra
        if ($mainPosition == 1) {
            if ($redoblonaPosition >= 1 && $redoblonaPosition <= 5) {
                return $this->redoblona1toX ? $this->redoblona1toX->payout_1_to_5 : 0;
            } elseif ($redoblonaPosition >= 6 && $redoblonaPosition <= 10) {
                return $this->redoblona1toX ? $this->redoblona1toX->payout_1_to_10 : 0;
            } elseif ($redoblonaPosition >= 11 && $redoblonaPosition <= 20) {
                return $this->redoblona1toX ? $this->redoblona1toX->payout_1_to_20 : 0;
            }
        }

        // Tabla: A los 5 todo a los 5 Cobra, A los 5 todo a los 10 Cobra, A los 5 todo a los 20 Cobra
        if ($mainPosition >= 2 && $mainPosition <= 5) {
            if ($redoblonaPosition >= 1 && $redoblonaPosition <= 5) {
                return $this->redoblona5to20 ? $this->redoblona5to20->payout_5_to_5 : 0;
            } elseif ($redoblonaPosition >= 6 && $redoblonaPosition <= 10) {
                return $this->redoblona5to20 ? $this->redoblona5to20->payout_5_to_10 : 0;
            } elseif ($redoblonaPosition >= 11 && $redoblonaPosition <= 20) {
                return $this->redoblona5to20 ? $this->redoblona5to20->payout_5_to_20 : 0;
            }
        }

        // Tabla: A los 10 todo a los 10 Cobra, A los 10 todo a los 20 Cobra, A los 20 todo a los 20 Cobra
        if ($mainPosition >= 6 && $mainPosition <= 10) {
            if ($redoblonaPosition >= 6 && $redoblonaPosition <= 10) {
                return $this->redoblona10to20 ? $this->redoblona10to20->payout_10_to_10 : 0;
            } elseif ($redoblonaPosition >= 11 && $redoblonaPosition <= 20) {
                return $this->redoblona10to20 ? $this->redoblona10to20->payout_10_to_20 : 0;
            }
        }

        if ($mainPosition >= 11 && $mainPosition <= 20) {
            if ($redoblonaPosition >= 11 && $redoblonaPosition <= 20) {
                return $this->redoblona10to20 ? $this->redoblona10to20->payout_20_to_20 : 0;
            }
        }

        return 0;
    }

    /**
     * Obtiene el mensaje de error para validación de redoblona
     */
    public function getValidationErrorMessage($playNumber): string
    {
        $cleanNumber = str_replace('*', '', $playNumber);
        $digitCount = strlen($cleanNumber);
        
        if ($digitCount != 2) {
            return "La redoblona solo se puede con números de 2 cifras. Tu número tiene {$digitCount} cifras.";
        }
        
        return "";
    }
}
