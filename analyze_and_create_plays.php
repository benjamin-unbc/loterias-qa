<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Number;
use App\Models\City;
use App\Models\Extract;
use App\Models\ApusModel;
use App\Models\Result;
use Carbon\Carbon;

echo "=== ANALIZANDO EXTRACTOS DE CIUDAD PARA PREVIA (18-10-2025) ===" . PHP_EOL;

// 1. Buscar la ciudad Ciudad NAC1015 (Previa)
$city = City::where('code', 'NAC1015')->first();
if (!$city) {
    echo "ERROR: No se encontró la ciudad NAC1015" . PHP_EOL;
    exit;
}

$extract = Extract::where('id', $city->extract_id)->first();
if (!$extract) {
    echo "ERROR: No se encontró el extracto" . PHP_EOL;
    exit;
}

$today = '2025-10-18'; // Fecha específica que mencionaste
echo "Fecha: " . $today . PHP_EOL;
echo "Ciudad: " . $city->name . " (" . $city->code . ")" . PHP_EOL;
echo "Extracto: " . $extract->name . PHP_EOL;

// 2. Buscar los números ganadores reales para hoy
$numbers = Number::where('city_id', $city->id)
    ->where('date', $today)
    ->orderBy('index')
    ->get();

if ($numbers->count() == 0) {
    echo "❌ No se encontraron números para la fecha " . $today . PHP_EOL;
    exit;
}

echo PHP_EOL . "=== NÚMEROS GANADORES REALES ENCONTRADOS ===" . PHP_EOL;
echo "Total números: " . $numbers->count() . PHP_EOL;

$winningNumbers = [];
foreach ($numbers as $number) {
    $winningNumbers[$number->index] = $number->value;
    echo "Posición " . $number->index . ": " . $number->value . PHP_EOL;
}

// 3. Crear jugadas ganadoras para Harold (user_id 24) usando números reales
echo PHP_EOL . "=== CREANDO JUGADAS GANADORAS PARA HAROLD ===" . PHP_EOL;

$plays = [];

// Jugada 1: Apuesta de 4 dígitos en posición 1 (a la cabeza) - Tabla Quiniela
$number1 = $winningNumbers[1]; // Número de la cabeza
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-4DIG-HEAD-' . time(),
    'lottery' => 'AB', // Mapea a NAC1015
    'number' => $number1, // Número completo de 4 dígitos
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Jugada 2: Apuesta de 3 dígitos en posición 1 (a la cabeza) - Tabla Quiniela
$last3Digits = substr($number1, -3); // Últimos 3 dígitos del número de la cabeza
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-3DIG-HEAD-' . time(),
    'lottery' => 'AB',
    'number' => '*' . $last3Digits, // *XXX
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Jugada 3: Apuesta de 2 dígitos en posición 1 (a la cabeza) - Tabla Quiniela
$last2Digits = substr($number1, -2); // Últimos 2 dígitos del número de la cabeza
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-2DIG-HEAD-' . time(),
    'lottery' => 'AB',
    'number' => '**' . $last2Digits, // **XX
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Jugada 4: Apuesta de 1 dígito en posición 1 (a la cabeza) - Tabla Quiniela
$last1Digit = substr($number1, -1); // Último dígito del número de la cabeza
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-1DIG-HEAD-' . time(),
    'lottery' => 'AB',
    'number' => '***' . $last1Digit, // ***X
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Jugada 5: Apuesta de 2 dígitos en posición 5 - Tabla Prizes
$number5 = $winningNumbers[5]; // Número en posición 5
$last2Digits5 = substr($number5, -2);
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-2DIG-POS5-' . time(),
    'lottery' => 'AB',
    'number' => '**' . $last2Digits5, // **XX
    'position' => 5, // Posición 5
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Jugada 6: Apuesta de 3 dígitos en posición 10 - Tabla FigureOne
$number10 = $winningNumbers[10]; // Número en posición 10
$last3Digits10 = substr($number10, -3);
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-3DIG-POS10-' . time(),
    'lottery' => 'AB',
    'number' => '*' . $last3Digits10, // *XXX
    'position' => 10, // Posición 10
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Jugada 7: Apuesta de 4 dígitos en posición 15 - Tabla FigureTwo
$number15 = $winningNumbers[15]; // Número en posición 15
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-4DIG-POS15-' . time(),
    'lottery' => 'AB',
    'number' => $number15, // Número completo
    'position' => 15, // Posición 15
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Jugada 8: Apuesta de 2 dígitos en posición 20 - Tabla Prizes
$number20 = $winningNumbers[20]; // Número en posición 20
$last2Digits20 = substr($number20, -2);
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-2DIG-POS20-' . time(),
    'lottery' => 'AB',
    'number' => '**' . $last2Digits20, // **XX
    'position' => 20, // Posición 20
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// 4. Insertar todas las jugadas
echo PHP_EOL . "=== INSERTANDO JUGADAS ===" . PHP_EOL;
foreach ($plays as $playData) {
    $play = ApusModel::create($playData);
    echo "✅ Jugada creada: " . $play->ticket . " - " . $play->number . " en posición " . $play->position . PHP_EOL;
}

echo PHP_EOL . "=== RESUMEN DE JUGADAS CREADAS ===" . PHP_EOL;
echo "1. 4 dígitos en posición 1 (cabeza) - Tabla Quiniela: " . $number1 . " → Esperado: 100 x 3500 = 350,000" . PHP_EOL;
echo "2. 3 dígitos en posición 1 (cabeza) - Tabla Quiniela: *" . $last3Digits . " → Esperado: 100 x 600 = 60,000" . PHP_EOL;
echo "3. 2 dígitos en posición 1 (cabeza) - Tabla Quiniela: **" . $last2Digits . " → Esperado: 100 x 70 = 7,000" . PHP_EOL;
echo "4. 1 dígito en posición 1 (cabeza) - Tabla Quiniela: ***" . $last1Digit . " → Esperado: 100 x 7 = 700" . PHP_EOL;
echo "5. 2 dígitos en posición 5 - Tabla Prizes: **" . $last2Digits5 . " → Esperado: 100 x 14 = 1,400" . PHP_EOL;
echo "6. 3 dígitos en posición 10 - Tabla FigureOne: *" . $last3Digits10 . " → Esperado: 100 x 60 = 6,000" . PHP_EOL;
echo "7. 4 dígitos en posición 15 - Tabla FigureTwo: " . $number15 . " → Esperado: 100 x 175 = 17,500" . PHP_EOL;
echo "8. 2 dígitos en posición 20 - Tabla Prizes: **" . $last2Digits20 . " → Esperado: 100 x 3.50 = 350" . PHP_EOL;

echo PHP_EOL . "✅ Todas las jugadas han sido creadas para Harold (user_id 24)" . PHP_EOL;
echo "Ahora ejecuta el job CalculateLotteryResults para procesar los resultados" . PHP_EOL;
