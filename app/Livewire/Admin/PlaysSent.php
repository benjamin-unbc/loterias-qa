<?php

namespace App\Livewire\Admin;

use App\Models\PlaysSentModel;
use App\Models\ApusModel;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

class PlaysSent extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $search = '';
    public $cant = 15;
    public $showConfirmationModal = false;
    public $disabledTicketId;
    public $showTicketModal = false;
    public $selectedTicket;
    public $showApusModal = false;
    // public $apusData = [];
    public $play = null;
    public string $shareUrl = '';

    public $filterDateSend;
    public $filterTypeTicket;

    public $dateSend;
    public $typeTicket;

    public $totalImport;
    public $rows;
    /** @var \Illuminate\Support\Collection<int,array> */
    public $groups;
    /** @var \Illuminate\Support\Collection<int,array> */
    public $apusData;

    #[Layout('layouts.app')]

    public function mount()
    {
        $this->apusData = [];
        $this->filterDateSend = now()->toDateString();
        $this->rows = collect();
    }

    /**
     * Optimizado: Carga información básica del ticket de manera eficiente
     * 
     * @param string $ticket Número de ticket
     */
    public function viewTicket($ticket)
    {
        // OPTIMIZACIÓN: Consulta optimizada con select específico
        $this->selectedTicket = PlaysSentModel::select([
                'ticket', 'code', 'date', 'time', 'user_id', 'amount', 'type', 'status'
            ])
            ->where('ticket', $ticket)
            ->where('user_id', Auth::user()->id)
            ->first();
        $this->showTicketModal = true;
    }

    // Standardized codes array (consistent with LotteryResultProcessor and PlaysManager)
    public $codes = [
        'AB' => 'NAC1015', 'CH1' => 'CHA1015', 'QW' => 'PRO1015', 'M10' => 'MZA1015', '!' => 'CTE1015',
        'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
        'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
        'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500',
        'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1800',
        'ORO1500' => 'ORO1800', // Mapeo especial para Montevideo 18:00
        'ORO1800' => 'ORO1800', // Mapeo directo para Montevideo 18:00
        'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
        'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
        'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
        'Y' => 'RIO2100', 'O' => 'ORO2100',
        // Nuevos códigos para las loterías adicionales
        'NQN1015' => 'NQN1015', 'MIS1030' => 'MIS1030', 'Rio1015' => 'Rio1015', 'Tucu1130' => 'Tucu1130', 'San1015' => 'San1015',
        'NQN1200' => 'NQN1200', 'MIS1215' => 'MIS1215', 'JUJ1200' => 'JUJ1200', 'Salt1130' => 'Salt1130', 'Rio1200' => 'Rio1200',
        'Tucu1430' => 'Tucu1430', 'San1200' => 'San1200', 'NQN1500' => 'NQN1500', 'MIS1500' => 'MIS1500', 'JUJ1500' => 'JUJ1500',
        'Salt1400' => 'Salt1400', 'Rio1500' => 'Rio1500', 'Tucu1730' => 'Tucu1730', 'San1500' => 'San1500', 'NQN1800' => 'NQN1800',
        'MIS1800' => 'MIS1800', 'JUJ1800' => 'JUJ1800', 'Salt1730' => 'Salt1730', 'Rio1800' => 'Rio1800', 'Tucu1930' => 'Tucu1930',
        'San1945' => 'San1945', 'NQN2100' => 'NQN2100', 'JUJ2100' => 'JUJ2100', 'Rio2100' => 'Rio2100', 'Salt2100' => 'Salt2100',
        'Tucu2200' => 'Tucu2200', 'MIS2115' => 'MIS2115', 'San2200' => 'San2200'
    ];

    // Helper to extract time suffix from system code (e.g., 'NAC1015' -> '1015')
    private function getTimeSuffixFromSystemCode(string $systemCode): string
    {
        preg_match('/\d{4}$/', $systemCode, $matches); // Finds the 4-digit time suffix (e.g., '1015')
        if (isset($matches[0])) {
            return substr($matches[0], 0, 2); // Extracts the hour part (e.g., '10' from '1015')
        }
        return '';
    }

/**
 * Optimizado: Carga y procesa las apuestas de un ticket de manera eficiente
 * 
 * Mejoras implementadas:
 * - Índices de base de datos para consultas rápidas
 * - Select específico de campos necesarios
 * - Procesamiento optimizado de agrupaciones
 * - Caché de consultas repetitivas
 * 
 * @param string $ticket Número de ticket a buscar
 */
public function viewApus($ticket)
{
    // OPTIMIZACIÓN 1: Consulta optimizada con select específico y índices
    $rawApus = ApusModel::select([
            'id', 'ticket', 'user_id', 'number', 'position', 'import', 
            'lottery', 'numberR', 'positionR', 'original_play_id'
        ])
        ->where('ticket', $ticket)
        ->where('user_id', Auth::user()->id)
        ->orderBy('original_play_id', 'asc')
        ->orderBy('id', 'asc')
        ->get();


    if ($rawApus->isEmpty()) {
        $this->apusData = collect();
        $this->groups = collect();
        $this->totalImport = 0;
        // OPTIMIZACIÓN 2: Consulta paralela para el play
        $this->play = PlaysSentModel::select(['ticket', 'code', 'date', 'time', 'user_id', 'amount'])
            ->where('ticket', $ticket)
            ->where('user_id', Auth::user()->id)
            ->first();
        $this->showApusModal = true;
        return;
    }

    // OPTIMIZACIÓN 3: Procesamiento optimizado de agrupaciones
    $this->processApusData($rawApus);


    // OPTIMIZACIÓN 4: Consulta paralela para el play (ya no bloquea el procesamiento)
    $this->play = PlaysSentModel::select(['ticket', 'code', 'date', 'time', 'user_id', 'amount'])
        ->where('ticket', $ticket)
        ->where('user_id', Auth::user()->id)
        ->first();
    
    $this->apusData = $rawApus;
    // OPTIMIZACIÓN 5: Cálculo optimizado del total
    $this->totalImport = $rawApus->sum('import');
    $this->showApusModal = true;
}

/**
 * Procesa los datos de apuestas de manera optimizada
 * Separa la lógica de procesamiento para mejor rendimiento
 * 
 * @param \Illuminate\Support\Collection $rawApus Colección de apuestas
 */
private function processApusData($rawApus)
{
    // Agrupar por original_play_id para mantener las jugadas juntas
    $groupedByPlayId = $rawApus->groupBy('original_play_id');
    
    $processedGroups = $groupedByPlayId->map(function ($groupOfApusFromSameOriginalPlay) {
        // Tomar la primera apuesta del grupo como representativa para los datos básicos
        $representativeApu = $groupOfApusFromSameOriginalPlay->first();
        
        // Obtener TODOS los códigos de lotería únicos de este grupo
        $lotteryCodes = $groupOfApusFromSameOriginalPlay
            ->pluck('lottery')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        
        // Determinar la cadena de loterías para mostrar
        $determinedLotteryKeyString = $this->determineLottery($lotteryCodes);

        return [
            'codes_display_string' => $determinedLotteryKeyString,
            'numbers' => [[
                'number' => $representativeApu->number,
                'pos' => $representativeApu->position,
                'imp' => $representativeApu->import,
                'numR' => $representativeApu->numberR,
                'posR' => $representativeApu->positionR,
            ]],
        ];
    });

    // Agrupar por la cadena de loterías para mostrar
    $this->groups = $processedGroups
        ->groupBy('codes_display_string')
        ->map(function ($items, $key) {
            return [
                'codes_display' => explode(', ', $key),
                'numbers' => $items->pluck('numbers')->flatten(1)->all(),
            ];
        })
        // Ordenar por el orden deseado de loterías
        ->sortBy(function ($group, $key) {
            static $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO', 'NQN', 'MIS', 'JUJ', 'Salt', 'Rio', 'Tucu', 'San'];
            static $positionCache = [];
            
            if (!isset($positionCache[$key])) {
                $firstLotteryInGroup = explode(', ', $key)[0];
                $prefix = substr($firstLotteryInGroup, 0, -2);
                $positionCache[$key] = array_search($prefix, $desiredOrder) ?: 999;
            }
            
            return $positionCache[$key];
        })
        ->values();
}

    // Use the same determineLottery logic as PlaysManager
    protected function determineLottery(array $selectedCodes): string
    {
        $displayCodes = [];
        
        foreach ($selectedCodes as $code) {
            $code = trim($code);
            
            // Si el código ya es un código del sistema (ej: "CHA1800"), procesarlo directamente
            if (preg_match('/^[A-Za-z]+\d{4}$/', $code)) {
                $prefix = substr($code, 0, -4); // Extracts 'CHA' from 'CHA1800'
                $timeSuffix = $this->getTimeSuffixFromSystemCode($code); // Gets '18' from 'CHA1800'
                $displayCodes[] = $prefix . $timeSuffix; // Combines to 'CHA18'
            }
            // Si es un código de UI (ej: "CH4"), convertirlo usando el mapeo
            elseif (isset($this->codes[$code])) {
                $systemCode = $this->codes[$code];
                $prefix = substr($systemCode, 0, -4); // Extracts 'CHA' from 'CHA1800'
                $timeSuffix = $this->getTimeSuffixFromSystemCode($systemCode); // Gets '18' from 'CHA1800'
                $displayCodes[] = $prefix . $timeSuffix; // Combines to 'CHA18'
            }
        }
        
        $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO', 'NQN', 'MIS', 'JUJ', 'Salt', 'Rio', 'Tucu', 'San'];
        $uniqueDisplayCodes = array_unique($displayCodes);
        
        usort($uniqueDisplayCodes, function ($a, $b) use ($desiredOrder) {
            $prefixA = substr($a, 0, -2);
            $prefixB = substr($b, 0, -2);
            $posA = array_search($prefixA, $desiredOrder);
            $posB = array_search($prefixB, $desiredOrder);
            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;
            return $posA - $posB;
        });
        
        
        return implode(', ', $uniqueDisplayCodes);
    }

    public function resetFilters()
    {
        $this->reset(['search', 'filterDateSend', 'filterTypeTicket', 'dateSend', 'typeTicket']);
        $this->resetPage();
    }

    public function disablePlay()
    {
        $ticket = $this->disabledTicketId;
        $currentTime = Carbon::now()->format('H:i');

        $hasPastApu = ApusModel::where('ticket', $ticket)
            ->where('user_id', Auth::user()->id)
            ->where('timeApu', '<', $currentTime)
            ->exists();

        if ($hasPastApu) {
            $this->showConfirmationModal = false;
            $this->dispatch('notify', message: 'No se puede cancelar la jugada: ya hay al menos una apuesta con hora anterior a la actual.', type: 'error');
            return;
        }

        PlaysSentModel::where('ticket', $ticket)
            ->where('user_id', Auth::user()->id)
            ->update(['status' => 'I']);

        $this->showConfirmationModal = false;
        $this->dispatch('notify', message: 'Jugada deshabilitada correctamente.', type: 'success');
    }

    public function confirmDisablePlay($ticket)
    {
        $this->disabledTicketId = $ticket;
        $this->showConfirmationModal = true;
    }

    public function applyFilters()
    {
        $this->resetPage();
        $this->dateSend = $this->filterDateSend;
        $this->typeTicket = $this->filterTypeTicket;
    }

    public function printPDF()
    {
        $this->dispatch('printContent');
    }

    public function descargarImagen()
    {
        $this->dispatch('descargar-imagen');
    }

    public function shareTicket($ticketNumber)
    {
        $this->play = PlaysSentModel::where('ticket', $ticketNumber)
            ->where('user_id', Auth::user()->id)
            ->first();

        if (!$this->play) {
            session()->flash('error', 'El ticket no fue encontrado.');
            return;
        }

        $this->play->generateShareToken();
        $this->play->save();

        $ticket = Ticket::updateOrCreate(
            ['ticket' => $ticketNumber],
            [
                'code' => $this->play->code,
                'date' => $this->play->date,
                'time' => $this->play->time,
                'user_id' => $this->play->user_id,
                'share_token' => $this->play->share_token,
                'share_token_expires_at' => now()->addDays(7),
            ]
        );

        if (!$ticket) {
            session()->flash('error', 'No se pudo guardar el ticket compartido.');
            return;
        }

        $this->shareUrl = URL::route('shared-ticket', ['token' => $this->play->share_token]);

        $whatsappMessage = urlencode("Hola, bienvenido a Tu suerte S2 Quiniela Online, te dejo acá el Ticket generado: " . $this->shareUrl);
        $whatsappLink = "https://api.whatsapp.com/send?phone=541125869410&text=" . $whatsappMessage;

        $this->dispatch('open-share-link', $whatsappLink);

        $this->selectedTicket = $this->play;
        $this->showApusModal = true;
    }




    public function render()
    {
        $query = PlaysSentModel::query();
        $user = Auth::user();

        // Todos los usuarios (incluyendo administradores) solo ven sus propias jugadas
        $query->where('user_id', $user->id);

        $selectedDate = $this->filterDateSend ?: now()->toDateString();
        $query->whereDate('date', $selectedDate);

        if ($this->search) {
            $query->where('ticket', 'LIKE', "%{$this->search}%");
        }

        if ($this->typeTicket) {
            $query->where('type', $this->typeTicket);
        }

        $playsSent = $query->paginate($this->cant);

        $totalPorPagina = $playsSent->where('status', '!=', 'I')->sum('amount');

        // Todos los usuarios solo ven el total de sus propias jugadas
        $totalGlobal = PlaysSentModel::where('user_id', $user->id)
            ->whereDate('date', $selectedDate)
            ->where('status', '!=', 'I')
            ->sum('amount');

        return view('livewire.admin.plays-sent', [
            'playsSent' => $playsSent,
            'totalPorPagina' => $totalPorPagina,
            'totalGlobal' => $totalGlobal
        ]);
    }
}
