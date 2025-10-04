<?php

namespace App\Services;

use Carbon\Carbon;

class LotteryTurnsService
{
    /**
     * Definición de turnos de lotería con sus horarios
     */
    private $turns = [
        'La Previa' => [
            'name' => 'La Previa',
            'time' => '09:00',
            'end_time' => '11:00',
            'status' => 'completed'
        ],
        'El Primero' => [
            'name' => 'El Primero', 
            'time' => '11:00',
            'end_time' => '13:00',
            'status' => 'completed'
        ],
        'Matutina' => [
            'name' => 'Matutina',
            'time' => '13:00', 
            'end_time' => '15:00',
            'status' => 'pending'
        ],
        'Vespertina' => [
            'name' => 'Vespertina',
            'time' => '15:00',
            'end_time' => '17:00', 
            'status' => 'pending'
        ],
        'Nocturna' => [
            'name' => 'Nocturna',
            'time' => '17:00',
            'end_time' => '19:00',
            'status' => 'pending'
        ]
    ];

    /**
     * Obtiene el estado actual de los turnos
     */
    public function getTurnsStatus(): array
    {
        $currentTime = Carbon::now()->setTimezone('America/Argentina/Buenos_Aires');
        $currentHour = $currentTime->format('H:i');
        
        $turnsWithStatus = [];
        
        foreach ($this->turns as $key => $turn) {
            $turnStatus = $this->getTurnStatus($currentHour, $turn);
            $turnsWithStatus[$key] = array_merge($turn, [
                'status' => $turnStatus,
                'current_time' => $currentHour,
                'is_active' => $turnStatus === 'active',
                'is_completed' => $turnStatus === 'completed',
                'is_pending' => $turnStatus === 'pending'
            ]);
        }
        
        return $turnsWithStatus;
    }

    /**
     * Determina el estado de un turno basado en la hora actual
     */
    private function getTurnStatus(string $currentTime, array $turn): string
    {
        $current = Carbon::createFromFormat('H:i', $currentTime);
        $start = Carbon::createFromFormat('H:i', $turn['time']);
        $end = Carbon::createFromFormat('H:i', $turn['end_time']);
        
        if ($current->between($start, $end)) {
            return 'active';
        } elseif ($current->greaterThan($end)) {
            return 'completed';
        } else {
            return 'pending';
        }
    }

    /**
     * Obtiene el turno actualmente activo
     */
    public function getCurrentTurn(): ?array
    {
        $turnsStatus = $this->getTurnsStatus();
        
        foreach ($turnsStatus as $turn) {
            if ($turn['is_active']) {
                return $turn;
            }
        }
        
        return null;
    }

    /**
     * Obtiene los turnos completados
     */
    public function getCompletedTurns(): array
    {
        $turnsStatus = $this->getTurnsStatus();
        
        return array_filter($turnsStatus, function($turn) {
            return $turn['is_completed'];
        });
    }

    /**
     * Obtiene los turnos pendientes
     */
    public function getPendingTurns(): array
    {
        $turnsStatus = $this->getTurnsStatus();
        
        return array_filter($turnsStatus, function($turn) {
            return $turn['is_pending'];
        });
    }

    /**
     * Obtiene el próximo turno
     */
    public function getNextTurn(): ?array
    {
        $turnsStatus = $this->getTurnsStatus();
        
        foreach ($turnsStatus as $turn) {
            if ($turn['is_pending']) {
                return $turn;
            }
        }
        
        return null;
    }

    /**
     * Calcula el tiempo restante para el próximo turno
     */
    public function getTimeUntilNextTurn(): ?array
    {
        $nextTurn = $this->getNextTurn();
        
        if (!$nextTurn) {
            return null;
        }
        
        $currentTime = Carbon::now()->setTimezone('America/Argentina/Buenos_Aires');
        $nextTurnTime = Carbon::createFromFormat('H:i', $nextTurn['time'])
            ->setDate($currentTime->year, $currentTime->month, $currentTime->day);
        
        // Si el próximo turno es mañana
        if ($nextTurnTime->lessThan($currentTime)) {
            $nextTurnTime->addDay();
        }
        
        $diff = $currentTime->diff($nextTurnTime);
        
        return [
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'seconds' => $diff->s,
            'total_minutes' => $diff->h * 60 + $diff->i
        ];
    }

    /**
     * Obtiene información del día actual
     */
    public function getCurrentDayInfo(): array
    {
        $currentTime = Carbon::now()->setTimezone('America/Argentina/Buenos_Aires');
        
        return [
            'date' => $currentTime->format('d/m/Y'),
            'time' => $currentTime->format('H:i:s'),
            'day_name' => $currentTime->format('l'),
            'day_name_es' => $this->getDayNameInSpanish($currentTime->dayOfWeek),
            'is_weekend' => $currentTime->isWeekend(),
            'is_holiday' => $this->isHoliday($currentTime)
        ];
    }

    /**
     * Convierte el nombre del día al español
     */
    private function getDayNameInSpanish(int $dayOfWeek): string
    {
        $days = [
            0 => 'Domingo',
            1 => 'Lunes', 
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];
        
        return $days[$dayOfWeek] ?? 'Desconocido';
    }

    /**
     * Verifica si es un día feriado (básico)
     */
    private function isHoliday(Carbon $date): bool
    {
        // Lista básica de feriados argentinos
        $holidays = [
            '01-01', // Año Nuevo
            '03-24', // Día de la Memoria
            '04-02', // Día del Veterano
            '05-01', // Día del Trabajador
            '05-25', // Día de la Revolución
            '06-20', // Día de la Bandera
            '07-09', // Día de la Independencia
            '08-17', // Día de San Martín
            '10-12', // Día del Respeto
            '11-20', // Día de la Soberanía
            '12-08', // Inmaculada Concepción
            '12-25'  // Navidad
        ];
        
        return in_array($date->format('m-d'), $holidays);
    }

    /**
     * Obtiene el horario de cierre de sorteos
     */
    public function getLotterySchedule(): array
    {
        return [
            'opens_at' => '09:00',
            'closes_at' => '19:00',
            'total_turns' => count($this->turns),
            'turns' => $this->turns
        ];
    }
}
