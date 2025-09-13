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
        if (!$user->hasAnyRole(['Administrador'])) {
            $playsSentQuery->where('user_id', $user->id);
        }
        $totalImporte = (float) $playsSentQuery->sum('amount');

        // 2. Total Aciertos (Suma de los premios a pagar)
        // Se calcula desde la tabla de resultados (Result) sumando la columna 'aciert'.
        $resultsQueryForTotals = Result::query()->whereDate('date', $this->date);
        if (!$user->hasAnyRole(['Administrador'])) {
            $resultsQueryForTotals->where('user_id', $user->id);
        }
        $totalAciertos = (float) $resultsQueryForTotals->sum('aciert');

        // --- CONSULTA PARA LA TABLA PAGINADA ---
        // Se agrupan los resultados para no mostrar tickets repetidos y sumar correctamente los aciertos por jugada.
        $resultsQuery = Result::query()
            ->select(
                'ticket',
                'number',
                'position',
                'numR',
                'posR',
                'import',
                'user_id',
                'date',
                DB::raw('SUM(aciert) as aciert'),
                DB::raw('GROUP_CONCAT(DISTINCT lottery SEPARATOR ", ") as lottery')
            )
            ->whereDate('date', $this->date);

        if (!$user->hasAnyRole(['Administrador'])) {
            $resultsQuery->where('user_id', $user->id);
        }
        $results = $resultsQuery->groupBy('ticket', 'number', 'position', 'numR', 'posR', 'import', 'user_id', 'date')
            ->paginate($this->cant);
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
        $this->date = date('Y-m-d');
        $this->resetPage();
        // Livewire se encarga de volver a renderizar automáticamente.
    }
}