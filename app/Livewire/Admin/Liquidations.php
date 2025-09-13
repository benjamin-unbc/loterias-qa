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

    protected function computeLiquidationData(): array
    {
        if (!$this->date) {
            $this->date = Carbon::yesterday()->format('Y-m-d');
        }
        $selectedDate = Carbon::parse($this->date);

        $baseQuery = Result::query()->whereDate('date', $this->date);
        $results = (clone $baseQuery)->paginate($this->cant);
        $totalAciert = (float) (clone $baseQuery)->sum('aciert');
        
        $apusQuery = \App\Models\ApusModel::query()->whereDate('created_at', $this->date);
        $previaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '10:15')->sum('import');
        $mananaTotalApus   = (float) (clone $apusQuery)->where('timeApu', '12:00')->sum('import');
        $matutinaTotalApus = (float) (clone $apusQuery)->where('timeApu', '15:00')->sum('import');
        $tardeTotalApus    = (float) (clone $apusQuery)->where('timeApu', '18:00')->sum('import');
        $nocheTotalApus    = (float) (clone $apusQuery)->where('timeApu', '21:00')->sum('import');
        $totalApus = $previaTotalApus + $mananaTotalApus + $matutinaTotalApus + $tardeTotalApus + $nocheTotalApus;
        
        $comision = $totalApus * 0.20;
        $totalGanaPase = $totalApus - $comision - $totalAciert;
        // Buscar la liquidación diaria más reciente estrictamente anterior a la fecha actual ($this->date)
        $prevLiquidation = DailyLiquidation::where('date', '<', $this->date)
                                           ->orderBy('date', 'desc')
                                           ->first();
        $prevGenerDeja = $prevLiquidation ? (float) $prevLiquidation->ud_deja : 0;
        
        if ($selectedDate->isSaturday()) {
            $comiDejaSem = ($totalGanaPase + $prevGenerDeja) * 0.30;
            $udDeja = ($totalGanaPase + $prevGenerDeja) - $comiDejaSem;
            $arrastre = 0;
        } else {
            $comiDejaSem = null;
            $udDeja = $totalGanaPase + $prevGenerDeja;
            $arrastre = $udDeja;
        }
        $anteri = $prevGenerDeja;
        
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
            'anteri'            => $anteri,
            'udRecibe'          => $totalAciert,
            'udDeja'            => $udDeja,
            'arrastre'          => $arrastre,
            'comi_deja_sem'     => $comiDejaSem,
        ];
    }

    public function search()
    {
        $this->resetPage();

        $data = $this->computeLiquidationData();

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
