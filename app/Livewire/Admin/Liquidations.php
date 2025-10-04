<?php

namespace App\Livewire\Admin;

use App\Models\Result; // Use the correct Result model
use App\Models\DailyLiquidation;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

class Liquidations extends Component
{
    use WithPagination;
    
    #[Layout('layouts.app')]

    public string $date;
    public int $cant = 15;

    public function mount()
    {
        $this->date = Carbon::yesterday()->format('Y-m-d');
    }

    public function printPDF()
    {
        $this->dispatch('printLiquidation');
    }

    public function descargarImagen()
    {
        $this->dispatch('downloadLiquidation');
    }

    /**
     * Calcula los datos de liquidación según el tipo de usuario
     * - Administradores: Ven liquidaciones globales del sistema
     * - Clientes: Ven solo sus liquidaciones individuales
     * 
     * @return array Datos de liquidación filtrados por usuario
     */
    protected function computeLiquidationData(): array
    {
        if (!$this->date) {
            $this->date = Carbon::yesterday()->format('Y-m-d');
        }
        
        $selectedDate = Carbon::parse($this->date);
        $user = auth()->user();
        
        // Determinar si es administrador o cliente
        $isAdmin = $user->hasAnyRole(['Administrador']);
        
        if ($isAdmin) {
            return $this->computeGlobalLiquidationData($selectedDate);
        } else {
            return $this->computeClientLiquidationData($user, $selectedDate);
        }
    }

    /**
     * Calcula liquidación global para administradores
     * Mantiene la lógica original del sistema
     * 
     * @param Carbon $selectedDate Fecha seleccionada
     * @return array Datos de liquidación global
     */
    protected function computeGlobalLiquidationData(Carbon $selectedDate): array
    {
        // Consulta global de resultados (sin filtro de usuario)
        $baseQuery = Result::query()->whereDate('date', $this->date);
        $results = (clone $baseQuery)->paginate($this->cant);
        $totalAciert = (float) (clone $baseQuery)->sum('aciert');
        
        // Consulta global de apuestas (sin filtro de usuario)
        $apusQuery = \App\Models\ApusModel::query()->whereDate('created_at', $this->date);
        $previaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus    = (float) (clone $apusQuery)->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus    = (float) (clone $apusQuery)->where('timeApu', '21:00')->sum('import');
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        $comision = $totalApus * 0.20;
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Buscar la liquidación diaria global más reciente anterior a la fecha actual
        $prevLiquidation = DailyLiquidation::where('date', '<', $this->date)
                                           ->orderBy('date', 'desc')
                                           ->first();
        $prevGenerDeja = $prevLiquidation ? (float) $prevLiquidation->ud_deja : 0;
        
        // Calcular arrastre global según día de la semana
        if ($selectedDate->isSaturday()) {
            $comiDejaSem = ($totalGanaPase + $prevGenerDeja) * 0.30;
            $udDeja = ($totalGanaPase + $prevGenerDeja) - $comiDejaSem;
            $arrastre = 0;
        } else {
            $comiDejaSem = null;
            $udDeja = $totalGanaPase + $prevGenerDeja;
            $arrastre = $udDeja;
        }
        
        return [
            'results'           => $results,
            'totalAciert'       => $totalAciert,
            'totalApus'         => $totalApus,
            'comision'          => $comision,
            'totalGanaPase'     => $totalGanaPase,
            'previaTotalApus'   => $previaTotalApus,
            'mananaTotalApus'   => $mananaTotalApus,
            'matutinaTotalApus' => $matutinaTotalApus,
            'tardeTotalApus'    => $tardeTotalApus,
            'nocheTotalApus'    => $nocheTotalApus,
            'anteri'            => $prevGenerDeja,
            'udRecibe'          => $totalAciert,
            'udDeja'            => $udDeja,
            'arrastre'          => $arrastre,
            'comi_deja_sem'     => $comiDejaSem,
        ];
    }

    /**
     * Calcula liquidación individual para clientes
     * Solo muestra datos del cliente específico
     * 
     * @param User $user Usuario cliente
     * @param Carbon $selectedDate Fecha seleccionada
     * @return array Datos de liquidación del cliente
     */
    protected function computeClientLiquidationData($user, Carbon $selectedDate): array
    {
        // Consulta de resultados filtrada por cliente
        $baseQuery = Result::query()->whereDate('date', $this->date)->where('user_id', $user->id);
        $results = (clone $baseQuery)->paginate($this->cant);
        $totalAciert = (float) (clone $baseQuery)->sum('aciert');
        
        // Consulta de apuestas filtrada por cliente
        $apusQuery = \App\Models\ApusModel::query()->whereDate('created_at', $this->date)->where('user_id', $user->id);
        $previaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus    = (float) (clone $apusQuery)->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus    = (float) (clone $apusQuery)->where('timeApu', '21:00')->sum('import');
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        $comision = $totalApus * 0.20;
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        
        // Para clientes, calcular arrastre basado en sus datos históricos
        // Buscar la última liquidación del cliente (si existe)
        $clientPrevLiquidation = $this->getClientPreviousLiquidation($user->id, $this->date);
        $prevClientDeja = $clientPrevLiquidation ? (float) $clientPrevLiquidation['ud_deja'] : 0;
        
        // Calcular arrastre individual del cliente
        if ($selectedDate->isSaturday()) {
            $comiDejaSem = ($totalGanaPase + $prevClientDeja) * 0.30;
            $udDeja = ($totalGanaPase + $prevClientDeja) - $comiDejaSem;
            $arrastre = 0;
        } else {
            $comiDejaSem = null;
            $udDeja = $totalGanaPase + $prevClientDeja;
            $arrastre = $udDeja;
        }
        
        return [
            'results'           => $results,
            'totalAciert'       => $totalAciert,
            'totalApus'         => $totalApus,
            'comision'          => $comision,
            'totalGanaPase'     => $totalGanaPase,
            'previaTotalApus'   => $previaTotalApus,
            'mananaTotalApus'   => $mananaTotalApus,
            'matutinaTotalApus' => $matutinaTotalApus,
            'tardeTotalApus'    => $tardeTotalApus,
            'nocheTotalApus'    => $nocheTotalApus,
            'anteri'            => $prevClientDeja,
            'udRecibe'          => $totalAciert,
            'udDeja'            => $udDeja,
            'arrastre'          => $arrastre,
            'comi_deja_sem'     => $comiDejaSem,
        ];
    }

    /**
     * Obtiene la liquidación anterior del cliente específico
     * Calcula basándose en los datos históricos del cliente
     * 
     * @param int $userId ID del usuario cliente
     * @param string $currentDate Fecha actual
     * @return array|null Datos de la liquidación anterior del cliente
     */
    protected function getClientPreviousLiquidation(int $userId, string $currentDate): ?array
    {
        // Buscar la fecha anterior con datos del cliente
        $previousDate = Carbon::parse($currentDate)->subDay();
        
        // Calcular liquidación del día anterior para este cliente específico
        $prevResultsQuery = Result::query()->whereDate('date', $previousDate)->where('user_id', $userId);
        $prevTotalAciert = (float) $prevResultsQuery->sum('aciert');
        
        $prevApusQuery = \App\Models\ApusModel::query()->whereDate('created_at', $previousDate)->where('user_id', $userId);
        $prevTotalApus = (float) $prevApusQuery->sum('import');
        
        if ($prevTotalApus == 0) {
            return null; // No hay datos del cliente en la fecha anterior
        }
        
        $prevComision = $prevTotalApus * 0.20;
        $prevTotalGanaPase = $prevTotalApus - $prevComision - $prevTotalAciert;
        
        // Para simplificar, asumimos que el cliente no tiene arrastre previo
        // En un sistema más complejo, se podría almacenar el arrastre por cliente
        $prevUdDeja = $prevTotalGanaPase;
        
        return [
            'ud_deja' => $prevUdDeja,
            'total_apus' => $prevTotalApus,
            'total_aciert' => $prevTotalAciert,
            'total_gana_pase' => $prevTotalGanaPase,
        ];
    }

    /**
     * Busca y calcula los datos de liquidación
     * Solo guarda liquidaciones globales para administradores
     * Los clientes solo ven sus datos sin guardar en la tabla global
     */
    public function search()
    {
        $this->resetPage();

        $data = $this->computeLiquidationData();
        $user = auth()->user();

        // Solo los administradores pueden guardar liquidaciones globales
        // Los clientes solo ven sus datos calculados en tiempo real
        if ($user->hasAnyRole(['Administrador'])) {
            DailyLiquidation::updateOrCreate(
                ['date' => $this->date],
                [
                    'total_apus'      => $data['totalApus'],
                    'comision'        => $data['comision'],
                    'total_aciert'    => $data['totalAciert'],
                    'total_gana_pase' => $data['totalGanaPase'],
                    'anteri'          => $data['anteri'],
                    'ud_recibe'       => $data['udRecibe'],
                    'ud_deja'         => $data['udDeja'],
                    'arrastre'        => $data['arrastre'],
                ]
            );
        }
    }

    public function resetFilter()
    {
        $this->date = date('Y-m-d');
        $this->resetPage();
    }

    public function render()
    {
        $data = $this->computeLiquidationData();
        return view('livewire.admin.liquidations', array_merge($data, [
            'date' => $this->date,
        ]));
    }
}
