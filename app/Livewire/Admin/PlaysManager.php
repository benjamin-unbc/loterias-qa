<?php

namespace App\Livewire\Admin;



use App\Models\ApusModel;

use App\Models\Play;

use App\Models\PlaysSentModel;

use App\Models\Ticket;

use App\Models\City;

use App\Models\Extract;

use App\Models\GlobalQuinielasConfiguration;

use Carbon\Carbon;

use Illuminate\Support\Facades\URL;

use Livewire\Attributes\Layout;

use Livewire\Component;

use Livewire\WithFileUploads;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;



class PlaysManager extends Component

{

    use WithFileUploads;



    #[Layout('layouts.app')]



    public $horaActual;

    public $horarios = []; // Se cargará dinámicamente desde la BD

    public $selected = [];

    public $checkboxCodes = [];
    
    // Nuevas propiedades para manejar todas las loterías
    public $allLotteries = []; // Todas las loterías con sus horarios
    public $lotteryGroups = []; // Loterías agrupadas por horario
    public $uiCodeMapping = []; // Mapeo de códigos UI a códigos de BD
    public $citySchedules = []; // Horarios por ciudad (para lógica por defecto)
    



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
    
    public $isCreatingDerived = false;



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



    // El array $codes ya no se usa - se reemplazó por lotteryGroups dinámico



    // Mapeo de códigos del sistema a códigos cortos para mostrar
    public $systemToShortCodes = [
        'NAC1015' => 'AB', 'CHA1015' => 'CH1', 'PRO1015' => 'QW', 'MZA1015' => 'M10', 'CTE1015' => '!',
        'SFE1015' => 'ER', 'COR1015' => 'SD', 'RIO1015' => 'RT', 'NAC1200' => 'Q', 'CHA1200' => 'CH2',
        'PRO1200' => 'W', 'MZA1200' => 'M1', 'CTE1200' => 'M', 'SFE1200' => 'R', 'COR1200' => 'T',
        'RIO1200' => 'K', 'NAC1500' => 'A', 'CHA1500' => 'CH3', 'PRO1500' => 'E', 'MZA1500' => 'M2',
        'CTE1500' => 'Ct3', 'SFE1500' => 'D', 'COR1500' => 'L', 'RIO1500' => 'J', 'ORO1800' => 'S',
        'NAC1800' => 'F', 'CHA1800' => 'CH4', 'PRO1800' => 'B', 'MZA1800' => 'M3', 'CTE1800' => 'Z',
        'SFE1800' => 'V', 'COR1800' => 'H', 'RIO1800' => 'U', 'NAC2100' => 'N', 'CHA2100' => 'CH5',
        'PRO2100' => 'P', 'MZA2100' => 'M4', 'CTE2100' => 'G', 'SFE2100' => 'I', 'COR2100' => 'C',
        'RIO2100' => 'Y', 'ORO2100' => 'O',
        // Nuevos códigos cortos para las loterías adicionales
        'NQN1015' => 'NQ1', 'MIS1030' => 'MI1', 'Rio1015' => 'RN1', 'Tucu1130' => 'TU1', 'San1015' => 'SG1',
        'NQN1200' => 'NQ2', 'MIS1215' => 'MI2', 'JUJ1200' => 'JU1', 'Salt1130' => 'SA1', 'Rio1200' => 'RN2',
        'Tucu1430' => 'TU2', 'San1200' => 'SG2', 'NQN1500' => 'NQ3', 'MIS1500' => 'MI3', 'JUJ1500' => 'JU2',
        'Salt1400' => 'SA2', 'Rio1500' => 'RN3', 'Tucu1730' => 'TU3', 'San1500' => 'SG3', 'NQN1800' => 'NQ4',
        'MIS1800' => 'MI4', 'JUJ1800' => 'JU3', 'Salt1730' => 'SA3', 'Rio1800' => 'RN4', 'Tucu1930' => 'TU4',
        'San1945' => 'SG4', 'NQN2100' => 'NQ5', 'JUJ2100' => 'JU4', 'Rio2100' => 'RN5', 'Salt2100' => 'SA4',
        'Tucu2200' => 'TU5', 'MIS2115' => 'MI5', 'San2200' => 'SG5'
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
        'S' => 'ORO1800', // Corregido: ORO1500 -> ORO1800
        'ORO1500' => 'ORO1800', // Mapeo especial para Montevideo 18:00
        'ORO1800' => 'ORO1800', // Mapeo directo para Montevideo 18:00

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
        'O' => 'ORO2100',

        // Nuevos códigos para las loterías adicionales
        'NQN1015' => 'NQN1015',
        'MIS1030' => 'MIS1030',
        'Rio1015' => 'Rio1015',
        'Tucu1130' => 'Tucu1130',
        'San1015' => 'San1015',
        'NQN1200' => 'NQN1200',
        'MIS1215' => 'MIS1215',
        'JUJ1200' => 'JUJ1200',
        'Salt1130' => 'Salt1130',
        'Rio1200' => 'Rio1200',
        'Tucu1430' => 'Tucu1430',
        'San1200' => 'San1200',
        'NQN1500' => 'NQN1500',
        'MIS1500' => 'MIS1500',
        'JUJ1500' => 'JUJ1500',
        'Salt1400' => 'Salt1400',
        'Rio1500' => 'Rio1500',
        'Tucu1730' => 'Tucu1730',
        'San1500' => 'San1500',
        'NQN1800' => 'NQN1800',
        'MIS1800' => 'MIS1800',
        'JUJ1800' => 'JUJ1800',
        'Salt1730' => 'Salt1730',
        'Rio1800' => 'Rio1800',
        'Tucu1930' => 'Tucu1930',
        'San1945' => 'San1945',
        'NQN2100' => 'NQN2100',
        'JUJ2100' => 'JUJ2100',
        'Rio2100' => 'Rio2100',
        'Salt2100' => 'Salt2100',
        'Tucu2200' => 'Tucu2200',
        'MIS2115' => 'MIS2115',
        'San2200' => 'San2200'

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

            'position' => ['nullable', 'numeric', 'in:1,5,10,20'], // Solo permite posiciones 1, 5, 10 y 20

            'import' => 'required|numeric|min:0.01',

            'numberR' => ['nullable', 'numeric', 'min:0', 'max:99', 'required_with:positionR'],

            'positionR' => ['nullable', 'numeric', 'in:1,5,10,20', 'required_with:numberR'], // Solo permite posiciones 1, 5, 10 y 20

        ];
    }



    protected $messages = [

        'number.regex' => 'El campo "Número" solo permite números y asteriscos (*).',

        'position.in' => 'El campo "Posición" solo permite los valores: 1, 5, 10 y 20.', // Solo posiciones permitidas

        'positionR.in' => 'El campo "Posición" de redoblona solo permite los valores: 1, 5, 10 y 20.', // Solo posiciones permitidas

        'numberR.redoblona_validation' => 'La redoblona solo se puede con números de 2 cifras.',

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

        // Cargar todas las loterías y horarios desde la base de datos
        $this->loadAllLotteriesAndSchedules();

        $this->checkboxCodes = [];
        
        // Inicializar rows como colección vacía
        $this->rows = collect();
        
        $this->updateLotteryCount();
        $this->calculateTotal(); // Calcular total inicial
    }



    /**
     * Carga todas las loterías y horarios desde la base de datos
     */
    protected function loadAllLotteriesAndSchedules()
    {
        // Obtener todas las ciudades con sus extractos (horarios)
        $cities = City::with('extract')
            ->orderBy('extract_id')
            ->orderBy('name')
            ->get();

        // Agrupar por horario (con mapeo especial para Montevideo)
        $this->lotteryGroups = $cities->groupBy(function($city) {
            // Mapeo especial para Montevideo: 18:00 se agrupa como 15:00
            if ($city->name === 'MONTEVIDEO' && $city->time === '18:00') {
                return '15:00';
            }
            return $city->time;
        })->map(function($citiesInTime) {
            return $citiesInTime->map(function($city) {
                // Mapeo especial para Montevideo
                if ($city->name === 'MONTEVIDEO') {
                    // Si es Montevideo a las 18:00, mostrarlo como 15:00 pero con código ORO1800
                    if ($city->time === '18:00') {
                        return [
                            'id' => $city->id,
                            'name' => $city->name,
                            'code' => $city->code, // Mantener ORO1800
                            'time' => '15:00', // Mostrar como 15:00
                            'extract_name' => $city->extract->name,
                            'ui_code' => $this->generateUICode($city->name, '15:00'),
                            'abbreviation' => $this->generateAbbreviation($city->name, $city->extract->name)
                        ];
                    }
                    // Si es Montevideo a las 21:00, mantenerlo normal
                    if ($city->time === '21:00') {
                        return [
                            'id' => $city->id,
                            'name' => $city->name,
                            'code' => $city->code,
                            'time' => $city->time,
                            'extract_name' => $city->extract->name,
                            'ui_code' => $this->generateUICode($city->name, $city->time),
                            'abbreviation' => $this->generateAbbreviation($city->name, $city->extract->name)
                        ];
                    }
                    // Si es Montevideo en cualquier otro horario, no mostrarlo
                    return null;
                }
                
                // Para todas las demás ciudades, mantener normal
                return [
                    'id' => $city->id,
                    'name' => $city->name,
                    'code' => $city->code,
                    'time' => $city->time,
                    'extract_name' => $city->extract->name,
                    'ui_code' => $this->generateUICode($city->name, $city->time),
                    'abbreviation' => $this->generateAbbreviation($city->name, $city->extract->name)
                ];
            })->filter(); // Filtrar los valores null
        });

        // Obtener todos los horarios únicos ordenados
        $this->horarios = $this->lotteryGroups->keys()->sort()->values()->toArray();
        
        // Cargar horarios por ciudad para lógica por defecto
        $this->citySchedules = [];
        foreach ($cities->groupBy('name') as $cityName => $cityData) {
            $schedules = $cityData->pluck('time')->unique()->sort()->values()->toArray();
            
            // Filtrar visualmente el horario 18:00 de Montevideo
            if ($cityName === 'MONTEVIDEO') {
                $schedules = array_filter($schedules, function($time) {
                    return $time !== '18:00';
                });
                $schedules = array_values($schedules); // Reindexar el array
            }
            
            $this->citySchedules[$cityName] = $schedules;
        }
        

        // Ordenar las loterías dentro de cada horario por el orden específico solicitado: NAC, CHA, PRO, MZA, CTE, SFE, COR, RIO, ORO
        $desiredOrder = ['CIUDAD', 'CHACO', 'PROVINCIA', 'MENDOZA', 'CORRIENTES', 'SANTA FE', 'CORDOBA', 'ENTRE RIOS', 'MONTEVIDEO'];
        
        foreach ($this->lotteryGroups as $time => &$lotteries) {
            // Convertir Collection a array para usar usort
            $lotteriesArray = $lotteries->toArray();
            usort($lotteriesArray, function($a, $b) use ($desiredOrder) {
                $posA = array_search($a['name'], $desiredOrder);
                $posB = array_search($b['name'], $desiredOrder);
                if ($posA === false) $posA = 999;
                if ($posB === false) $posB = 999;
                return $posA - $posB;
            });
            // Convertir de vuelta a Collection
            $lotteries = collect($lotteriesArray);
        }

        // Crear mapeo de códigos UI a códigos de BD
        $this->uiCodeMapping = [];
        foreach ($cities as $city) {
            // Mapeo especial para Montevideo
            if ($city->name === 'MONTEVIDEO') {
                if ($city->time === '18:00') {
                    // Para Montevideo 18:00, usar código ORO1800 pero UI code con 15:00
                    $uiCode = $this->generateUICode($city->name, '15:00');
                    $this->uiCodeMapping[$uiCode] = $city->code; // ORO1800
                } elseif ($city->time === '21:00') {
                    // Para Montevideo 21:00, mantener normal
                    $uiCode = $this->generateUICode($city->name, $city->time);
                    $this->uiCodeMapping[$uiCode] = $city->code;
                }
                // Ignorar otros horarios de Montevideo
            } else {
                // Para todas las demás ciudades, mantener normal
                $uiCode = $this->generateUICode($city->name, $city->time);
                $this->uiCodeMapping[$uiCode] = $city->code;
            }
        }

        // Inicializar selecciones
        foreach ($this->horarios as $time) {
            $this->selected[$time] = false;
            
            if (isset($this->lotteryGroups[$time])) {
                foreach ($this->lotteryGroups[$time] as $index => $lottery) {
                    $this->selected["{$time}_col_" . ($index + 1)] = false;
                }
            }
        }
    }

    /**
     * Genera un código UI único para cada lotería basado en su nombre y horario
     */
    protected function generateUICode($cityName, $time)
    {
        // Mapeo de nombres de ciudades a códigos cortos
        $cityCodes = [
            'CIUDAD' => 'NAC',
            'SANTA FE' => 'SFE', 
            'PROVINCIA' => 'PRO',
            'ENTRE RIOS' => 'RIO',
            'CORDOBA' => 'COR',
            'CORRIENTES' => 'CTE',
            'CHACO' => 'CHA',
            'NEUQUEN' => 'NQN',
            'MISIONES' => 'MIS',
            'MENDOZA' => 'MZA',
            'Río Negro' => 'Rio',
            'Tucuman' => 'Tucu',
            'Santiago' => 'San',
            'JUJUY' => 'JUJ',
            'SALTA' => 'Salt',
            'MONTEVIDEO' => 'ORO',
            'SAN LUIS' => 'SLU',
            'CHUBUT' => 'CHU',
            'FORMOSA' => 'FOR',
            'CATAMARCA' => 'CAT',
            'SAN JUAN' => 'SJU'
        ];

        $cityCode = $cityCodes[$cityName] ?? substr($cityName, 0, 3);
        
        // Mapeo especial para Montevideo: 15:00 debe generar ORO1800
        if ($cityName === 'MONTEVIDEO' && $time === '15:00') {
            return 'ORO1800';
        }
        
        $timeCode = str_replace(':', '', $time);
        return $cityCode . $timeCode;
    }

    /**
     * Genera una abreviación para mostrar en la interfaz
     */
    protected function generateAbbreviation($cityName, $extractName)
    {
        // Mapeo específico para las 9 loterías principales con abreviaciones exactas
        $specificAbbreviations = [
            'CIUDAD' => 'NAC',
            'CHACO' => 'CHA',
            'PROVINCIA' => 'PRO',
            'MENDOZA' => 'MZA',
            'CORRIENTES' => 'CTE',
            'SANTA FE' => 'SFE',
            'CORDOBA' => 'COR',
            'ENTRE RIOS' => 'RIO',
            'MONTEVIDEO' => 'ORO'
        ];
        
        // Si es una de las 9 loterías principales, usar la abreviación específica
        if (isset($specificAbbreviations[$cityName])) {
            return $specificAbbreviations[$cityName];
        }
        
        // Para las demás loterías, mantener las abreviaciones actuales
        $cityAbbreviations = [
            'NEUQUEN' => 'NEU',
            'MISIONES' => 'MIS',
            'Río Negro' => 'RNE',
            'Tucuman' => 'TUC',
            'Santiago' => 'SGO',
            'JUJUY' => 'JUJ',
            'SALTA' => 'SAL',
            'SAN LUIS' => 'SLU',
            'CHUBUT' => 'CHU',
            'FORMOSA' => 'FOR',
            'CATAMARCA' => 'CAT',
            'SAN JUAN' => 'SJU'
        ];

        return $cityAbbreviations[$cityName] ?? substr($cityName, 0, 3);
    }


    protected function getAndSortPlays(): Collection

    {
        // MEJORA: Agregar validación adicional de seguridad
        $currentUserId = auth()->id();
        
        if (!$currentUserId) {
            \Log::error("Usuario no autenticado al obtener jugadas");
            return collect();
        }

        $plays = Play::where('user_id', $currentUserId)
            ->select(['id', 'user_id', 'type', 'number', 'position', 'import', 'lottery', 'numberR', 'positionR', 'isChecked'])
            ->orderBy('id', 'asc')
            ->get()
            ->values();
            
        // MEJORA: Validar que todas las jugadas pertenecen al usuario actual
        $invalidPlays = $plays->filter(function($play) use ($currentUserId) {
            return $play->user_id !== $currentUserId;
        });
        
        if ($invalidPlays->isNotEmpty()) {
            \Log::error("Jugadas de otros usuarios encontradas", [
                'current_user_id' => $currentUserId,
                'invalid_play_ids' => $invalidPlays->pluck('id')->toArray(),
                'invalid_user_ids' => $invalidPlays->pluck('user_id')->unique()->toArray()
            ]);
            
            // Filtrar solo las jugadas válidas
            $plays = $plays->filter(function($play) use ($currentUserId) {
                return $play->user_id === $currentUserId;
            })->values();
        }
        
        return $plays;
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

                    'codes_display' => $this->formatLotteryCodesForDisplay($codesArrayForDisplay),

                    'numbers' => collect($playsInLotteryGroup)->map(fn($play_obj) => [

                        'number' => $play_obj->number,

                        'pos' => $play_obj->position,

                        'imp' => $play_obj->import,

                        'numR' => $play_obj->numberR,

                        'posR' => $play_obj->positionR,

                    ])->sortBy('number')->values(),

                ];
            })->values();



        $this->apusData = $rawApus;

        $this->totalAmount = (float) $this->play->pay;

        $this->showApusModal = true;
    }



    protected function determineLottery(array $selectedUiCodes): string // This method takes UI codes

    {

        $displayCodes = [];

        foreach ($selectedUiCodes as $uiCode) {

            $uiCode = trim($uiCode);

            // Usar el nuevo mapeo dinámico
            if (isset($this->uiCodeMapping[$uiCode])) {

                $systemCode = $this->uiCodeMapping[$uiCode];

                // Usar código completo directamente
                $displayCodes[] = $systemCode;

            }
        }


        // Custom sort based on a predefined order (usando códigos completos)

        $desiredOrder = ['NAC1015', 'CHA1015', 'PRO1015', 'MZA1015', 'CTE1015', 'SFE1015', 'COR1015', 'RIO1015', 'NAC1200', 'CHA1200', 'PRO1200', 'MZA1200', 'CTE1200', 'SFE1200', 'COR1200', 'RIO1200', 'NAC1500', 'CHA1500', 'PRO1500', 'MZA1500', 'CTE1500', 'SFE1500', 'COR1500', 'RIO1500', 'ORO1800', 'NAC1800', 'CHA1800', 'PRO1800', 'MZA1800', 'CTE1800', 'SFE1800', 'COR1800', 'RIO1800', 'NAC2100', 'CHA2100', 'PRO2100', 'MZA2100', 'CTE2100', 'SFE2100', 'COR2100', 'RIO2100', 'ORO2100'];

        $uniqueDisplayCodes = array_unique($displayCodes);



        usort($uniqueDisplayCodes, function ($a, $b) use ($desiredOrder) {

            // Buscar directamente los códigos completos en el orden deseado
            $posA = array_search($a, $desiredOrder);
            $posB = array_search($b, $desiredOrder);

            // Si no se encuentra en el orden deseado, poner al final
            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;

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
            // Buscar en la nueva estructura de lotteryGroups
            foreach ($this->lotteryGroups as $time => $lotteries) {
                foreach ($lotteries as $index => $lottery) {
                    if ($lottery['ui_code'] === $code) {
                        $this->selected["{$time}_col_" . ($index + 1)] = true;
                        break 2; // Salir de ambos bucles
                    }
                }
            }
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
    
    // Validación personalizada para redoblona
    try {
        $this->validateRedoblona($validatedData);
    } catch (\Exception $e) {
        // La validación de redoblona falló, ya se mostró la notificación
        return;
    }

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
    
    // Validación personalizada para redoblona
    try {
        $this->validateRedoblona($validatedData);
    } catch (\Exception $e) {
        // La validación de redoblona falló, ya se mostró la notificación
        return;
    }

    // Verificar si se ha seleccionado al menos una lotería
    if (empty($this->checkboxCodes) || empty(array_filter($this->checkboxCodes))) {
        $this->dispatch('notify', message: 'Debe seleccionar al menos una lotería.', type: 'warning');
        return;
    }

    try {
        $importeAGuardar = $validatedData['import'];
        
        // Filtrar solo códigos válidos según la configuración de Quinielas
        $validCodes = [];
        $globalConfig = GlobalQuinielasConfiguration::all()
            ->keyBy('city_name')
            ->map(function($config) {
                return $config->selected_schedules;
            });
            
        foreach ($this->checkboxCodes as $code) {
            // Verificar si este código corresponde a una lotería configurada
            foreach ($this->lotteryGroups as $time => $lotteries) {
                foreach ($lotteries as $lottery) {
                    if ($lottery['ui_code'] === $code) {
                        $cityName = $lottery['name'];
                        $selectedSchedules = $globalConfig[$cityName] ?? [];
                        if (in_array($time, $selectedSchedules)) {
                            $validCodes[] = $code;
                        }
                        break 2; // Salir de ambos loops
                    }
                }
            }
        }
        
        $currentLotteryString = implode(',', array_unique($validCodes));
        
        // Verificar que hay al menos un código válido
        if (empty($validCodes)) {
            $this->dispatch('notify', message: 'No hay loterías válidas seleccionadas según la configuración de Quinielas.', type: 'warning');
            return;
        }

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

        // **Protección robusta contra duplicados** (manuales y automáticos)
        
        // 1. Generar clave única para esta jugada específica
        $playHash = md5(auth()->id() . $this->formatNumber($validatedData['number']) . 
                       ($validatedData['position'] ?? 1) . $currentLotteryString . 
                       $validatedData['numberR'] . $validatedData['positionR']);
        
        // 2. Verificar si ya existe un bloqueo activo para esta jugada
        $lockKey = 'play_creation_' . $playHash;
        if (\Cache::has($lockKey)) {
            $this->dispatch('notify', message: 'Jugada en proceso. Espere un momento...', type: 'info');
            return;
        }
        
        // 3. Crear bloqueo temporal mínimo (0.1 segundos) para evitar duplicados
        \Cache::put($lockKey, true, 0.1);
        
        try {
            // 4. Verificar duplicados en base de datos (últimos 1 segundo)
            $recentPlay = Play::where('user_id', auth()->id())
                ->where('number', $this->formatNumber($validatedData['number']))
                ->where('position', $validatedData['position'] ?? 1)
                ->where('lottery', $currentLotteryString)
                ->where('numberR', $validatedData['numberR'])
                ->where('positionR', $validatedData['positionR'])
                ->where('created_at', '>=', now()->subSeconds(1))
                ->first();

            if ($recentPlay) {
                \Cache::forget($lockKey); // Liberar bloqueo
                $this->dispatch('notify', message: 'Jugada duplicada detectada. Espere unos segundos.', type: 'warning');
                return;
            }

            // 5. Crear la nueva jugada
            $newPlay = Play::create($playDataToCreate);
            
        } finally {
            // 6. Liberar bloqueo siempre (incluso si hay error)
            \Cache::forget($lockKey);
        }

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

        // MEJORA: Reactivar las bajadas si se creó una nueva jugada base (3 o 4 dígitos)
        $cleanNumber = str_replace('*', '', $validatedData['number']);
        if (strlen($cleanNumber) >= 3 && ctype_digit($cleanNumber)) {
            $lastDerivedKey = 'lastDerivedCompleted_' . auth()->id();
            \Cache::forget($lastDerivedKey); // Reactivar las bajadas
            $this->currentBasePlayId = $newPlay->id; // Actualizar ID de base
            $this->currentDerivedCount = 0; // Resetear contador
            
            // MEJORA: Limpiar también el cache de bloqueo de derivadas
            $lockKey = 'addRowWithDerived_lock_' . auth()->id();
            \Cache::forget($lockKey);
            
            // Logging para debugging
            \Log::info("Nueva jugada base creada para derivación", [
                'user_id' => auth()->id(),
                'play_id' => $newPlay->id,
                'number' => $newPlay->number,
                'clean_number' => $cleanNumber,
                'previous_base_play_id' => $this->currentBasePlayId
            ]);
        }

        // Limpiar el ID de la jugada si estamos en modo edición
        if ($this->editingRowId) $this->editingRowId = null;
    } catch (\Exception $e) {
        // En caso de error, notificar el fallo
        \Log::error('Error al agregar jugada: ' . $e->getMessage());
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
            
            // Validación personalizada para redoblona
            try {
                $this->validateRedoblona($validatedData);
            } catch (\Exception $e) {
                // La validación de redoblona falló, ya se mostró la notificación
                return;
            }
            
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
        // Obtener configuración global de quinielas
        $globalConfig = GlobalQuinielasConfiguration::all()
            ->keyBy('city_name')
            ->map(function($config) {
                return $config->selected_schedules;
            });
        
        // Si no hay configuración global guardada, usar configuración por defecto
        if ($globalConfig->isEmpty()) {
            $defaultSelectedLotteries = [
                'CIUDAD', 'CHACO', 'PROVINCIA', 'MENDOZA', 'CORRIENTES', 
                'SANTA FE', 'CORDOBA', 'ENTRE RIOS', 'MONTEVIDEO'
            ];
            
            foreach ($defaultSelectedLotteries as $cityName) {
                $globalConfig[$cityName] = $this->citySchedules[$cityName] ?? [];
            }
        }
        
        // Filtrar horarios: solo mostrar aquellos que tienen al menos una lotería seleccionada
        $horariosFiltrados = collect($this->horarios)->filter(function($time) use ($globalConfig) {
            // Verificar si hay al menos una lotería seleccionada para este horario
            foreach ($globalConfig as $cityName => $selectedSchedules) {
                if (in_array($time, $selectedSchedules)) {
                    // Verificar si esta lotería realmente existe en este horario
                    if (isset($this->lotteryGroups[$time])) {
                        foreach ($this->lotteryGroups[$time] as $lottery) {
                            if ($lottery['name'] === $cityName) {
                                return true; // Hay al menos una lotería seleccionada para este horario
                            }
                        }
                    }
                }
            }
            return false; // No hay loterías seleccionadas para este horario
        })->values();

        return $horariosFiltrados->map(function ($time) {

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
        // Obtener configuración global de quinielas
        $globalConfig = GlobalQuinielasConfiguration::all()
            ->keyBy('city_name')
            ->map(function($config) {
                return $config->selected_schedules;
            });

        $codesToUpdate = [];

        $anyCheckboxChanged = false;



        foreach ($this->horarios as $hora) {

            if ($this->isDisabled($hora)) continue;



            if ($this->selected[$hora] != $checked) {

                $this->selected[$hora] = $checked;

                $anyCheckboxChanged = true;
            }



            // Usar la nueva estructura de lotteryGroups
            if (isset($this->lotteryGroups[$hora])) {
                foreach ($this->lotteryGroups[$hora] as $colIdx => $lottery) {

                    $key = "{$hora}_col_" . ($colIdx + 1);

                    // Verificar si esta lotería está configurada para este horario
                    $cityName = $lottery['name'];
                    $selectedSchedules = $globalConfig[$cityName] ?? [];
                    
                    if (in_array($hora, $selectedSchedules) && $this->selected[$key] != $checked) {

                        $this->selected[$key] = $checked;

                        $anyCheckboxChanged = true;
                    }

                    // Solo agregar códigos de loterías configuradas en Quinielas
                    $cityName = $lottery['name'];
                    $selectedSchedules = $globalConfig[$cityName] ?? [];
                    
                    if (in_array($hora, $selectedSchedules)) {
                        $codesToUpdate[] = $lottery['ui_code'];
                    }
                }
            }



        }



        if ($checked) {
            // Solo agregar códigos de loterías que están realmente marcadas en los checkboxes individuales
            $actuallySelectedCodes = [];
            foreach ($this->horarios as $hora) {
                if ($this->isDisabled($hora)) continue;
                
                if (isset($this->lotteryGroups[$hora])) {
                    foreach ($this->lotteryGroups[$hora] as $colIdx => $lottery) {
                        $key = "{$hora}_col_" . ($colIdx + 1);
                        
                        // Verificar si esta lotería está configurada para este horario
                        $cityName = $lottery['name'];
                        $selectedSchedules = $globalConfig[$cityName] ?? [];
                        
                        // Solo agregar si está marcada Y está configurada para este horario
                        if (in_array($hora, $selectedSchedules) && $this->selected[$key]) {
                            $actuallySelectedCodes[] = $lottery['ui_code'];
                        }
                    }
                }
            }
            
            $newCodes = array_values(array_unique($actuallySelectedCodes));

            if ($this->checkboxCodes != $newCodes) {
                $this->checkboxCodes = $newCodes;
                $anyCheckboxChanged = true;
            }
        } else {
            // Al desmarcar "todos", limpiar todos los códigos
            $this->checkboxCodes = [];
            $anyCheckboxChanged = true;
        }


        if ($anyCheckboxChanged) {

            $this->updateLotteryCount();
        }
    }



    public function toggleRowCheckboxes($time, $checked)

    {

        if ($this->isDisabled($time)) return;

        // Obtener configuración global de quinielas
        $globalConfig = GlobalQuinielasConfiguration::all()
            ->keyBy('city_name')
            ->map(function($config) {
                return $config->selected_schedules;
            });

        $currentCodesInRow = [];

        $anyCheckboxChanged = false;



        // Usar la nueva estructura de lotteryGroups
        if (isset($this->lotteryGroups[$time])) {
            foreach ($this->lotteryGroups[$time] as $index => $lottery) {

                $key = "{$time}_col_" . ($index + 1);

                if (($this->selected[$key] ?? false) != $checked) {

                    $this->selected[$key] = $checked;

                    $anyCheckboxChanged = true;
                }

                // Solo agregar códigos de loterías que están configuradas para este horario
                $cityName = $lottery['name'];
                $selectedSchedules = $globalConfig[$cityName] ?? [];
                
                if (in_array($time, $selectedSchedules)) {
                    $currentCodesInRow[] = $lottery['ui_code'];
                }
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
        // Obtener el código UI de la nueva estructura
        $lottery = $this->lotteryGroups[$time][$col - 1] ?? null;
        if (!$lottery) return;
        
        $code = $lottery['ui_code'];


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

        // Usar la nueva estructura de lotteryGroups
        if (isset($this->lotteryGroups[$time])) {
            foreach ($this->lotteryGroups[$time] as $index => $lottery) {

                $colKey = "{$time}_col_" . ($index + 1);

                if (empty($this->selected[$colKey] ?? false)) {
                    $allCheckedInRow = false;
                    break;
                }
            }
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

    /**
     * Convierte códigos del sistema a códigos cortos para mostrar
     */
    protected function convertToShortCodes($systemCodes)
    {
        $shortCodes = [];
        foreach ($systemCodes as $code) {
            $shortCode = $this->systemToShortCodes[$code] ?? $code;
            $shortCodes[] = $shortCode;
        }
        return $shortCodes;
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

            // Usar la nueva estructura de lotteryGroups
            if (isset($this->lotteryGroups[$h])) {
                foreach ($this->lotteryGroups[$h] as $colIdx => $lottery) {

                    $this->selected["{$h}_col_" . ($colIdx + 1)] = false;
                }
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
            
            // Determinar el sistema de numeración basado en si el usuario es nuevo o existente
            $currentUser = auth()->user();
            $isNewUser = $this->isNewUser($currentUser);
            
            if ($isNewUser) {
                // Sistema nuevo: ID-XXXX (ej: 23-0001, 23-0002)
                $ticket = $this->generateNewUserTicket($currentUser->id);
            } else {
                // Sistema actual: XXXX (ej: 00001, 00002)
                $nextTicketNumber = DB::select("SELECT COALESCE(MAX(CAST(ticket AS UNSIGNED)), 0) + 1 as next_ticket FROM plays_sent")[0]->next_ticket;
                $ticket = str_pad($nextTicketNumber, 5, '0', STR_PAD_LEFT);
            }

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

            // Obtener el número de quiniela de la primera jugada (asumiendo que todas las jugadas del ticket tienen el mismo número)
            $firstPlayNumber = $plays->first()->number ?? '0000';
            
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
                'code' => $firstPlayNumber, // Usar el número de quiniela en lugar del código alfanumérico
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

            // Buscar en la nueva estructura de lotteryGroups
            foreach ($this->lotteryGroups as $time => $lotteries) {

                foreach ($lotteries as $lottery) {

                    if ($lottery['ui_code'] === $code) {

                        $selectedTimes[] = $time;

                        break 2; // Salir de ambos bucles
                    }
                }
            }
        }

        return implode(',', array_unique($selectedTimes));
    }



    private function getTimeApuFromLottery($lotteryUiCode): string

    {

        // Buscar en la nueva estructura de lotteryGroups
        foreach ($this->lotteryGroups as $time => $lotteries) {

            foreach ($lotteries as $lottery) {

                if ($lottery['ui_code'] === $lotteryUiCode) {

                    return $time;
                }
            }
        }

        return '';
    }



    public function addRowWithDerived()
{
    // 1. Verificar si ya hay una operación en curso (evitar clics rápidos)
    if ($this->isCreatingDerived) {
        return;
    }
    
    // 2. Crear una clave de bloqueo única para este usuario y operación
    $lockKey = 'addRowWithDerived_lock_' . auth()->id();
    $lastDerivedKey = 'lastDerivedCompleted_' . auth()->id();
    
    // 3. Verificar si ya hay una operación en curso (evitar clics rápidos)
    if (\Cache::has($lockKey)) {
        // En lugar de mostrar mensaje, simplemente ignorar el clic (más rápido)
        return;
    }
    
    // 4. Verificar si se completó la última derivada y bloquear completamente
    if (\Cache::has($lastDerivedKey)) {
        // La última derivada ya se completó, bloquear completamente
        return;
    }
    
    // 5. Marcar como procesando y establecer bloqueo más largo (0.5 segundos)
    $this->isCreatingDerived = true;
    \Cache::put($lockKey, true, 0.5);
    
    try {
        // MEJORA: Obtener la jugada base de manera más específica y segura
        $basePlay = $this->getBasePlayForDerivation();

        if (!$basePlay) {
            $this->dispatch('notify', message: 'Debe haber una jugada principal de 3 o 4 dígitos para generar derivadas.', type: 'error');
            return;
        }

        // MEJORA: Validación adicional de seguridad
        if (!$this->validateBasePlay($basePlay)) {
            $this->dispatch('notify', message: 'La jugada base no es válida para generar derivadas.', type: 'error');
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

        // MEJORA: Verificar qué derivadas ya existen en la base de datos (UNA SOLA CONSULTA)
        $existingDerivedNumbers = $this->getExistingDerivedNumbers($basePlay, $derivedNumbersToCreate);
        
        // OPTIMIZACIÓN: Verificación rápida - si no hay derivadas existentes, crear la primera
        if (empty($existingDerivedNumbers)) {
            $nextDerivedIndex = 0;
            $newDerivedNumberFormatted = $derivedNumbersToCreate[0];
            $this->currentDerivedCount = 0;
        } else {
            // Encontrar la siguiente derivada que no existe
            $nextDerivedIndex = $this->findNextDerivedIndex($derivedNumbersToCreate, $existingDerivedNumbers);
            
            if ($nextDerivedIndex === null) {
                // Todas las derivadas ya existen, bloquear
                \Cache::put($lastDerivedKey, true, 10); // Bloquear por 10 segundos
                $this->dispatch('notify', message: 'Todas las jugadas derivadas ya fueron creadas para este número base.', type: 'info');
                return;
            }

            $newDerivedNumberFormatted = $derivedNumbersToCreate[$nextDerivedIndex];
            $this->currentDerivedCount = $nextDerivedIndex;
        }

        // OPTIMIZACIÓN: Verificación rápida de duplicados (solo si ya sabemos que existe)
        if (in_array($newDerivedNumberFormatted, $existingDerivedNumbers)) {
            // Ya verificamos que existe, no necesitamos consultar de nuevo
            \Log::warning("Duplicado detectado en derivación (ya verificado)", [
                'user_id' => auth()->id(),
                'new_derived_number' => $newDerivedNumberFormatted,
                'base_play_id' => $basePlay->id
            ]);
            \Cache::forget($lockKey);
            $this->isCreatingDerived = false;
            return;
        }

        // OPTIMIZACIÓN: Eliminadas verificaciones redundantes ya que la consulta principal ya las cubre

        // MEJORA: Crear la nueva jugada derivada con logging detallado
        $newPlayData = [
            'user_id' => auth()->id(),
            'type' => $basePlay->type,
            'number' => $newDerivedNumberFormatted,
            'position' => $basePlay->position,
            'import' => $basePlay->import,
            'lottery' => $basePlay->lottery,
            'numberR' => $basePlay->numberR,
            'positionR' => $basePlay->positionR,
            'isChecked' => $basePlay->isChecked,
        ];
        
        \Log::info("Creando jugada derivada", [
            'user_id' => auth()->id(),
            'base_play_id' => $basePlay->id,
            'base_number' => $basePlay->number,
            'derived_number' => $newDerivedNumberFormatted,
            'derived_count' => $this->currentDerivedCount + 1,
            'total_derived_possible' => count($derivedNumbersToCreate)
        ]);
        
        $newPlay = Play::create($newPlayData);

        // OPTIMIZACIÓN: Verificación rápida si esta fue la última derivada
        if ($nextDerivedIndex === count($derivedNumbersToCreate) - 1) {
            // Esta fue la última derivada, bloquear por tiempo más largo
            \Cache::put($lastDerivedKey, true, 15); // Bloquear por 15 segundos
            \Log::info("Última derivada creada, bloqueando futuras creaciones", [
                'user_id' => auth()->id(),
                'base_play_id' => $basePlay->id,
                'created_derived_number' => $newDerivedNumberFormatted,
                'total_derived_created' => count($derivedNumbersToCreate)
            ]);
        }
        
        $this->rows = $this->getAndSortPlays();
        $this->needsTotalRecalculation = true;
        $this->dispatch('scroll-to-last-play', ['playId' => $newPlay->id]);
        $this->dispatch('notify', message: "Jugada derivada '{$newDerivedNumberFormatted}' creada correctamente.", type: 'success');
        $this->dispatch('focusInput', ['selector' => '#number']);
        
    } catch (\Exception $e) {
        // En caso de error, liberar el bloqueo y notificar
        \Cache::forget($lockKey);
        \Log::error('Error al crear jugada derivada: ' . $e->getMessage());
        $this->dispatch('notify', message: 'Error al crear jugada derivada.', type: 'error');
    } finally {
        // Asegurar que el bloqueo se libere siempre y resetear el estado
        \Cache::forget($lockKey);
        $this->isCreatingDerived = false;
    }
}


    /**
     * MEJORA: Obtiene la jugada base para derivación de manera más específica y segura
     * Prioriza la jugada que el usuario está visualizando o la más reciente válida
     */
    private function getBasePlayForDerivation()
    {
        $currentUserId = auth()->id();
        
        // Logging para debugging
        \Log::info("Buscando jugada base para derivación", [
            'user_id' => $currentUserId,
            'current_base_play_id' => $this->currentBasePlayId,
            'editing_row_id' => $this->editingRowId
        ]);

        // 1. Si hay una jugada en edición, usar esa como base
        if ($this->editingRowId) {
            $editingPlay = Play::where('id', $this->editingRowId)
                ->where('user_id', $currentUserId)
                ->whereRaw('LENGTH(REPLACE(number, "*", "")) IN (3,4)')
                ->first();
                
            if ($editingPlay) {
                \Log::info("Usando jugada en edición como base", ['play_id' => $editingPlay->id]);
                return $editingPlay;
            }
        }

        // 2. Si hay una jugada base actual válida, verificar que aún existe
        if ($this->currentBasePlayId) {
            $currentBasePlay = Play::where('id', $this->currentBasePlayId)
                ->where('user_id', $currentUserId)
                ->whereRaw('LENGTH(REPLACE(number, "*", "")) IN (3,4)')
                ->first();
                
            if ($currentBasePlay) {
                \Log::info("Usando jugada base actual", ['play_id' => $currentBasePlay->id]);
                return $currentBasePlay;
            }
        }

        // 3. Buscar la jugada más reciente de 3-4 dígitos del usuario actual
        // MEJORA: Agregar validación de tiempo para evitar jugadas muy antiguas
        $basePlay = Play::where('user_id', $currentUserId)
            ->whereRaw('LENGTH(REPLACE(number, "*", "")) IN (3,4)')
            ->where('created_at', '>=', now()->subHours(24)) // Solo jugadas de las últimas 24 horas
            ->orderBy('id', 'desc')
            ->first();

        if ($basePlay) {
            \Log::info("Usando jugada más reciente como base", [
                'play_id' => $basePlay->id,
                'number' => $basePlay->number,
                'created_at' => $basePlay->created_at
            ]);
        } else {
            \Log::warning("No se encontró jugada base válida para derivación", [
                'user_id' => $currentUserId,
                'search_criteria' => '3-4 dígitos, últimas 24 horas'
            ]);
        }

        return $basePlay;
    }

    /**
     * MEJORA: Valida que la jugada base es segura para usar en derivación
     */
    private function validateBasePlay($basePlay): bool
    {
        if (!$basePlay) {
            return false;
        }

        $currentUserId = auth()->id();
        
        // 1. Verificar que la jugada pertenece al usuario actual
        if ($basePlay->user_id !== $currentUserId) {
            \Log::error("Intento de usar jugada de otro usuario", [
                'play_user_id' => $basePlay->user_id,
                'current_user_id' => $currentUserId,
                'play_id' => $basePlay->id
            ]);
            return false;
        }

        // 2. Verificar que la jugada no es muy antigua (máximo 24 horas)
        if ($basePlay->created_at < now()->subHours(24)) {
            \Log::warning("Jugada base muy antigua", [
                'play_id' => $basePlay->id,
                'created_at' => $basePlay->created_at,
                'age_hours' => $basePlay->created_at->diffInHours(now())
            ]);
            return false;
        }

        // 3. Verificar que el número es válido
        $cleanNumber = str_replace('*', '', $basePlay->number);
        if (!ctype_digit($cleanNumber) || !in_array(strlen($cleanNumber), [3, 4])) {
            \Log::warning("Número de jugada base inválido", [
                'play_id' => $basePlay->id,
                'number' => $basePlay->number,
                'clean_number' => $cleanNumber
            ]);
            return false;
        }

        // 4. Verificar que tiene loterías seleccionadas
        if (empty($basePlay->lottery)) {
            \Log::warning("Jugada base sin loterías", [
                'play_id' => $basePlay->id
            ]);
            return false;
        }

        return true;
    }

    /**
     * MEJORA: Obtiene las derivadas que ya existen en la base de datos (OPTIMIZADO)
     */
    private function getExistingDerivedNumbers($basePlay, $derivedNumbersToCreate): array
    {
        // OPTIMIZACIÓN: Una sola consulta en lugar de múltiples consultas
        $existingPlays = Play::where('user_id', auth()->id())
            ->whereIn('number', $derivedNumbersToCreate)
            ->where('position', $basePlay->position)
            ->where('lottery', $basePlay->lottery)
            ->where('numberR', $basePlay->numberR)
            ->where('positionR', $basePlay->positionR)
            ->where('created_at', '>=', now()->subHours(24))
            ->pluck('number')
            ->toArray();
        
        // Solo loggear si hay derivadas existentes (reducir logs)
        if (!empty($existingPlays)) {
            \Log::info("Derivadas existentes encontradas", [
                'user_id' => auth()->id(),
                'base_play_id' => $basePlay->id,
                'existing_derived_numbers' => $existingPlays,
                'total_derived_possible' => count($derivedNumbersToCreate)
            ]);
        }
        
        return $existingPlays;
    }

    /**
     * MEJORA: Encuentra el índice de la siguiente derivada que no existe (OPTIMIZADO)
     */
    private function findNextDerivedIndex($derivedNumbersToCreate, $existingDerivedNumbers): ?int
    {
        // OPTIMIZACIÓN: Usar array_flip para búsqueda O(1) en lugar de in_array O(n)
        $existingNumbersMap = array_flip($existingDerivedNumbers);
        
        foreach ($derivedNumbersToCreate as $index => $derivedNumber) {
            if (!isset($existingNumbersMap[$derivedNumber])) {
                // Solo loggear cuando se encuentra una derivada a crear (reducir logs)
                \Log::info("Siguiente derivada a crear", [
                    'user_id' => auth()->id(),
                    'derived_index' => $index,
                    'derived_number' => $derivedNumber,
                    'existing_count' => count($existingDerivedNumbers),
                    'total_possible' => count($derivedNumbersToCreate)
                ]);
                return $index;
            }
        }
        
        // Solo loggear cuando todas existen (caso menos común)
        \Log::info("Todas las derivadas ya existen", [
            'user_id' => auth()->id(),
            'existing_derived_numbers' => $existingDerivedNumbers,
            'total_derived_possible' => count($derivedNumbersToCreate)
        ]);
        
        return null; // Todas las derivadas ya existen
    }

    /**
     * MEJORA: Obtiene las derivadas que aún no han sido creadas (OPTIMIZADO)
     */
    private function getRemainingDerivedNumbers($basePlay, $derivedNumbersToCreate): array
    {
        // OPTIMIZACIÓN: Reutilizar la consulta ya hecha en lugar de hacer otra
        $existingDerivedNumbers = $this->getExistingDerivedNumbers($basePlay, $derivedNumbersToCreate);
        $existingNumbersMap = array_flip($existingDerivedNumbers);
        $remainingDerived = [];
        
        foreach ($derivedNumbersToCreate as $derivedNumber) {
            if (!isset($existingNumbersMap[$derivedNumber])) {
                $remainingDerived[] = $derivedNumber;
            }
        }
        
        return $remainingDerived;
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

    /**
     * Determina si un usuario es "nuevo" basado en su fecha de creación
     * Los usuarios creados después del 1 de enero de 2025 se consideran nuevos
     */
    private function isNewUser($user): bool
    {
        // Fecha límite para considerar usuarios como "nuevos"
        $cutoffDate = '2025-01-01 00:00:00';
        
        return $user->created_at >= $cutoffDate;
    }

    /**
     * Genera un número de ticket para usuarios nuevos con formato ID-XXXX
     * Ejemplo: 23-0001, 23-0002, etc.
     */
    private function generateNewUserTicket($userId): string
    {
        // Buscar el último ticket del usuario con formato ID-XXXX
        $lastTicket = DB::select("
            SELECT ticket 
            FROM plays_sent 
            WHERE ticket LIKE ? 
            ORDER BY CAST(SUBSTRING_INDEX(ticket, '-', -1) AS UNSIGNED) DESC 
            LIMIT 1
        ", ["{$userId}-%"])[0] ?? null;

        if ($lastTicket) {
            // Extraer el número secuencial del último ticket
            $lastNumber = (int) substr($lastTicket->ticket, strpos($lastTicket->ticket, '-') + 1);
            $nextNumber = $lastNumber + 1;
        } else {
            // Primer ticket del usuario
            $nextNumber = 1;
        }

        return $userId . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
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
            
            'lotteryGroups' => $this->lotteryGroups, // Pasar los grupos de loterías

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

    /**
     * Validación personalizada para redoblona
     * Verifica que solo se pueda hacer redoblona con números de 2 cifras
     */
    private function validateRedoblona($validatedData)
    {
        // Si no hay número de redoblona, no validar
        if (empty($validatedData['numberR'])) {
            return;
        }

        // Obtener el número principal
        $mainNumber = $validatedData['number'] ?? '';
        $cleanMainNumber = str_replace('*', '', $mainNumber);
        $mainDigitCount = strlen($cleanMainNumber);

        // Validar que el número principal sea de 2 cifras
        if ($mainDigitCount !== 2) {
            $this->dispatch('notify', message: 'La redoblona solo se puede con números de 2 cifras.', type: 'warning');
            throw new \Exception('Redoblona validation failed');
        }

        // Validar que el número de redoblona sea de 2 cifras
        $cleanRedoblonaNumber = str_replace('*', '', $validatedData['numberR']);
        $redoblonaDigitCount = strlen($cleanRedoblonaNumber);

        if ($redoblonaDigitCount !== 2) {
            $this->dispatch('notify', message: 'La redoblona solo se puede con números de 2 cifras.', type: 'warning');
            throw new \Exception('Redoblona validation failed');
        }
    }

    /**
     * Formatea los códigos de lotería para mostrar solo las primeras letras + hora
     * Ejemplo: NAC2100 -> NAC21, CHA2100 -> CHA21
     */
    private function formatLotteryCodesForDisplay(array $codes): array
    {
        return array_map(function($code) {
            // Si el código tiene 4 dígitos al final (ej: NAC2100), quitar los últimos 2
            if (preg_match('/^([A-Za-z]+)(\d{4})$/', $code, $matches)) {
                $letters = $matches[1];
                $time = $matches[2];
                // Quitar los últimos 2 dígitos del tiempo
                $shortTime = substr($time, 0, 2);
                return $letters . $shortTime;
            }
            // Si no coincide el patrón, devolver el código original
            return $code;
        }, $codes);
    }

}
