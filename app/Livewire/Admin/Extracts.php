<?php

namespace App\Livewire\Admin;

use App\Models\City;
use App\Jobs\CalculateLotteryResults; // Importar el Job
use App\Models\Extract;
use App\Models\Number;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Añadir esta línea

class Extracts extends Component
{
    #[Layout('layouts.app')]

    public $cities;
    public $isAdmin;
    public $extracts;
    public $showApusModal = false;
    public $showFullExtract = false;
    public $action = '';
    public $filterDate;
    public $selectedDate;
    public $indexData;

    public function mount()
    {
        $this->filterDate = \Carbon\Carbon::today()->toDateString();
        $this->selectedDate = $this->filterDate; // Inicializar selectedDate
        $this->cities    = City::with('numbers')->get();
        $this->isAdmin   = Auth::user()->hasRole('Administrador');
        $this->extracts  = Extract::all();
        $this->loadData(); // Cargar datos con la fecha inicial correcta
    }

    public function searchDate()
    {
        $this->selectedDate = $this->filterDate;
        $this->loadData(); // Recargar datos con la nueva fecha seleccionada
    }

    public function resetFilters()
    {
        $today = Carbon::today()->toDateString();
        $this->filterDate   = $today;
        $this->selectedDate = $today;
        $this->loadData();
    }

    public function loadData()
    {
        // Asegurarse que selectedDate tenga un valor antes de usarlo en la consulta.
        // Si selectedDate está vacío por alguna razón, usar la fecha actual como fallback.
        $dateForQuery = $this->selectedDate ?: Carbon::today()->toDateString();
        
        $this->cities = City::with(['numbers' => function ($query) use ($dateForQuery) {
            $query->where('date', $dateForQuery);
        }])->get();
    }

    public function storeNumber($cityId, $extractId, $index, $value)
    {
        $dateToStore = $this->filterDate ?: Carbon::today()->toDateString(); // Usar la fecha del filtro

        // Verificar si ya existe un registro para ese índice, ciudad y extract en la fecha actual.
        $existing = Number::where('city_id', $cityId)
            ->where('extract_id', $extractId)
            ->where('index', $index)
            ->where('date', $dateToStore)
            ->first();

        if ($existing) {
            $this->dispatch('notify', message: "El campo $index ya fue registrado para la fecha seleccionada.", type: 'warning');
            return;
        }

        // Crear el nuevo registro
        $this->indexData = Number::create([
            'city_id'    => $cityId,
            'extract_id' => $extractId,
            'index'      => $index,
            'value'      => $value,
            'date'       => $dateToStore,
        ]);

        // PREMIACIÓN INMEDIATA: Buscar jugadas premiadas y crear Result
        $city = \App\Models\City::find($cityId);
        if ($city) {
            $lotteryCode = $city->code;
            $cityInitial = substr($lotteryCode, 0, 1);
            \Log::info('Premiación rápida: buscando jugadas', [
                'lotteryCode' => $lotteryCode,
                'cityInitial' => $cityInitial,
                'index' => $index,
                'value' => $value,
                'date' => $dateToStore
            ]);
            $plays = \App\Models\ApusModel::whereIn('lottery', [$lotteryCode, $cityInitial])
                ->where('position', $index)
                ->where('number', $value)
                ->where('timeApu', $city->time)
                ->get();
            \Log::info('Jugadas encontradas', ['count' => $plays->count(), 'ids' => $plays->pluck('id')]);
            $premiados = [];
            foreach ($plays as $play) {
                $resultData = [
                    'user_id'    => $play->user_id,
                    'ticket'     => $play->ticket,
                    'lottery'    => $play->lottery,
                    'number'     => $play->number,
                    'position'   => $play->position,
                    'numero_g'   => null,
                    'posicion_g' => null,
                    'numR'       => $play->numberR,
                    'posR'       => $play->positionR,
                    'num_g_r'    => null,
                    'pos_g_r'    => null,
                    'XA'         => null,
                    'import'     => $play->import,
                    'aciert'     => $play->import,
                    'date'       => isset($this->indexData) ? $this->indexData->date : $dateToStore,
                    'time'       => $play->timeApu,
                ];
                \Log::info('Intentando crear Result', $resultData);
                try {
                    $result = \App\Models\Result::create($resultData);
                    \Log::info('Result creado', ['id' => $result->id]);
                } catch (\Exception $e) {
                    \Log::error('Error al crear Result', ['error' => $e->getMessage()]);
                }
                $premiados[] = $play->ticket;
            }
            if (count($premiados) > 0) {
                $this->dispatch('notify', message: '¡Tickets premiados: ' . implode(', ', $premiados) . '!', type: 'success');
            }
        }

        // Actualizar los datos para refrescar la vista
        $this->loadData(); // Recargar datos para la fecha actual del filtro

    // Log para confirmar que se intenta despachar el Job
    Log::info("Extracts - Intentando despachar CalculateLotteryResults para fecha: " . $dateToStore);

        // Disparar el Job para calcular los resultados
        // CalculateLotteryResults::dispatch($dateToStore); // Desactivado para no borrar resultados inmediatos

        $this->dispatch('notify', message: "Valor registrado correctamente en el campo $index.", type: 'success');
    }

    public function printPDF()
    {
        $this->dispatch('printContent');
    }

    public function descargarImagen()
    {
        $this->dispatch('descargar-imagen');
    }

    public function showModal($action)
    {
        $this->action = $action; // Guardar la acción seleccionada
        $this->showApusModal = true;
    }

    public function toggleExtractView()
    {
        $this->showFullExtract = !$this->showFullExtract;
    }

    public function updateNumber($numeroId, $newValue)
    {
        $numero = Number::findOrFail($numeroId);
        $numero->value = $newValue;
        $numero->save();

        // PREMIACIÓN INMEDIATA: Buscar jugadas premiadas y crear Result
        $city = $numero->city;
        if ($city) {
            $lotteryCode = $city->code;
            $cityInitial = substr($lotteryCode, 0, 1);
            \Log::info('Premiación rápida (update): buscando jugadas', [
                'lotteryCode' => $lotteryCode,
                'cityInitial' => $cityInitial,
                'index' => $numero->index,
                'value' => $newValue,
                'date' => $numero->date
            ]);
            $plays = \App\Models\ApusModel::whereIn('lottery', [$lotteryCode, $cityInitial])
                ->where('position', $numero->index)
                ->where('number', $newValue)
                ->where('timeApu', $city->time)
                ->get();
            \Log::info('Jugadas encontradas (update)', ['count' => $plays->count(), 'ids' => $plays->pluck('id')]);
            $premiados = [];
            foreach ($plays as $play) {
                $resultData = [
                    'user_id'    => $play->user_id,
                    'ticket'     => $play->ticket,
                    'lottery'    => $play->lottery,
                    'number'     => $play->number,
                    'position'   => $play->position,
                    'numero_g'   => null,
                    'posicion_g' => null,
                    'numR'       => $play->numberR,
                    'posR'       => $play->positionR,
                    'num_g_r'    => null,
                    'pos_g_r'    => null,
                    'XA'         => null,
                    'import'     => $play->import,
                    'aciert'     => $play->import,
                    'date'       => $numero->date,
                    'time'       => $play->timeApu,
                ];
                \Log::info('Intentando crear Result', $resultData);
                try {
                    $result = \App\Models\Result::create($resultData);
                    \Log::info('Result creado', ['id' => $result->id]);
                } catch (\Exception $e) {
                    \Log::error('Error al crear Result', ['error' => $e->getMessage()]);
                }
                $premiados[] = $play->ticket;
            }
            if (count($premiados) > 0) {
                $this->dispatch('notify', message: '¡Tickets premiados: ' . implode(', ', $premiados) . '!', type: 'success');
            }
        }

    // Log para confirmar que se intenta despachar el Job después de actualizar
    Log::info("Extracts - Intentando despachar CalculateLotteryResults (update) para fecha: " . $numero->date);

        // Disparar el Job después de actualizar también
        // CalculateLotteryResults::dispatch($numero->date); // Desactivado para no borrar resultados inmediatos

        $this->loadData(); // Recargar datos para asegurar que la vista se actualice si es necesario
        $this->dispatch('notify', message: 'Valor de extracto actualizado.', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.extracts', [
            'cities' => $this->cities,
            'isAdmin' => $this->isAdmin,
            'extracts' => $this->extracts,
        ]);
    }
}
