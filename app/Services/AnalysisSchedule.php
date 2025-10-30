<?php

namespace App\Services;

class AnalysisSchedule
{
    /**
     * Ventanas de análisis (hora:minuto) en 24h
     */
    private static array $windows = [
        ['11', '15'],
        ['12', '45'],
        ['15', '45'],
        ['18', '45'],
        ['21', '45'],
        ['22', '45'],
    ];

    /**
     * Duración de la ventana en minutos
     */
    private const WINDOW_MINUTES = 20;

    /**
     * Retorna true si la hora actual está dentro de alguna ventana de análisis
     */
    public static function isWithinAnalysisWindow(?\DateTimeInterface $now = null): bool
    {
        $now = $now ? (clone $now) : new \DateTime('now');

        foreach (self::$windows as [$h, $m]) {
            $start = (new \DateTime($now->format('Y-m-d')))->setTime((int)$h, (int)$m, 0);
            $end = (clone $start)->modify('+' . self::WINDOW_MINUTES . ' minutes');
            if ($now >= $start && $now <= $end) {
                return true;
            }
        }

        return false;
    }
}


