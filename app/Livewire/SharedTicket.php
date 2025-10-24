<?php

namespace App\Livewire;

use App\Models\ApusModel;
use App\Models\PlaysSentModel;
use App\Models\Ticket;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;

class SharedTicket extends Component
{
    #[Layout('layouts.ticket')]

    public $ticket;
    public $apusData = [];
    public $showApusModal = true;
    public $play = null;
    public string $shareUrl = '';
    public $user;
    public $selectedTicket;
    public $totalImport = 0;

    public $codes = [
        'AB' => 'NAC1015', 'CH1' => 'CHA1015', 'QW' => 'PRO1015', 'M10' => 'MZA1015', '!' => 'CTE1015',
        'ER' => 'SFE1015', 'SD' => 'COR1015', 'RT' => 'RIO1015', 'Q' => 'NAC1200', 'CH2' => 'CHA1200',
        'W' => 'PRO1200', 'M1' => 'MZA1200', 'M' => 'CTE1200', 'R' => 'SFE1200', 'T' => 'COR1200',
        'K' => 'RIO1200', 'A' => 'NAC1500', 'CH3' => 'CHA1500', 'E' => 'PRO1500', 'M2' => 'MZA1500',
        'Ct3' => 'CTE1500', 'D' => 'SFE1500', 'L' => 'COR1500', 'J' => 'RIO1500', 'S' => 'ORO1800',
        'ORO1500' => 'ORO1800', 'ORO1800' => 'ORO1800',
        'F' => 'NAC1800', 'CH4' => 'CHA1800', 'B' => 'PRO1800', 'M3' => 'MZA1800', 'Z' => 'CTE1800',
        'V' => 'SFE1800', 'H' => 'COR1800', 'U' => 'RIO1800', 'N' => 'NAC2100', 'CH5' => 'CHA2100',
        'P' => 'PRO2100', 'M4' => 'MZA2100', 'G' => 'CTE2100', 'I' => 'SFE2100', 'C' => 'COR2100',
        'Y' => 'RIO2100', 'O' => 'ORO2100',
        // Nuevos códigos cortos para las loterías adicionales
        'NQ1' => 'NQN1015', 'MI1' => 'MIS1030', 'RN1' => 'Rio1015', 'TU1' => 'Tucu1130', 'SG1' => 'San1015',
        'NQ2' => 'NQN1200', 'MI2' => 'MIS1215', 'JU1' => 'JUJ1200', 'SA1' => 'Salt1130', 'RN2' => 'Rio1200',
        'TU2' => 'Tucu1430', 'SG2' => 'San1200', 'NQ3' => 'NQN1500', 'MI3' => 'MIS1500', 'JU2' => 'JUJ1500',
        'SA2' => 'Salt1400', 'RN3' => 'Rio1500', 'TU3' => 'Tucu1730', 'SG3' => 'San1500', 'NQ4' => 'NQN1800',
        'MI4' => 'MIS1800', 'JU3' => 'JUJ1800', 'SA3' => 'Salt1730', 'RN4' => 'Rio1800', 'TU4' => 'Tucu1930',
        'SG4' => 'San1945', 'NQ5' => 'NQN2100', 'JU4' => 'JUJ2100', 'RN5' => 'Rio2100', 'SA4' => 'Salt2100',
        'TU5' => 'Tucu2200', 'MI5' => 'MIS2115', 'SG5' => 'San2200'
    ];

    public function mount($token)
    {
        $this->ticket = Ticket::where('share_token', $token)
            ->where(function ($query) {
                $query->whereNull('share_token_expires_at')
                    ->orWhere('share_token_expires_at', '>', now());
            })
            ->with(['user'])
            ->first();

        if (!$this->ticket) {
            abort(404, 'Ticket no encontrado o el enlace ha expirado.');
        }

        $this->user = $this->ticket->user;

        // Obtener el registro de jugada (play) según el ticket
        $this->play = PlaysSentModel::where('ticket', $this->ticket->ticket)->first();

        // Obtener las apuestas (apus) del ticket
        $this->apusData = ApusModel::where('ticket', $this->ticket->ticket)->get();

        // Para cada apuesta, asignar el nombre determinado de lotería
        foreach ($this->apusData as $apu) {
            $apu->determined_lottery = $this->determineLottery(explode(',', $apu->lottery));
        }

        // Calcular el total de cada apuesta y el total global
        $this->totalImport = 0;
        foreach ($this->apusData as $apu) {
            // Se cuenta la cantidad de loterías (filtrando elementos vacíos)
            $lotteryCount = count(array_filter(explode(',', $apu->lottery)));
            $apu->lottery_count = $lotteryCount;
            // Se calcula el total de la fila: importe * cantidad de loterías seleccionadas
            $apu->row_total = $apu->import * $lotteryCount;
            $this->totalImport += $apu->row_total;
        }
    }

    public function determineLottery(array $selectedCodes): string
    {
        $matchedLotteries = [];

        foreach ($selectedCodes as $code) {
            $code = trim($code);
            
            // Si el código ya es un código del sistema válido (ej: "CHA1800"), usarlo directamente
            if (preg_match('/^[A-Za-z]+\d{4}$/', $code)) {
                $matchedLotteries[] = $code;
            }
            // Si es un código corto (ej: "CH4"), convertirlo a código completo
            elseif (isset($this->codes[$code])) {
                $matchedLotteries[] = $this->codes[$code];
            }
        }

        return implode(', ', $matchedLotteries);
    }

    public function printPDF()
    {
        $this->dispatch('printContentTicket');
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

        $this->dispatch('open-share-link', $this->shareUrl);

        // Se muestra el modal de apuestas
        $this->selectedTicket = $this->play;
        $this->showApusModal = true;
    }

    public function render()
    {
        return view('livewire.shared-ticket', [
            'ticket'      => $this->ticket,
            'apusData'    => $this->apusData,
            'user'        => $this->user,
            'totalImport' => $this->totalImport,
        ]);
    }
}
