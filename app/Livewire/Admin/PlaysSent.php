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

    public function viewTicket($ticket)
    {
        $this->selectedTicket = PlaysSentModel::where('ticket', $ticket)->first();
        $this->showTicketModal = true;
    }

    // Standardized codes array (consistent with LotteryResultProcessor)
    public $codes = [
        'AB' => 'NAC1015', 'CH1' => 'CHA1015', 'QW' => 'PRO1015', 'M10' => 'MZA1015', '!' => 'CTE1015',
        'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
        'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
        'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500',
        'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1500',
        'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
        'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
        'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
        'Y' => 'RIO2100', 'O' => 'ORO2100'
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

 public function viewApus($ticket)
{
    $rawApus = ApusModel::where('ticket', $ticket)
        ->orderBy('original_play_id', 'asc')
        ->orderBy('id', 'asc')
        ->get();

    if ($rawApus->isEmpty()) {
        $this->apusData = collect();
        $this->groups = collect();
        $this->totalImport = 0;
        $this->play = PlaysSentModel::where('ticket', $ticket)->first();
        $this->showApusModal = true;
        return;
    }

    // --- INICIO DE LA MODIFICACIÓN ---

    // 1. Agrupar las jugadas por su ID original para asegurar que no se pierda ninguna.
    $this->groups = $rawApus->groupBy('original_play_id')
        ->map(function ($groupOfApusFromSameOriginalPlay) {
            // Tomar la primera apuesta del grupo como representativa de la jugada.
            $representativeApu = $groupOfApusFromSameOriginalPlay->first();
            
            // Recolectar todos los códigos de lotería de este grupo.
            $lotteryCodesForThisPlay = $groupOfApusFromSameOriginalPlay->pluck('lottery')->filter()->unique()->values();
            
            // Determinar la cadena de loterías ordenada para mostrar en el ticket.
            $determinedLotteryKeyString = $this->determineLottery($lotteryCodesForThisPlay->all());

            return [
                // Usamos la cadena de loterías ordenada como clave para la vista.
                'codes_display_string' => $determinedLotteryKeyString,
                // Mantenemos los datos de la jugada.
                'numbers' => [[
                    'number' => $representativeApu->number,
                    'pos' => $representativeApu->position,
                    'imp' => $representativeApu->import,
                    'numR' => $representativeApu->numberR, // Se asegura de incluir la redoblona.
                    'posR' => $representativeApu->positionR,
                ]],
            ];
        })
        // 2. Agrupar nuevamente por la cadena de loterías para la visualización final.
        ->groupBy('codes_display_string')
        ->map(function ($items, $key) {
            return [
                'codes_display' => explode(', ', $key),
                'numbers' => $items->pluck('numbers')->flatten(1)->all(),
            ];
        })
        // 3. Ordenar los grupos de loterías según el orden deseado.
        ->sortBy(function ($group, $key) {
            $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO'];
            $firstLotteryInGroup = explode(', ', $key)[0]; // Ej: "NAC10"
            $prefix = substr($firstLotteryInGroup, 0, -2); // Ej: "NAC"
            $position = array_search($prefix, $desiredOrder);
            return $position === false ? 999 : $position;
        })
        ->values();

    // --- FIN DE LA MODIFICACIÓN ---

    $this->play = PlaysSentModel::where('ticket', $ticket)->first();
    $this->apusData = $rawApus;
    // Siempre calcular el total de importes de las jugadas, sin importar el status del ticket
    $this->totalImport = $rawApus->sum(fn($r) => $r->import);
    $this->showApusModal = true;
}

    // Use the same determineLottery logic as PlaysManager
    protected function determineLottery(array $selectedUiCodes): string
    {
        $systemCodes = [];
        foreach ($selectedUiCodes as $uiCode) {
            $uiCode = trim($uiCode);
            if (isset($this->codes[$uiCode])) {
                $systemCodes[] = $this->codes[$uiCode];
            }
        }
        $displayCodes = [];
        foreach ($systemCodes as $systemCode) {
            $prefix = substr($systemCode, 0, -4); // Extracts 'NAC' from 'NAC1015'
            $timeSuffix = $this->getTimeSuffixFromSystemCode($systemCode); // Gets '10' from 'NAC1015'
            $displayCodes[] = $prefix . $timeSuffix; // Combines to 'NAC10'
        }
        $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO'];
        $uniqueDisplayCodes = array_unique($displayCodes);
        usort($uniqueDisplayCodes, function ($a, $b) use ($desiredOrder) {
            $prefixA = substr($a, 0, -2);
            $prefixB = substr($b, 0, -2);
            $posA = array_search($prefixA, $desiredOrder);
            $posB = array_search($prefixB, $desiredOrder);
            if ($posB === false) return -1;
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
            ->where('timeApu', '<', $currentTime)
            ->exists();

        if ($hasPastApu) {
            $this->showConfirmationModal = false;
            $this->dispatch('notify', message: 'No se puede cancelar la jugada: ya hay al menos una apuesta con hora anterior a la actual.', type: 'error');
            return;
        }

        PlaysSentModel::where('ticket', $ticket)
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
        $this->play = PlaysSentModel::where('ticket', $ticketNumber)->first();

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

        if (!$user->hasRole('Administrador')) {
            $query->where('user_id', $user->id);
        }

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

        if ($user->hasRole('Administrador')) {
            $totalGlobal = PlaysSentModel::whereDate('date', $selectedDate)
                ->where('status', '!=', 'I')
                ->sum('amount');
        } else {
            $totalGlobal = PlaysSentModel::where('user_id', $user->id)
                ->whereDate('date', $selectedDate)
                ->where('status', '!=', 'I')
                ->sum('amount');
        }

        return view('livewire.admin.plays-sent', [
            'playsSent' => $playsSent,
            'totalPorPagina' => $totalPorPagina,
            'totalGlobal' => $totalGlobal
        ]);
    }
}
