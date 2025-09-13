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
        '10' => ['AB' => 'N', 'CH1' => 'CH', 'QW' => 'P', 'M10' => 'MZ', '!' => 'Ct', 'ER' => 'S', 'SD' => 'C', 'RT' => 'R'],
        '12' => ['Q' => 'N', 'CH2' => 'CH', 'W' => 'P', 'M1' => 'MZ', 'M' => 'Ct', 'R' => 'S', 'T' => 'C', 'K' => 'R'],
        '15' => ['A' => 'N', 'CH3' => 'CH', 'E' => 'P', 'M2' => 'MZ', 'Ct3' => 'Ct', 'D' => 'S', 'L' => 'C', 'J' => 'R', 'S' => 'O'],
        '18' => ['F' => 'N', 'CH4' => 'CH', 'B' => 'P', 'M3' => 'MZ', 'Z' => 'Ct', 'V' => 'S', 'H' => 'C', 'U' => 'R'],
        '21' => ['N' => 'N', 'CH5' => 'CH', 'P' => 'P', 'M4' => 'MZ', 'G' => 'Ct', 'I' => 'S', 'C' => 'C', 'Y' => 'R', 'O' => 'O'],
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
            foreach ($this->codes as $time => $codes) {
                if (array_key_exists($code, $codes)) {
                    $lottery = $codes[$code];
                    $matchedLotteries[] = "$lottery$time";
                }
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
