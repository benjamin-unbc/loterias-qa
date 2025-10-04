<?php

namespace App\Livewire\Admin;



use App\Models\ApusModel;

use App\Models\Play;

use App\Models\PlaysSentModel;

use App\Models\Ticket;

use Carbon\Carbon;

use Illuminate\Support\Facades\URL;

use Livewire\Attributes\Layout;

use Livewire\Component;

use Livewire\WithFileUploads;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;



class PlaysManager extends Component

{

    use WithFileUploads;



    #[Layout('layouts.app')]



    public $horaActual;

    public $horarios = ['10:15', '12:00', '15:00', '18:00', '21:00'];

    public $selected = [];

    public $checkboxCodes = [];



    public $number;

    public $position;

    public $initialDefaultImport = '';

    public $lastImportValue;



    public $import = '';



    public $numberR;

    public $positionR;

    public $isChecked = false;

    public $focusNumberInput = false;



    public Collection $rows;

    public $count = 0;

    public $editingRowId = null;

    public $inputsDisabled = false;



    public $currentBasePlayId = null;

    public $currentDerivedCount = 0;



    public $enterCount = 0;

    public $lastEnterTime;



    public $showRepeatModal = false;

    public $searchTicketNumber;

    public $searchTicketError = '';



    public $totalAmount;

    public $cachedTotal = 0; // Cache para el total
    public $needsTotalRecalculation = true; // Flag para recalcular total

    public $showTicketModal = false;

    public $showApusModal = false; // This is used for the ticket details modal

    public $selectedTicket;

    public $play;

    public $rowsTicket;

    public $totalImport;

    public string $shareUrl = '';

    public $groups;

    public $apusData; // This is used for the raw APU data in the modal

    public $raw; // This variable seems unused or redundant

    public $isSaving = false;



    public $codes = [

        '10:15' => ['AB', 'CH1', 'QW', 'M10', '!', 'ER', 'SD', 'RT'],

        '12:00' => ['Q', 'CH2', 'W', 'M1', 'M', 'R', 'T', 'K'],

        '15:00' => ['A', 'CH3', 'E', 'M2', 'Ct3', 'D', 'L', 'J', 'S'],

        '18:00' => ['F', 'CH4', 'B', 'M3', 'Z', 'V', 'H', 'U'],

        '21:00' => ['N', 'CH5', 'P', 'M4', 'G', 'I', 'C', 'Y', 'O'],

    ];



    // Standardized codes array (consistent with LotteryResultProcessor)

    public $codesTicket = [

        'AB' => 'NAC1015',
        'CH1' => 'CHA1015',
        'QW' => 'PRO1015',
        'M10' => 'MZA1015',
        '!' => 'CTE1015',

        'ER' => 'SFE1015',
        'SD' => 'COR1015',
        'RT' => 'RIO1015',
        'Q' => 'NAC1200',
        'CH2' => 'CHA1200',

        'W' => 'PRO1200',
        'M1' => 'MZA1200',
        'M' => 'CTE1200',
        'R' => 'SFE1200',
        'T' => 'COR1200',

        'K' => 'RIO1200',
        'A' => 'NAC1500',
        'CH3' => 'CHA1500',
        'E' => 'PRO1500',
        'M2' => 'MZA1500',

        'Ct3' => 'CTE1500',
        'D' => 'SFE1500',
        'L' => 'COR1500',
        'J' => 'RIO1500',
        'S' => 'ORO1500',

        'F' => 'NAC1800',
        'CH4' => 'CHA1800',
        'B' => 'PRO1800',
        'M3' => 'MZA1800',
        'Z' => 'CTE1800',

        'V' => 'SFE1800',
        'H' => 'COR1800',
        'U' => 'RIO1800',
        'N' => 'NAC2100',
        'CH5' => 'CHA2100',

        'P' => 'PRO2100',
        'M4' => 'MZA2100',
        'G' => 'CTE2100',
        'I' => 'SFE2100',
        'C' => 'COR2100',

        'Y' => 'RIO2100',
        'O' => 'ORO2100'

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



    protected function rules()

    {

        return [

            'number' => ['required', 'regex:/^[\d*]+$/'],

            'position' => 'nullable|numeric|min:1|max:20|digits_between:1,2', // agregado digits_between para limitar a 2 dígitos

            'import' => 'required|numeric|min:0.01',

            'numberR' => ['nullable', 'numeric', 'min:0', 'max:99', 'required_with:positionR'],

            'positionR' => ['nullable', 'numeric', 'min:1', 'max:20', 'digits_between:1,2', 'required_with:numberR'], // agregado digits_between para limitar a 2 dígitos

        ];
    }



    protected $messages = [

        'number.regex' => 'El campo "Número" solo permite números y asteriscos (*).',

        'position.digits_between' => 'El campo "Posición" debe tener entre 1 y 2 dígitos.', // mensaje agregado para nueva validación

        'positionR.digits_between' => 'El campo "Posición" de redoblona debe tener entre 1 y 2 dígitos.', // mensaje agregado para nueva validación

    ];



    protected $listeners = [

        'addRowWithDerived' => 'addRowWithDerived',

    ];



    public function mount()

    {

        $this->horaActual = Carbon::now()->setTimezone('America/Argentina/Buenos_Aires');

        $this->checkIfInputsShouldBeDisabled();

        $this->groups = collect();

        $this->apusData = collect();

        $this->selected = [];

        foreach ($this->horarios as $h) {

            $this->selected[$h] = false;

            foreach ($this->codes[$h] ?? [] as $colIdx => $codeValue) {

                $this->selected["{$h}_col_" . ($colIdx + 1)] = false;
            }

            if (in_array($h, ['15:00', '21:00'])) {

                $this->selected["{$h}_oro"] = false;
            }
        }

        $this->checkboxCodes = [];

        $this->rows = $this->getAndSortPlays();

        $this->initialDefaultImport = '';

        $this->lastImportValue = '';

        $this->import = '';

        $this->updateLotteryCount();
        $this->calculateTotal(); // Calcular total inicial
    }



    protected function getAndSortPlays(): Collection

    {

        return Play::where('user_id', auth()->id())

            ->select(['id', 'user_id', 'type', 'number', 'position', 'import', 'lottery', 'numberR', 'positionR', 'isChecked'])

            ->orderBy('id', 'asc')

            ->get()

            ->values();
    }



    public function updated($propertyName)

    {

        // Solo validar cuando se está guardando, no en cada cambio
        if ($this->isSaving) {
            if ($propertyName === 'number') {
                $this->validateOnly($propertyName, ['number' => ['required', 'regex:/^[\d*]+$/']]);
            }
        }
    }



    public function viewApus($ticket)

    {

        $this->play = PlaysSentModel::where('ticket', $ticket)->first();



        if (!$this->play) {

            $this->dispatch('notify', message: 'Error: Ticket principal no encontrado.', type: 'error');

            $this->showApusModal = false;

            return;
        }



        $rawApus = ApusModel::where('ticket', $ticket)

            ->orderBy('original_play_id', 'asc')

            ->orderBy('id', 'asc')

            ->get();



        if ($rawApus->isEmpty()) {

            $this->apusData = collect();

            $this->groups = collect();

            $this->totalAmount = (float) $this->play->pay;

            $this->showApusModal = true;

            return;
        }



        $reconstructedPlays = $rawApus->groupBy(function ($apu) {

            $playIdentifier = $apu->original_play_id ?? ('no_play_id_' . $apu->id);

            return $playIdentifier . '|' .

                ($apu->number ?? '') . '|' .

                ($apu->position ?? '') . '|' .

                ((string)($apu->import ?? '0.00')) . '|' .

                ($apu->numberR ?? '') . '|' .

                ($apu->positionR ?? '') . '|' .

                (int)($apu->isChecked ?? 0);
        })->map(function ($groupOfApusFromSameOriginalPlay) {

            $representativeApu = $groupOfApusFromSameOriginalPlay->first();

            $lotteryCodesForThisPlay = $groupOfApusFromSameOriginalPlay->pluck('lottery')->filter()->unique()->values();

            $determinedLotteryKeyString = $this->determineLottery($lotteryCodesForThisPlay->all());


            if ($determinedLotteryKeyString === '') {

                $determinedLotteryKeyString = 'Error Lotería';
            }



            return (object)[

                'original_play_id_ref' => $representativeApu->original_play_id,

                'number' => $representativeApu->number,

                'position' => $representativeApu->position,

                'import' => $representativeApu->import,

                'numberR' => $representativeApu->numberR,

                'positionR' => $representativeApu->positionR,

                'isChecked' => $representativeApu->isChecked ?? 0,

                'determined_lottery_display_string' => $determinedLotteryKeyString,

            ];
        })

            ->sortBy('original_play_id_ref')

            ->values();



        $this->groups = $reconstructedPlays->groupBy('determined_lottery_display_string')

            ->map(function ($playsInLotteryGroup, $determinedLotteryKeyString) {

                $codesArrayForDisplay = [];

                if (!empty($determinedLotteryKeyString) && $determinedLotteryKeyString !== 'Error Lotería') {

                    $codesArrayForDisplay = explode(', ', $determinedLotteryKeyString);
                } else {

                    $codesArrayForDisplay = [$determinedLotteryKeyString];
                }

                return [

                    'codes_display' => $codesArrayForDisplay,

                    'numbers' => collect($playsInLotteryGroup)->map(fn($play_obj) => [

                        'number' => $play_obj->number,

                        'pos' => $play_obj->position,

                        'imp' => $play_obj->import,

                        'numR' => $play_obj->numberR,

                        'posR' => $play_obj->positionR,

                    ])->values(),

                ];
            })->values();



        $this->apusData = $rawApus;

        $this->totalAmount = (float) $this->play->pay;

        $this->showApusModal = true;
    }



    protected function determineLottery(array $selectedUiCodes): string // This method takes UI codes

    {

        $systemCodes = [];

        foreach ($selectedUiCodes as $uiCode) {

            $uiCode = trim($uiCode);

            if (isset($this->codesTicket[$uiCode])) {

                $systemCodes[] = $this->codesTicket[$uiCode];
            }
        }


        $displayCodes = [];

        foreach ($systemCodes as $systemCode) {

            $prefix = substr($systemCode, 0, -4); // Extracts 'NAC' from 'NAC1015'

            $timeSuffix = $this->getTimeSuffixFromSystemCode($systemCode); // Gets '10' from 'NAC1015'

            $displayCodes[] = $prefix . $timeSuffix; // Combines to 'NAC10'

        }


        // Custom sort based on a predefined order

        $desiredOrder = ['NAC', 'CHA', 'PRO', 'MZA', 'CTE', 'SFE', 'COR', 'RIO', 'ORO'];

        $uniqueDisplayCodes = array_unique($displayCodes);



        usort($uniqueDisplayCodes, function ($a, $b) use ($desiredOrder) {

            // Extract the lottery prefix for sorting (e.g., 'NAC' from 'NAC10')

            $prefixA = substr($a, 0, -2);

            $prefixB = substr($b, 0, -2);



            $posA = array_search($prefixA, $desiredOrder);

            $posB = array_search($prefixB, $desiredOrder);



            if ($posB === false) return -1;



            return $posA - $posB;
        });



        return implode(', ', $uniqueDisplayCodes);
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

        // OPTIMIZACIÓN: Consulta optimizada con select específico
        $this->play = PlaysSentModel::select(['ticket', 'code', 'date', 'time', 'user_id', 'amount', 'share_token'])
            ->where('ticket', $ticketNumber)
            ->first();

        if (!$this->play) {

            session()->flash('error', 'El ticket no fue encontrado.');

            return;
        }

        if (method_exists($this->play, 'generateShareToken')) {

            $this->play->generateShareToken();

            $this->play->save();
        }

        Ticket::updateOrCreate(

            ['ticket' => $ticketNumber],

            [

                'code' => $this->play->code,

                'date' => $this->play->date,

                'time' => $this->play->time,

                'user_id' => $this->play->user_id,

                'share_token' => $this->play->share_token ?? null,

                'share_token_expires_at' => now()->addDays(7),

            ]

        );

        if (empty($this->play->share_token)) {

            session()->flash('error', 'No se pudo generar el enlace para compartir el ticket.');

            return;
        }

        $this->shareUrl = URL::route('shared-ticket', ['token' => $this->play->share_token]);

        $whatsappMessage = urlencode("Hola, bienvenido a Tu suerte S2 Quiniela Online, te dejo acá el Ticket generado: {$this->shareUrl}");

        $whatsappLink = "https://api.whatsapp.com/send?phone=541125869410&text={$whatsappMessage}";

        $this->dispatch('open-share-link', $whatsappLink);

        $this->selectedTicket = $this->play;

        $this->showTicketModal = true;
    }



    public function openRepeatModal()

    {

        $this->searchTicketNumber = '';

        $this->searchTicketError = '';

        $this->showRepeatModal = true;
    }



    public function openRepeatLoteriaModal()

    {

        $this->openRepeatModal();
    }



    public function searchTicket()

    {

        $this->searchTicketError = '';

        if (empty($this->searchTicketNumber)) {

            $this->searchTicketError = 'Por favor, ingrese un número de ticket.';

            return;
        }

        $ticketToSearch = trim($this->searchTicketNumber);



        $currentSelectedLotteriesOnForm = implode(',', array_filter($this->checkboxCodes));



        if (empty($currentSelectedLotteriesOnForm)) {

            $this->searchTicketError = 'Por favor, seleccione las loterías deseadas en el formulario principal antes de repetir un ticket.';

            return;
        }



        try {

            // OPTIMIZACIÓN 1: Consulta optimizada con select específico
            $playsSentOriginal = PlaysSentModel::select(['ticket', 'code', 'date', 'time', 'user_id', 'amount'])
                ->where('ticket', $ticketToSearch)
                ->first();

            if (!$playsSentOriginal) {

                $this->searchTicketError = 'El número de ticket ingresado no existe.';

                return;
            }



            // OPTIMIZACIÓN 2: Consulta optimizada con select específico y índices
            $apusDelTicket = ApusModel::select([
                    'id', 'ticket', 'user_id', 'number', 'position', 'import', 
                    'lottery', 'numberR', 'positionR', 'original_play_id'
                ])
                ->where('ticket', $ticketToSearch)
                ->orderBy('original_play_id', 'asc')
                ->orderBy('id', 'asc')
                ->get();



            if ($apusDelTicket->isEmpty()) {

                $this->searchTicketError = 'El ticket existe pero no tiene jugadas asociadas para repetir.';

                return;
            }



            Play::where('user_id', auth()->id())->delete();



            $jugadasParaCrear = $apusDelTicket->groupBy('original_play_id')

                ->map(function ($apusDeUnaPlayOriginal) use ($currentSelectedLotteriesOnForm) {

                    $apuRepresentativo = $apusDeUnaPlayOriginal->first();

                    return [

                        'user_id' => auth()->id(),

                        'type' => (!empty($apuRepresentativo->numberR) || !empty($apuRepresentativo->positionR)) ? 'R' : 'J',

                        'number' => $apuRepresentativo->number,

                        'position' => $apuRepresentativo->position,

                        'import' => $apuRepresentativo->import,

                        'lottery' => $currentSelectedLotteriesOnForm,

                        'numberR' => $apuRepresentativo->numberR,

                        'positionR' => $apuRepresentativo->positionR,

                        'isChecked' => $apuRepresentativo->isChecked ?? 0,

                    ];
                })

                ->filter()

                ->values();



            if ($jugadasParaCrear->isEmpty()) {

                $this->searchTicketError = 'No se encontraron jugadas válidas para repetir (posiblemente no había loterías seleccionadas en el formulario).';

                $this->rows = collect();

                return;
            }



            $nuevasPlaysCreadas = [];

            foreach ($jugadasParaCrear as $datosPlay) {

                try {

                    if (empty($datosPlay['number']) || is_null($datosPlay['import'])) {

                        continue;
                    }

                    $playCreada = Play::create($datosPlay);

                    $nuevasPlaysCreadas[] = $playCreada;
                } catch (\Exception $e) {

                    // Opcional: manejar el error de creación de una jugada individual

                }
            }



            if (empty($nuevasPlaysCreadas)) {

                $this->rows = collect();

                return;
            }



            $this->rows = $this->getAndSortPlays();
            $this->needsTotalRecalculation = true;
            $this->calculateTotal();
            if ($this->rows->count() > 0) {
                $lastRow = $this->rows->last();
                $this->dispatch('scroll-to-last-play', ['playId' => $lastRow->id]);
            }
            $this->dispatch('notify', message: 'Jugadas del ticket repetidas con las loterías actuales.', type: 'success');
            $this->showRepeatModal = false;
        } catch (\Exception $e) {

            $this->searchTicketError = 'Ocurrió un error inesperado al procesar el ticket.';
        }
    }



    public function checkIfInputsShouldBeDisabled()

    {

        // Lógica para deshabilitar inputs según horario si es necesario

    }



    public function toggle()

    {

        $this->isChecked = !$this->isChecked;
    }


// Función para cargar la jugada en modo edición
public function editRow($id)
{
    $row = Play::find($id);

    if ($row && $row->user_id == auth()->id()) {

        $this->editingRowId = $row->id;

        // Eliminar los asteriscos para mostrar solo el número limpio en el formulario
        $this->number = $this->removeAsterisks($row->number);
        $this->position = $row->position;
        $this->import = $row->import;
        $this->checkboxCodes = !empty($row->lottery) ? array_filter(explode(',', $row->lottery)) : [];
        $this->numberR = $row->numberR;
        $this->positionR = $row->positionR;
        $this->isChecked = (bool)$row->isChecked;

        $this->selected = [];
        foreach ($this->checkboxCodes as $code) {
            foreach ($this->codes as $time => $timeCodes) {
                if (is_array($timeCodes)) {
                    $col = array_search($code, $timeCodes);
                    if ($col !== false) $this->selected["{$time}_col_" . ($col + 1)] = true;
                }
            }
            if ($code === 'S') $this->selected["15:00_oro"] = true;
            elseif ($code === 'O') $this->selected["21:00_oro"] = true;
        }

        foreach ($this->horarios as $time) {
            if (!$this->isDisabled($time)) $this->updateRowMasterCheckbox($time);
        }

        $this->updateLotteryCount();

        $this->lastImportValue = $this->import;
    } else {
        $this->dispatch('notify', message: 'Jugada no encontrada o no tienes permiso para editarla.', type: 'error');
    }
}

// Función para eliminar los asteriscos al mostrar el número
private function removeAsterisks($number)
{
    return str_replace('*', '', $number); // Elimina todos los asteriscos
}

// Función para formatear el número con asteriscos antes de guardarlo
private function formatNumberWithAsterisks($number)
{
    if (strlen($number) < 4) {
        return str_pad($number, 4, '*', STR_PAD_LEFT); // Agrega asteriscos al inicio hasta completar 4 caracteres
    }

    return $number; // Si ya tiene 4 dígitos, no se le agregan asteriscos
}

// Función para actualizar la jugada (se utiliza cuando se edita)
public function updateRow()
{
    if (!$this->editingRowId) {
        $this->dispatch('notify', message: 'No hay jugada seleccionada para editar.', type: 'error');
        return;
    }

    $validatedData = $this->validate();

    if (empty($this->checkboxCodes) || empty(array_filter($this->checkboxCodes))) {
        $this->dispatch('notify', message: 'Debe seleccionar al menos una lotería.', type: 'warning');
        return;
    }

    try {
        $row = Play::where('id', $this->editingRowId)->where('user_id', auth()->id())->firstOrFail();

        $currentLotteryString = implode(',', array_filter($this->checkboxCodes));

        // Formatear el número con asteriscos
        $formattedNumber = $this->formatNumberWithAsterisks($validatedData['number']);

        $updateData = [
            'number' => $formattedNumber,
            'position' => $validatedData['position'] ?? 1,
            'import' => $validatedData['import'],
            'lottery' => $currentLotteryString,
            'numberR' => $validatedData['numberR'],
            'positionR' => $validatedData['positionR'],
            'isChecked' => $this->isChecked,
        ];

        $row->update($updateData);

        $this->lastImportValue = $validatedData['import'];

        $this->resetForm();

        $this->needsTotalRecalculation = true; // Marcar para recalcular total

        $this->dispatch('notify', message: 'Jugada actualizada.', type: 'success');

        $this->rows = $this->getAndSortPlays();
    } catch (\Exception $e) {
        $this->dispatch('notify', message: 'Error al actualizar.', type: 'error');
    }
}


    public function deleteAllRows()

    {

        try {

            $deletedRows = Play::where('user_id', auth()->id())->delete();

            if ($deletedRows > 0) {

                $this->resetForm();

                $this->rows = collect();

                $this->dispatch('notify', message: 'Todas las jugadas eliminadas.', type: 'success');
            } else {

                $this->dispatch('notify', message: 'No hay jugadas para eliminar.', type: 'info');
            }
        } catch (\Exception $e) {

            $this->dispatch('notify', message: 'Error al eliminar.', type: 'error');
        }
    }



    public function deleteAllCreatePlaysSent()

    {

        try {

            Play::where('user_id', auth()->id())->delete();
        } catch (\Exception $e) {

            // Silently fail or handle error without notifying user

        }
    }



    public function deleteRow($id)

    {

        try {

            $row = Play::where('id', $id)->where('user_id', auth()->id())->firstOrFail();

            $row->delete();

            if ($this->editingRowId == $id) $this->resetForm();

            $this->rows = $this->getAndSortPlays();

            $this->needsTotalRecalculation = true; // Marcar para recalcular total

            $this->dispatch('notify', message: 'Jugada eliminada.', type: 'success');
        } catch (\Exception $e) {

            $this->dispatch('notify', message: 'Error al eliminar.', type: 'error');
        }
    }


public function addRow()
{
    $validatedData = $this->validate();

    // Verificar si se ha seleccionado al menos una lotería
    if (empty($this->checkboxCodes) || empty(array_filter($this->checkboxCodes))) {
        $this->dispatch('notify', message: 'Debe seleccionar al menos una lotería.', type: 'warning');
        return;
    }

    try {
        $importeAGuardar = $validatedData['import'];
        $currentLotteryString = implode(',', array_filter($this->checkboxCodes));

        // Datos para crear la jugada
        $playDataToCreate = [
            'user_id' => auth()->id(),
            'type' => (!empty($validatedData['numberR']) || !empty($validatedData['positionR'])) ? 'R' : 'J',
            'number' => $this->formatNumber($validatedData['number']), // Número formateado
            'position' => $validatedData['position'] ?? 1,
            'import' => $importeAGuardar,
            'lottery' => $currentLotteryString,
            'numberR' => $validatedData['numberR'],
            'positionR' => $validatedData['positionR'],
            'isChecked' => $this->isChecked ?? false,
        ];

        // **Eliminamos la validación de duplicados** para permitir la creación de jugadas duplicadas.
        // No se verifica si la jugada ya existe, permitiendo que se creen múltiples jugadas con los mismos datos.

        // Crear la nueva jugada sin validación de duplicados
        $newPlay = Play::create($playDataToCreate);

        // Agregar la jugada recién creada a la lista de jugadas
        $this->rows->push($newPlay);

        // Mantener el último valor de importe para que aparezca en el campo
        $this->lastImportValue = $importeAGuardar;

        // Limpiar el formulario de entrada
        $this->resetFormAdd();

        // Mantener el importe en el campo de entrada
        $this->import = $this->lastImportValue;

        // Marcar que se necesita recalcular el total
        $this->needsTotalRecalculation = true;

        // Hacer scroll hacia la última jugada agregada
        $this->dispatch('scroll-to-last-play', ['playId' => $newPlay->id]);

        // Notificar que la jugada fue agregada exitosamente
        $this->dispatch('notify', message: 'Jugada agregada.', type: 'success');

        // Focar el siguiente input de número
        $this->dispatch('focusInput', ['selector' => '#number']);

        // Limpiar el ID de la jugada si estamos en modo edición
        if ($this->editingRowId) $this->editingRowId = null;
    } catch (\Exception $e) {
        // En caso de error, notificar el fallo
        $this->dispatch('notify', message: 'Error al agregar.', type: 'error');
    }
}



    public function saveRow()

    {

        if ($this->isSaving) {

            return;
        }



        try {

            // Validar solo al guardar, no en cada cambio
            $validatedData = $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('focus-on-input', id: 'number');
            throw $e;
        }



        $this->isSaving = true;



        if ($this->editingRowId) {
            try {
                $this->updateRow();
            } catch (\Exception $e) {
                $this->dispatch('focus-on-input', id: 'number');
                $this->isSaving = false;
                throw $e;
            }
        } else {
            try {
                $this->addRow();
            } catch (\Exception $e) {
                $this->dispatch('focus-on-input', id: 'number');
                $this->isSaving = false;
                throw $e;
            }
        }



        $this->reset('raw');
        $this->dispatch('play-saved');
        $this->isSaving = false;
        $this->dispatch('focus-on-input', id: 'number');
    }



    // 3. Reemplaza tu función handleEnter() existente con esta versión más simple y efectiva:

    public function handleEnter()

    {

        if ($this->isSaving) {

            return;
        }

        $currentTime = $this->getCurrentTimeInMilliseconds();

        // Protección más estricta: mínimo 1 segundo entre Enter válidos
        if ($this->enterCount === 0 || !$this->lastEnterTime || ($currentTime - $this->lastEnterTime > 1000)) {

            $this->enterCount = 1;

            $this->lastEnterTime = $currentTime;

            $this->saveRow();

            return;
        }

        // Si se detecta spam, no hacer nada (silenciosamente ignorar)
    }





    private function getCurrentTimeInMilliseconds(): int

    {

        return round(microtime(true) * 1000);
    }



    public function clearError($field)

    {

        $this->resetValidation($field);

        if ($field === 'searchTicketNumber') $this->searchTicketError = '';
    }



    public function getHorariosConEstado()

    {

        return collect($this->horarios)->map(function ($time) {

            $horaCarbon = Carbon::createFromFormat('H:i', $time, 'America/Argentina/Buenos_Aires');

            $isDisabled = $this->horaActual->gt($horaCarbon);

            if ($isDisabled) {

                if (isset($this->selected[$time]) && $this->selected[$time]) $this->selected[$time] = false;

                foreach ($this->codes[$time] ?? [] as $colIdx => $codeValue) {

                    $keyInSelected = "{$time}_col_" . ($colIdx + 1);

                    if (isset($this->selected[$keyInSelected]) && $this->selected[$keyInSelected]) $this->selected[$keyInSelected] = false;

                    $this->checkboxCodes = array_values(array_diff($this->checkboxCodes, [$codeValue]));
                }

                if (in_array($time, ['15:00', '21:00'])) {

                    $oroKeyInSelected = "{$time}_oro";

                    if (isset($this->selected[$oroKeyInSelected]) && $this->selected[$oroKeyInSelected]) $this->selected[$oroKeyInSelected] = false;

                    $oroCode = $time === '15:00' ? 'S' : 'O';

                    $this->checkboxCodes = array_values(array_diff($this->checkboxCodes, [$oroCode]));
                }
            }

            return ['time' => $time, 'disabledAttr' => $isDisabled ? 'disabled' : '', 'checkboxClass' => $isDisabled ? 'bg-zinc-700 cursor-not-allowed opacity-50' : 'cursor-pointer text-green-400 focus:ring-green-400', 'textClass' => $isDisabled ? 'text-gray-400 line-through' : 'text-gray-700 dark:text-gray-300', 'isDisabled' => $isDisabled];
        })->toArray();
    }



    public function toggleAllCheckboxes($checked)

    {

        $codesToUpdate = [];

        $anyCheckboxChanged = false;



        foreach ($this->horarios as $hora) {

            if ($this->isDisabled($hora)) continue;



            if ($this->selected[$hora] != $checked) {

                $this->selected[$hora] = $checked;

                $anyCheckboxChanged = true;
            }



            foreach ($this->codes[$hora] ?? [] as $colIdx => $codeValue) {

                $key = "{$hora}_col_" . ($colIdx + 1);

                if ($this->selected[$key] != $checked) {

                    $this->selected[$key] = $checked;

                    $anyCheckboxChanged = true;
                }

                $codesToUpdate[] = $codeValue;
            }



            if (in_array($hora, ['15:00', '21:00'])) {

                $oroKey = "{$hora}_oro";

                if (($this->selected[$oroKey] ?? false) != $checked) {

                    $this->selected[$oroKey] = $checked;

                    $anyCheckboxChanged = true;
                }

                $codesToUpdate[] = $hora === '15:00' ? 'S' : 'O';
            }
        }



        if ($checked) {

            $newCodes = array_values(array_unique(array_merge($this->checkboxCodes, $codesToUpdate)));

            if ($this->checkboxCodes != $newCodes) {

                $this->checkboxCodes = $newCodes;

                $anyCheckboxChanged = true;
            }
        } else {

            $newCodes = array_values(array_diff($this->checkboxCodes, $codesToUpdate));

            if ($this->checkboxCodes != $newCodes) {

                $this->checkboxCodes = $newCodes;

                $anyCheckboxChanged = true;
            }
        }


        if ($anyCheckboxChanged) {

            $this->updateLotteryCount();
        }
    }



    public function toggleRowCheckboxes($time, $checked)

    {

        if ($this->isDisabled($time)) return;


        $currentCodesInRow = $this->codes[$time] ?? [];

        $anyCheckboxChanged = false;



        if (in_array($time, ['15:00', '21:00'])) {

            $oroCode = $time === '15:00' ? 'S' : 'O';

            $currentCodesInRow[] = $oroCode;


            $oroKey = "{$time}_oro";

            if (($this->selected[$oroKey] ?? false) != $checked) {

                $this->selected[$oroKey] = $checked;

                $anyCheckboxChanged = true;
            }
        }



        foreach (array_keys($this->codes[$time] ?? []) as $index) {

            $key = "{$time}_col_" . ($index + 1);

            if (($this->selected[$key] ?? false) != $checked) {

                $this->selected[$key] = $checked;

                $anyCheckboxChanged = true;
            }
        }


        if ($checked) {

            $newCodes = array_values(array_unique(array_merge($this->checkboxCodes, $currentCodesInRow)));

            if ($this->checkboxCodes != $newCodes) {

                $this->checkboxCodes = $newCodes;

                $anyCheckboxChanged = true;
            }
        } else {

            $newCodes = array_values(array_diff($this->checkboxCodes, $currentCodesInRow));

            if ($this->checkboxCodes != $newCodes) {

                $this->checkboxCodes = $newCodes;

                $anyCheckboxChanged = true;
            }
        }


        if ($this->selected[$time] != $checked) {

            $this->selected[$time] = $checked;

            $anyCheckboxChanged = true;
        }



        if ($anyCheckboxChanged) {

            $this->updateLotteryCount();
        }
    }



    public function toggleColumnCheckbox($time, $col, $checked)

    {

        if ($this->isDisabled($time)) return;
        $code = $this->codes[$time][$col - 1] ?? null;
        if (!$code) return;


        $key = "{$time}_col_{$col}";

        $anyCheckboxChanged = false;



        if ($this->selected[$key] != $checked) {

            $this->selected[$key] = $checked;

            $anyCheckboxChanged = true;
        }



        if ($checked) {

            if (!in_array($code, $this->checkboxCodes)) {

                $this->checkboxCodes[] = $code;

                $this->checkboxCodes = array_values(array_unique($this->checkboxCodes));

                $anyCheckboxChanged = true;
            }
        } else {

            if (in_array($code, $this->checkboxCodes)) {

                $this->checkboxCodes = array_values(array_diff($this->checkboxCodes, [$code]));

                $anyCheckboxChanged = true;
            }
        }


        if ($anyCheckboxChanged) {

            $this->updateLotteryCount();

            $this->updateRowMasterCheckbox($time);
        }
    }



    public function toggleOroCheckbox($time, $checked)

    {

        if (!in_array($time, ['15:00', '21:00']) || $this->isDisabled($time)) return;


        $oroCode = $time === '15:00' ? 'S' : 'O';

        $key = "{$time}_oro";

        $anyCheckboxChanged = false;



        if (($this->selected[$key] ?? false) != $checked) {

            $this->selected[$key] = $checked;

            $anyCheckboxChanged = true;
        }



        if ($checked) {

            if (!in_array($oroCode, $this->checkboxCodes)) {

                $this->checkboxCodes[] = $oroCode;

                $this->checkboxCodes = array_values(array_unique($this->checkboxCodes));

                $anyCheckboxChanged = true;
            }
        } else {

            if (in_array($oroCode, $this->checkboxCodes)) {

                $this->checkboxCodes = array_values(array_diff($this->checkboxCodes, [$oroCode]));

                $anyCheckboxChanged = true;
            }
        }


        if ($anyCheckboxChanged) {

            $this->updateLotteryCount();

            $this->updateRowMasterCheckbox($time);
        }
    }



    private function updateRowMasterCheckbox($time)

    {

        if (!array_key_exists($time, $this->selected)) $this->selected[$time] = false;

        if ($this->isDisabled($time)) {
            if ($this->selected[$time]) $this->selected[$time] = false;
            return;
        }

        $allCheckedInRow = true;

        foreach (array_keys($this->codes[$time] ?? []) as $index) {

            $colKey = "{$time}_col_" . ($index + 1);

            if (empty($this->selected[$colKey] ?? false)) {
                $allCheckedInRow = false;
                break;
            }
        }

        if ($allCheckedInRow && in_array($time, ['15:00', '21:00'])) {

            $oroKey = "{$time}_oro";

            if (empty($this->selected[$oroKey] ?? false)) $allCheckedInRow = false;
        }

        if (($this->selected[$time] ?? false) != $allCheckedInRow) $this->selected[$time] = $allCheckedInRow;
    }



    private function isDisabled($time): bool

    {

        try {

            $hora = Carbon::createFromFormat('H:i', $time, 'America/Argentina/Buenos_Aires');

            return $this->horaActual->gt($hora);
        } catch (\Exception $e) {

            return true;
        }
    }



    private function updateLotteryCount()

    {

        $this->checkboxCodes = array_values(array_unique(array_filter($this->checkboxCodes)));

        $this->count = count($this->checkboxCodes);
    }



    public function resetFormAdd()

    {

        $this->resetValidation(['number', 'position', 'import', 'numberR', 'positionR']);

        $this->reset(['number', 'position', 'numberR', 'positionR', 'isChecked']);

        $this->import = '';

        $this->editingRowId = null;
    }



    public function resetForm()

    {

        $this->resetValidation();

        $this->reset([

            'number',
            'position',
            'import',

            'numberR',
            'positionR',
            'isChecked',

            'editingRowId',

            'checkboxCodes',

            'selected',

        ]);


        foreach ($this->horarios as $h) {

            $this->selected[$h] = false;

            foreach ($this->codes[$h] ?? [] as $colIdx => $codeValue) {

                $this->selected["{$h}_col_" . ($colIdx + 1)] = false;
            }

            if (in_array($h, ['15:00', '21:00'])) {

                $this->selected["{$h}_oro"] = false;
            }
        }

        $this->checkboxCodes = [];



        $this->import = '';

        $this->lastImportValue = '';

        $this->updateLotteryCount();
    }


    public function formatNumber($number): string

    {

        $numberStr = (string)$number;

        $hasAsterisk = strpos($numberStr, '*') !== false;



        if ($hasAsterisk) {

            if (strlen($numberStr) > 4) $numberStr = substr($numberStr, -4);

            while (strlen($numberStr) < 4) {

                $numberStr = '*' . $numberStr;
            }

            return $numberStr;
        }



        $cleanNumberStr = preg_replace('/[^\d]/', '', $numberStr);

        $len = strlen($cleanNumberStr);



        if ($len == 0) return '****';

        if ($len == 1) return '***' . $cleanNumberStr;

        if ($len == 2) return '**' . $cleanNumberStr;

        if ($len == 3) return '*' . $cleanNumberStr;

        if ($len == 4) return $cleanNumberStr;


        return substr($cleanNumberStr, -4);
    }



    public function sendPlays()

    {

        $plays = $this->rows;

        if ($plays->isEmpty()) {

            $this->dispatch('notify', message: 'No hay jugadas para enviar.', type: 'info');

            return;
        }



        DB::beginTransaction();
        try {
            $playsCount = $plays->count();
            
            // Optimización: usar una sola consulta para obtener el siguiente ticket
            $nextTicketNumber = DB::select("SELECT COALESCE(MAX(CAST(ticket AS UNSIGNED)), 0) + 1 as next_ticket FROM plays_sent")[0]->next_ticket;
            $ticket = str_pad($nextTicketNumber, 5, '0', STR_PAD_LEFT);

            $uniqueCodeForTicket = $this->generateUniqueCode();

            // Crear ticket y plays_sent en paralelo
            $ticketCreated = Ticket::create([
                'ticket' => $ticket,
                'code' => $uniqueCodeForTicket,
                'date' => now()->toDateString(),
                'time' => now()->format('H:i:s'),
                'user_id' => auth()->id()
            ]);

            $this->totalAmount = $this->calculateTotal();
            
            // Optimización: preparar todos los datos antes de insertar
            $apusToInsert = [];
            $now = now();
            $lotteryCodesForAllPlays = [];
            
            foreach ($plays as $play_calc) {
                $lotteryCodesForThisPlay = !empty($play_calc->lottery) ? array_filter(explode(',', $play_calc->lottery)) : [];
                $lotteryCodesForAllPlays = array_merge($lotteryCodesForAllPlays, $lotteryCodesForThisPlay);
                
                foreach ($lotteryCodesForThisPlay as $singleLotteryCode) {
                    $apusToInsert[] = [
                        'ticket' => $ticket,
                        'user_id' => auth()->id(),
                        'original_play_id' => $play_calc->id,
                        'number' => $play_calc->number,
                        'position' => $play_calc->position,
                        'import' => $play_calc->import,
                        'lottery' => trim($singleLotteryCode),
                        'numberR' => $play_calc->numberR,
                        'positionR' => $play_calc->positionR,
                        'isChecked' => $play_calc->isChecked,
                        'timeApu' => $this->getTimeApuFromLottery(trim($singleLotteryCode)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $type = $plays->contains(fn($play) => !empty($play->numberR) || !empty($play->positionR)) ? 'R' : 'J';
            $uniqueLotteryCodes = array_unique($lotteryCodesForAllPlays);

            $playsSent = PlaysSentModel::create([
                'ticket' => $ticket,
                'user_id' => auth()->id(),
                'time' => now()->format('H:i:s'),
                'timePlay' => $this->getTimePlayFromPlays($plays),
                'type' => $type,
                'apu' => $playsCount,
                'lot' => implode(',', $uniqueLotteryCodes),
                'pay' => $this->totalAmount,
                'amount' => $this->totalAmount,
                'date' => now()->toDateString(),
                'code' => $uniqueCodeForTicket,
            ]);

            if ($playsSent && $ticketCreated) {
                // Optimización: eliminar y insertar en una sola operación
                ApusModel::where('ticket', $ticket)->delete();
                if (!empty($apusToInsert)) {
                    ApusModel::insert($apusToInsert);
                }
                
                $this->dispatch('notify', message: "Apuestas enviadas. Ticket: {$ticket}", type: 'success');
                $this->deleteAllCreatePlaysSent();
                $this->rows = collect();
                $this->resetForm();
                $this->needsTotalRecalculation = true;
                $this->viewApus($ticket);
            } else {
                DB::rollBack();
                $this->dispatch('notify', message: 'Error al guardar apuestas.', type: 'error');
                return;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('notify', message: 'Error al guardar apuestas.', type: 'error');
        }
    }



    private function getTimePlayFromPlays(Collection $plays): string

    {

        $allLotteryCodesFromPlays = $plays->pluck('lottery')

            ->map(fn($lotteryString) => !empty($lotteryString) ? explode(',', $lotteryString) : [])

            ->flatten()

            ->map(fn($code) => trim($code))

            ->filter()

            ->unique();



        $selectedTimes = [];

        foreach ($allLotteryCodesFromPlays as $code) {

            if ($code === 'S') {

                $selectedTimes[] = '15:00';

                continue;
            }

            if ($code === 'O') {

                $selectedTimes[] = '21:00';

                continue;
            }

            foreach ($this->codes as $time => $codesArray) {

                if (in_array($code, $codesArray)) {

                    $selectedTimes[] = $time;

                    break;
                }
            }
        }

        return implode(',', array_unique($selectedTimes));
    }



    private function getTimeApuFromLottery($lotteryUiCode): string

    {

        if ($lotteryUiCode === 'S') return '15:00';

        if ($lotteryUiCode === 'O') return '21:00';



        foreach ($this->codes as $time => $uiCodesInTime) {

            if (in_array($lotteryUiCode, $uiCodesInTime)) {

                return $time;
            }
        }

        return '';
    }



    public function addRowWithDerived()
{
    $basePlay = Play::where('user_id', auth()->id())
        ->whereRaw('LENGTH(REPLACE(number, "*", "")) IN (3,4)')
        ->orderBy('id', 'desc')
        ->first();

    if (!$basePlay) {
        $this->dispatch('notify', message: 'Debe haber una jugada principal de 3 o 4 dígitos para generar derivadas.', type: 'error');
        return;
    }

    $cleanBaseNumber = str_replace('*', '', $basePlay->number);

    if (!ctype_digit($cleanBaseNumber) || !in_array(strlen($cleanBaseNumber), [3, 4])) {
        $this->dispatch('notify', message: 'La jugada principal para derivar debe tener 3 o 4 dígitos numéricos (sin contar asteriscos).', type: 'error');
        return;
    }

    if ($this->currentBasePlayId !== $basePlay->id) {
        $this->currentBasePlayId = $basePlay->id;
        $this->currentDerivedCount = 0;
    }

    $allDerivedRaw = $this->getSpecialDerivedNumbers($cleanBaseNumber);
    $derivedNumbersToCreate = array_slice($allDerivedRaw, 1);

    if (empty($derivedNumbersToCreate)) {
        $this->dispatch('notify', message: 'No hay jugadas derivadas definidas para este número base.', type: 'info');
        return;
    }

    if ($this->currentDerivedCount >= count($derivedNumbersToCreate)) {
        $this->dispatch('notify', message: 'Ya se crearon todas las jugadas derivadas posibles para la base actual.', type: 'info');
        return;
    }

    $newDerivedNumberFormatted = $derivedNumbersToCreate[$this->currentDerivedCount];

    // **Eliminamos la validación de duplicados** para las jugadas derivadas
    // Esto permite que se cree una jugada derivada duplicada sin ningún problema.
    $newPlay = Play::create([
        'user_id' => auth()->id(),
        'type' => $basePlay->type,
        'number' => $newDerivedNumberFormatted,
        'position' => $basePlay->position,
        'import' => $basePlay->import,
        'lottery' => $basePlay->lottery,
        'numberR' => $basePlay->numberR,
        'positionR' => $basePlay->positionR,
        'isChecked' => $basePlay->isChecked,
    ]);

    $this->currentDerivedCount++;
    $this->rows = $this->getAndSortPlays();
    $this->needsTotalRecalculation = true;
    $this->dispatch('scroll-to-last-play', ['playId' => $newPlay->id]);
    $this->dispatch('notify', message: "Jugada derivada '{$newDerivedNumberFormatted}' creada correctamente.", type: 'success');
    $this->dispatch('focusInput', ['selector' => '#number']);
}


    private function getSpecialDerivedNumbers($cleanOriginalNumber): array

    {

        $derived = [];

        $derived[] = $this->formatNumber($cleanOriginalNumber);



        if (strlen($cleanOriginalNumber) === 3) {

            $derived[] = $this->formatNumber(substr($cleanOriginalNumber, 1, 2));
        } else if (strlen($cleanOriginalNumber) === 4) {

            $derived[] = $this->formatNumber(substr($cleanOriginalNumber, 1, 3));

            $derived[] = $this->formatNumber(substr($cleanOriginalNumber, 2, 2));
        }

        return array_unique($derived);
    }



    private function generateUniqueCode(): string

    {

        do {

            $code = bin2hex(random_bytes(12));
        } while (PlaysSentModel::where('code', $code)->exists() || Ticket::where('code', $code)->exists());

        return $code;
    }



    public function nuevoTicket()

    {

        $this->resetForm();

        $this->showTicketModal = false;

        $this->showApusModal = false;


        $this->rows = $this->getAndSortPlays();
    }

    public function focusNextInput($nextInputId)

    {

        // Valida que el campo actual (número) no esté vacío antes de avanzar.

        if (empty($this->number)) {

            $this->dispatch('focus-on-input', id: 'number');

            return;
        }



        $this->dispatch('focus-on-input', id: $nextInputId);
    }

    public function render()

    {

        $horariosConEstado = $this->getHorariosConEstado();

        $mainPlay = null;

        if ($this->rows instanceof Collection && !$this->rows->isEmpty()) {

            $mainPlay = $this->rows->first(function ($play) {

                if (!$play || !isset($play->number)) return false;

                $numberStr = (string)$play->number;

                return strlen(str_replace('*', '', $numberStr)) === 4;
            });
        }

        // Calcular total solo si es necesario
        $total = $this->calculateTotal();

        return view('livewire.admin.plays-manager', [

            'horariosConEstado' => $horariosConEstado,

            'rows' => $this->rows,

            'mainNumber' => $mainPlay ? $mainPlay->number : null,

            'total' => $total, // Pasar el total calculado

        ]);
    }

    // Método optimizado para calcular total
    private function calculateTotal()
    {
        if (!$this->needsTotalRecalculation) {
            return $this->cachedTotal;
        }
        
        $this->cachedTotal = $this->rows->sum(function($row) {
            $lotteryCount = !empty($row->lottery) ? count(array_filter(explode(',', $row->lottery))) : 0;
            return (float)$row->import * $lotteryCount;
        });
        
        $this->needsTotalRecalculation = false;
        return $this->cachedTotal;
    }
}
