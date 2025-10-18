<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ApusModel;
use App\Models\Result;
use Carbon\Carbon;

echo "=== CREANDO JUGADAS GANADORAS CON NÚMEROS REALES DE CIUDAD NAC1015 ===" . PHP_EOL;

// Números ganadores reales de Ciudad NAC1015 para hoy
$winningNumbers = [
    1 => '9879',   // Cabeza
    2 => '0191',
    3 => '3136',
    4 => '2311',
    5 => '7483',
    6 => '9095',
    7 => '7060',
    8 => '4667',
    9 => '6939',
    10 => '2389',
    11 => '5997',
    12 => '6107',
    13 => '4134',
    14 => '5094',
    15 => '3719',
    16 => '4290',
    17 => '4547',
    18 => '6795',
    19 => '9057',
    20 => '1069'
];

echo "Números ganadores reales:" . PHP_EOL;
foreach ($winningNumbers as $position => $number) {
    echo "Posición " . $position . ": " . $number . PHP_EOL;
}

$today = Carbon::today()->format('Y-m-d');
echo PHP_EOL . "Fecha: " . $today . PHP_EOL;

// Limpiar jugadas existentes para Harold hoy
ApusModel::where('user_id', 24)->whereDate('created_at', $today)->delete();
Result::where('user_id', 24)->where('date', $today)->delete();

echo PHP_EOL . "=== CREANDO JUGADAS GANADORAS PARA HAROLD (user_id 24) ===" . PHP_EOL;

$plays = [];

// JUGADA 1: 4 dígitos en posición 1 (cabeza) - Tabla Quiniela
$number1 = $winningNumbers[1]; // '9879'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-4DIG-HEAD-001',
    'lottery' => 'AB', // Mapea a NAC1015
    'number' => $number1, // '9879'
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 2: 3 dígitos en posición 1 (cabeza) - Tabla Quiniela
$last3Digits = substr($number1, -3); // '879'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-3DIG-HEAD-002',
    'lottery' => 'AB',
    'number' => '*' . $last3Digits, // '*879'
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 3: 2 dígitos en posición 1 (cabeza) - Tabla Quiniela
$last2Digits = substr($number1, -2); // '79'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-2DIG-HEAD-003',
    'lottery' => 'AB',
    'number' => '**' . $last2Digits, // '**79'
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 4: 1 dígito en posición 1 (cabeza) - Tabla Quiniela
$last1Digit = substr($number1, -1); // '9'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-1DIG-HEAD-004',
    'lottery' => 'AB',
    'number' => '***' . $last1Digit, // '***9'
    'position' => 1, // Posición 1 (a la cabeza)
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 5: 2 dígitos en posición 5 - Tabla Prizes (A los 5)
$number5 = $winningNumbers[5]; // '7483'
$last2Digits5 = substr($number5, -2); // '83'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-2DIG-POS5-005',
    'lottery' => 'AB',
    'number' => '**' . $last2Digits5, // '**83'
    'position' => 5, // Posición 5
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 6: 3 dígitos en posición 10 - Tabla FigureOne (A los 10)
$number10 = $winningNumbers[10]; // '2389'
$last3Digits10 = substr($number10, -3); // '389'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-3DIG-POS10-006',
    'lottery' => 'AB',
    'number' => '*' . $last3Digits10, // '*389'
    'position' => 10, // Posición 10
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 7: 4 dígitos en posición 15 - Tabla FigureTwo (A los 20)
$number15 = $winningNumbers[15]; // '3719'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-4DIG-POS15-007',
    'lottery' => 'AB',
    'number' => $number15, // '3719'
    'position' => 15, // Posición 15
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 8: 2 dígitos en posición 20 - Tabla Prizes (A los 20)
$number20 = $winningNumbers[20]; // '1069'
$last2Digits20 = substr($number20, -2); // '69'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-2DIG-POS20-008',
    'lottery' => 'AB',
    'number' => '**' . $last2Digits20, // '**69'
    'position' => 20, // Posición 20
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 9: 3 dígitos en posición 3 - Tabla FigureOne (A los 5)
$number3 = $winningNumbers[3]; // '3136'
$last3Digits3 = substr($number3, -3); // '136'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-3DIG-POS3-009',
    'lottery' => 'AB',
    'number' => '*' . $last3Digits3, // '*136'
    'position' => 3, // Posición 3
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// JUGADA 10: 4 dígitos en posición 8 - Tabla FigureTwo (A los 10)
$number8 = $winningNumbers[8]; // '4667'
$plays[] = [
    'user_id' => 24,
    'ticket' => 'TEST-4DIG-POS8-010',
    'lottery' => 'AB',
    'number' => $number8, // '4667'
    'position' => 8, // Posición 8
    'import' => 100,
    'numberR' => null,
    'positionR' => null,
    'created_at' => now(),
    'updated_at' => now()
];

// Insertar todas las jugadas
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
echo "9. 3 dígitos en posición 3 - Tabla FigureOne: *" . $last3Digits3 . " → Esperado: 100 x 120 = 12,000" . PHP_EOL;
echo "10. 4 dígitos en posición 8 - Tabla FigureTwo: " . $number8 . " → Esperado: 100 x 350 = 35,000" . PHP_EOL;

echo PHP_EOL . "✅ Total: 10 jugadas creadas para Harold (user_id 24)" . PHP_EOL;
echo "Ahora ejecuta el job CalculateLotteryResults para procesar los resultados" . PHP_EOL;
